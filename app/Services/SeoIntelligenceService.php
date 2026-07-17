<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentRefreshTask;
use App\Models\Keyword;
use App\Models\SearchPerformanceSnapshot;
use App\Models\SeoActionItem;
use App\Models\SeoProject;
use App\Models\SerpDifferentiationBrief;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SeoIntelligenceService
{
    /** @return array{import:array<string,mixed>,actions:int,briefs:int,refresh_tasks:int} */
    public function run(int $days = 28): array
    {
        $import = app(SearchPerformanceImportService::class)->import(now()->subDays($days + 2), now()->subDays(2));
        $analysis = $this->analyze($days);

        return ['import' => $import, ...$analysis];
    }

    /** @return array{actions:int,briefs:int,refresh_tasks:int} */
    public function analyze(int $days = 28): array
    {
        $actions = 0;
        $briefs = 0;
        $refreshTasks = 0;
        $snapshots = SearchPerformanceSnapshot::query()
            ->with(['article.project', 'project'])
            ->where('date_to', '>=', now()->subDays($days)->toDateString())
            ->where('impressions', '>', 0)
            ->get();

        foreach ($this->articleQueryAggregates($snapshots) as $row) {
            $created = $this->actionsForArticleQuery($row);
            $actions += $created['actions'];
            $refreshTasks += $created['refresh_tasks'];

            if ($row['article']) {
                $brief = $this->buildDifferentiationBrief($row['article']->project, $row['article']->keyword, $row['article']);
                if ($brief) {
                    $briefs++;
                }
            }
        }

        foreach ($this->siteWideGaps($snapshots) as $gap) {
            if ($this->queueAction(
                project: $gap['project'],
                article: null,
                keyword: null,
                type: 'content_gap',
                priority: $gap['priority'],
                title: 'Créer ou rattacher une page pour « '.$gap['query'].' »',
                description: 'Requête visible dans les moteurs sans page clairement associée. À transformer en section, satellite ou nouvelle page si elle correspond à la stratégie.',
                evidence: $gap,
            )) {
                $actions++;
            }
        }

        foreach ($this->publishedArticlesWithoutSearchData($days) as $article) {
            if ($this->queueAction(
                project: $article->project,
                article: $article,
                keyword: $article->keyword,
                type: 'indexing_followup',
                priority: 35,
                title: 'Contrôler l’indexation de « '.$article->title.' »',
                description: 'Article publié sans signal Search Console/Bing récent. Vérifiez l’indexation, le maillage et la couverture sitemap.',
                evidence: ['published_at' => $article->published_at?->toDateString(), 'url' => $article->public_url],
            )) {
                $actions++;
            }
        }

        return ['actions' => $actions, 'briefs' => $briefs, 'refresh_tasks' => $refreshTasks];
    }

    public function generationDirective(SeoProject $project, ?Keyword $keyword, array $blueprint): string
    {
        $keywordText = trim((string) ($keyword?->keyword ?: $blueprint['primary_keyword'] ?? ''));
        $brief = SerpDifferentiationBrief::query()
            ->where('seo_project_id', $project->id)
            ->when($keyword?->id, fn ($query) => $query->where('keyword_id', $keyword->id))
            ->when(! $keyword?->id && $keywordText !== '', fn ($query) => $query->where('primary_keyword', $keywordText))
            ->latest('generated_at')
            ->first();

        if (! $brief && $keywordText !== '') {
            $brief = $this->buildDifferentiationBrief($project, $keyword);
        }

        if (! $brief?->prompt_directive) {
            return '';
        }

        return "\nDIFFÉRENCIATION DATA-DRIVEN\n".$brief->prompt_directive."\n";
    }

    public function buildDifferentiationBrief(SeoProject $project, ?Keyword $keyword = null, ?Article $article = null): ?SerpDifferentiationBrief
    {
        $primaryKeyword = trim((string) ($keyword?->keyword ?: $article?->primary_keyword ?: $article?->title));
        if ($primaryKeyword === '' && ! $article) {
            return null;
        }

        $context = $this->key($primaryKeyword.' '.$article?->title.' '.$article?->topic_key.' '.$article?->content_angle);
        $tokens = $this->tokens($context);
        $competingArticles = Article::query()
            ->where('seo_project_id', $project->id)
            ->whereIn('status', ['review', 'scheduled', 'published'])
            ->when($article?->id, fn ($query) => $query->whereKeyNot($article->id))
            ->latest('published_at')
            ->get()
            ->map(function (Article $candidate) use ($tokens): array {
                $candidateTokens = $this->tokens($this->key($candidate->title.' '.$candidate->primary_keyword.' '.$candidate->topic_key.' '.$candidate->content_angle));
                $score = $tokens !== [] && $candidateTokens !== []
                    ? count(array_intersect($tokens, $candidateTokens)) / max(1, count(array_unique([...$tokens, ...$candidateTokens])))
                    : 0.0;

                return [
                    'id' => $candidate->id,
                    'title' => $candidate->title,
                    'slug' => $candidate->slug,
                    'type' => $candidate->type,
                    'score' => round($score * 100, 1),
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['score'] >= 12)
            ->sortByDesc('score')
            ->take(5)
            ->values()
            ->all();

        $queryEvidence = $this->relatedQueries($project, $article, $tokens)->take(8)->values()->all();
        $angles = $this->requiredAngles($primaryKeyword, $queryEvidence);
        $missing = $this->missingSections($article, $angles);
        $directive = $this->briefDirective($primaryKeyword, $competingArticles, $queryEvidence, $angles, $missing);

        return SerpDifferentiationBrief::query()->updateOrCreate([
            'seo_project_id' => $project->id,
            'article_id' => $article?->id,
            'keyword_id' => $keyword?->id,
            'primary_keyword' => $primaryKeyword ?: null,
        ], [
            'status' => 'ready',
            'competing_articles' => $competingArticles,
            'query_evidence' => $queryEvidence,
            'required_angles' => $angles,
            'missing_sections' => $missing,
            'prompt_directive' => $directive,
            'generated_at' => now(),
        ]);
    }

    /** @param Collection<int, SearchPerformanceSnapshot> $snapshots */
    private function articleQueryAggregates(Collection $snapshots): Collection
    {
        return $snapshots
            ->filter(fn (SearchPerformanceSnapshot $snapshot): bool => $snapshot->article !== null && trim((string) $snapshot->query) !== '')
            ->groupBy(fn (SearchPerformanceSnapshot $snapshot): string => $snapshot->article_id.'|'.$this->key((string) $snapshot->query))
            ->map(function (Collection $rows): array {
                $impressions = (int) $rows->sum('impressions');
                $clicks = (int) $rows->sum('clicks');
                $weightedPosition = $rows->sum(fn (SearchPerformanceSnapshot $row): float => ((float) $row->position) * max(1, (int) $row->impressions));

                return [
                    'project' => $rows->first()->article->project,
                    'article' => $rows->first()->article,
                    'keyword' => $rows->first()->article->keyword,
                    'query' => (string) $rows->first()->query,
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => $impressions > 0 ? $clicks / $impressions : 0.0,
                    'position' => $impressions > 0 ? $weightedPosition / $impressions : null,
                    'providers' => $rows->pluck('provider')->unique()->values()->all(),
                ];
            })
            ->sortByDesc('impressions')
            ->values();
    }

    /** @param array<string,mixed> $row @return array{actions:int,refresh_tasks:int} */
    private function actionsForArticleQuery(array $row): array
    {
        $actions = 0;
        $refreshTasks = 0;
        /** @var Article $article */
        $article = $row['article'];
        $position = (float) ($row['position'] ?? 99);
        $impressions = (int) $row['impressions'];
        $ctr = (float) $row['ctr'];
        $query = (string) $row['query'];
        $priority = $this->priority($impressions, $position, $article);

        if ($impressions >= 80 && $position >= 5 && $position <= 15) {
            if ($this->queueAction(
                project: $row['project'],
                article: $article,
                keyword: $row['keyword'],
                type: 'refresh_content',
                priority: $priority,
                title: 'Gagner des positions sur « '.$query.' »',
                description: 'La page reçoit des impressions mais reste en zone 5-15. Rafraîchir le contenu, renforcer l’intention et le maillage.',
                evidence: $row,
            )) {
                $actions++;
            }
            if ($this->queueRefreshTask($article, 'performance_refresh', $row, $priority)) {
                $refreshTasks++;
            }
        }

        if ($impressions >= 100 && $position <= 10 && $ctr < $this->expectedCtr($position) * 0.55) {
            if ($this->queueAction(
                project: $row['project'],
                article: $article,
                keyword: $row['keyword'],
                type: 'rewrite_title_meta',
                priority: max(5, $priority - 8),
                title: 'Réécrire title/meta pour améliorer le CTR',
                description: 'La position est correcte mais le CTR est faible. Travailler l’offre, la précision et la promesse visible dans les SERP.',
                evidence: $row + ['expected_ctr' => $this->expectedCtr($position)],
            )) {
                $actions++;
            }
        }

        if ($impressions >= 30 && ! $this->articleCoversQuery($article, $query)) {
            if ($this->queueAction(
                project: $row['project'],
                article: $article,
                keyword: $row['keyword'],
                type: 'add_section',
                priority: max(8, $priority - 4),
                title: 'Ajouter une section pour « '.$query.' »',
                description: 'La requête remonte déjà mais n’est pas clairement couverte dans le titre ou le contenu.',
                evidence: $row,
            )) {
                $actions++;
            }
        }

        if (in_array($article->type, ['pricing', 'comparison', 'best_tools', 'alternatives', 'tool_review'], true)
            && (int) $row['clicks'] >= 20
            && $article->affiliateClicks()->count() === 0) {
            if ($this->queueAction(
                project: $row['project'],
                article: $article,
                keyword: $row['keyword'],
                type: 'conversion_review',
                priority: 22,
                title: 'Optimiser la conversion affiliate',
                description: 'La page reçoit du trafic organique mais aucun clic affilié enregistré.',
                evidence: $row,
            )) {
                $actions++;
            }
        }

        return ['actions' => $actions, 'refresh_tasks' => $refreshTasks];
    }

    /** @param Collection<int, SearchPerformanceSnapshot> $snapshots */
    private function siteWideGaps(Collection $snapshots): Collection
    {
        return $snapshots
            ->filter(fn (SearchPerformanceSnapshot $snapshot): bool => $snapshot->article_id === null && trim((string) $snapshot->query) !== '')
            ->groupBy(fn (SearchPerformanceSnapshot $snapshot): string => ($snapshot->seo_project_id ?: 0).'|'.$this->key((string) $snapshot->query))
            ->map(function (Collection $rows): array {
                $impressions = (int) $rows->sum('impressions');
                $clicks = (int) $rows->sum('clicks');
                $position = (float) $rows->avg('position');

                return [
                    'project' => $rows->first()->project ?: SeoProject::query()->where('status', 'active')->first(),
                    'query' => (string) $rows->first()->query,
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'position' => round($position, 2),
                    'priority' => $this->priority($impressions, $position),
                ];
            })
            ->filter(fn (array $row): bool => $row['project'] !== null && $row['impressions'] >= 50)
            ->sortBy('priority')
            ->values();
    }

    private function publishedArticlesWithoutSearchData(int $days): Collection
    {
        return Article::query()
            ->with('project')
            ->where('status', 'published')
            ->where('published_at', '<=', now()->subDays(14))
            ->whereDoesntHave('searchPerformanceSnapshots', fn ($query) => $query->where('date_to', '>=', now()->subDays($days)->toDateString()))
            ->limit(50)
            ->get();
    }

    private function queueAction(
        ?SeoProject $project,
        ?Article $article,
        ?Keyword $keyword,
        string $type,
        int $priority,
        string $title,
        ?string $description,
        array $evidence,
    ): bool {
        $signature = sha1($type.'|'.($article?->id ?: 'site').'|'.Str::ascii(mb_strtolower((string) ($evidence['query'] ?? $title))));
        $existing = SeoActionItem::query()
            ->where('type', $type)
            ->whereIn('status', ['queued', 'in_progress'])
            ->get()
            ->contains(fn (SeoActionItem $item): bool => data_get($item->evidence, 'signature') === $signature);
        if ($existing) {
            return false;
        }

        $evidence['signature'] = $signature;
        SeoActionItem::query()->create([
            'seo_project_id' => $project?->id ?: $article?->seo_project_id,
            'article_id' => $article?->id,
            'keyword_id' => $keyword?->id,
            'source' => 'search_intelligence',
            'type' => $type,
            'priority' => max(1, min(100, $priority)),
            'status' => 'queued',
            'title' => mb_substr($title, 0, 255),
            'description' => $description,
            'evidence' => $evidence,
            'due_at' => now()->addDays($priority <= 20 ? 1 : 7),
        ]);

        return true;
    }

    private function queueRefreshTask(Article $article, string $reason, array $payload, int $priority): bool
    {
        $exists = ContentRefreshTask::query()
            ->where('article_id', $article->id)
            ->where('reason', $reason)
            ->whereIn('status', ['queued', 'processing'])
            ->exists();
        if ($exists) {
            return false;
        }

        ContentRefreshTask::query()->create([
            'seo_project_id' => $article->seo_project_id,
            'article_id' => $article->id,
            'reason' => $reason,
            'priority' => max(1, min(100, $priority)),
            'status' => 'queued',
            'payload' => $payload,
            'scheduled_at' => now(),
        ]);

        return true;
    }

    private function relatedQueries(SeoProject $project, ?Article $article, array $tokens): Collection
    {
        return SearchPerformanceSnapshot::query()
            ->when($article?->id, fn ($query) => $query->where('article_id', $article->id), fn ($query) => $query->where('seo_project_id', $project->id))
            ->where('date_to', '>=', now()->subDays(60)->toDateString())
            ->whereNotNull('query')
            ->get()
            ->filter(function (SearchPerformanceSnapshot $snapshot) use ($tokens): bool {
                if ($tokens === []) {
                    return true;
                }
                $queryTokens = $this->tokens($this->key((string) $snapshot->query));

                return count(array_intersect($tokens, $queryTokens)) > 0;
            })
            ->groupBy(fn (SearchPerformanceSnapshot $snapshot): string => $this->key((string) $snapshot->query))
            ->map(function (Collection $rows): array {
                $impressions = (int) $rows->sum('impressions');
                $clicks = (int) $rows->sum('clicks');

                return [
                    'query' => (string) $rows->first()->query,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'position' => round((float) $rows->avg('position'), 2),
                ];
            })
            ->sortByDesc('impressions')
            ->values();
    }

    /** @param array<int, array<string,mixed>> $queries @return string[] */
    private function requiredAngles(string $primaryKeyword, array $queries): array
    {
        $text = $this->key($primaryKeyword.' '.collect($queries)->pluck('query')->implode(' '));
        $angles = [];

        foreach ([
            'gratuit' => 'prix, plan gratuit et limites réelles',
            'auto entrepreneur' => 'cas auto-entrepreneur avec TVA, URSSAF et livre des recettes',
            'btp' => 'critères métier BTP séparés des logiciels généralistes',
            'batiment' => 'critères métier bâtiment séparés des logiciels généralistes',
            'tarif' => 'tarifs sourcés et conditions commerciales',
            'prix' => 'tarifs sourcés et conditions commerciales',
            'comparatif' => 'comparaison neutre par profil utilisateur',
            'avis' => 'verdict nuancé avec limites prouvées',
            'electronique' => 'conformité facturation électronique avec source officielle',
        ] as $needle => $angle) {
            if (str_contains($text, $this->key($needle))) {
                $angles[] = $angle;
            }
        }

        return array_values(array_unique($angles ?: ['angle métier précis, non générique']));
    }

    /** @param string[] $angles @return string[] */
    private function missingSections(?Article $article, array $angles): array
    {
        if (! $article) {
            return $angles;
        }
        $body = $this->key($article->title.' '.$article->body);

        return collect($angles)
            ->reject(fn (string $angle): bool => collect($this->tokens($this->key($angle)))->every(fn (string $token): bool => str_contains($body, $token)))
            ->values()
            ->all();
    }

    private function briefDirective(string $primaryKeyword, array $competingArticles, array $queries, array $angles, array $missing): string
    {
        $competing = collect($competingArticles)->map(fn (array $item): string => '- '.$item['title'].' (similarité interne '.$item['score'].' %)')->implode("\n");
        $queryLines = collect($queries)->map(fn (array $item): string => '- '.$item['query'].' : '.$item['impressions'].' impressions, position '.$item['position'])->implode("\n");
        $angleLines = collect($angles)->map(fn (string $angle): string => '- '.$angle)->implode("\n");
        $missingLines = collect($missing)->map(fn (string $section): string => '- '.$section)->implode("\n");

        return trim(<<<TEXT
Mot-clé cible : {$primaryKeyword}

Ne duplique pas les contenus internes proches :
{$competing}

Signaux de recherche à couvrir naturellement si pertinents :
{$queryLines}

Angles différenciants obligatoires :
{$angleLines}

Sections ou preuves à renforcer :
{$missingLines}

Le contenu doit apporter une information plus précise que ces voisins internes : critères métier, limites prouvées, données vérifiées, cas d’usage et décision par profil. N’écris pas une variante générique.
TEXT);
    }

    private function articleCoversQuery(Article $article, string $query): bool
    {
        $articleText = $this->key($article->title.' '.$article->primary_keyword.' '.$article->body);
        $queryTokens = array_slice($this->tokens($this->key($query)), 0, 6);
        if ($queryTokens === []) {
            return true;
        }

        $covered = collect($queryTokens)->filter(fn (string $token): bool => str_contains($articleText, $token))->count();

        return $covered >= max(2, (int) ceil(count($queryTokens) * 0.65));
    }

    private function expectedCtr(float $position): float
    {
        return match (true) {
            $position <= 1.5 => 0.28,
            $position <= 3 => 0.14,
            $position <= 5 => 0.07,
            $position <= 8 => 0.035,
            default => 0.018,
        };
    }

    private function priority(int $impressions, float $position, ?Article $article = null): int
    {
        $score = 70;
        $score -= min(35, (int) floor($impressions / 80));
        if ($position >= 4 && $position <= 12) {
            $score -= 12;
        }
        if ($article && in_array($article->type, ['pricing', 'comparison', 'best_tools', 'alternatives'], true)) {
            $score -= 10;
        }

        return max(5, min(95, $score));
    }

    /** @return string[] */
    private function tokens(string $value): array
    {
        $stop = ['avec', 'dans', 'pour', 'sans', 'plus', 'guide', 'logiciel', 'outil', 'outils', 'meilleur', 'meilleurs', 'votre', 'comment'];

        return array_values(array_unique(array_filter(
            preg_split('/[^a-z0-9]+/u', $value) ?: [],
            fn (string $token): bool => strlen($token) >= 3 && ! in_array($token, $stop, true),
        )));
    }

    private function key(string $value): string
    {
        $value = Str::ascii(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?: '');
    }
}
