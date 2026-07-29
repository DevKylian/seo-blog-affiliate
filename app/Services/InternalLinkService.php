<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\InternalLink;
use App\Models\SeoProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class InternalLinkService
{
    public function __construct(private readonly EditorialDuplicateDetector $duplicates) {}

    public function refresh(Article $article): int
    {
        $article->internalLinks()->where('automatic', true)->delete();
        $targets = $this->suggestions($article);

        foreach ($targets as $candidate) {
            InternalLink::query()->create([
                'source_article_id' => $article->id,
                'target_article_id' => $candidate['article']->id,
                'anchor_text' => $candidate['anchor'],
                'automatic' => true,
            ]);
        }

        return $targets->count();
    }

    /**
     * Retourne les pages complémentaires à communiquer au rédacteur avant la
     * génération. Le texte peut ainsi intégrer les liens dans une phrase utile,
     * tandis que refresh() conserve une garantie déterministe au rendu public.
     *
     * @param  array<string, mixed>  $blueprint
     * @return Collection<int, array{article: ?Article, anchor: string, score: float, role: string, title?: string, url?: string}>
     */
    public function suggestionsForBlueprint(SeoProject $project, array $blueprint, int $limit = 3, ?string $contentType = null): Collection
    {
        $source = new Article([
            'seo_project_id' => $project->id,
            'content_cluster_id' => $blueprint['content_cluster_id'] ?? null,
            'type' => $contentType ?: (string) ($blueprint['content_type'] ?? 'informational'),
            'title' => (string) ($blueprint['title'] ?? $blueprint['unique_promise'] ?? $blueprint['primary_keyword'] ?? ''),
            'primary_keyword' => (string) ($blueprint['primary_keyword'] ?? ''),
            'entity_key' => (string) ($blueprint['entity'] ?? ''),
            'topic_key' => (string) ($blueprint['topic'] ?? ''),
            'search_intent' => (string) ($blueprint['intent'] ?? 'informational'),
            'content_angle' => (string) ($blueprint['angle'] ?? ''),
            'editorial_audience' => (string) ($blueprint['audience'] ?? 'general'),
            'unique_promise' => (string) ($blueprint['unique_promise'] ?? ''),
            'editorial_problem' => (string) ($blueprint['problem'] ?? ''),
            'expected_outcome' => (string) ($blueprint['expected_outcome'] ?? ''),
            'funnel_stage' => (string) ($blueprint['funnel_stage'] ?? 'consideration'),
            'excluded_topics' => $blueprint['excluded_topics'] ?? [],
            'body' => collect($blueprint['outline'] ?? [])->map(fn ($heading) => '## '.$heading)->implode("\n"),
        ]);
        $source->setRelation('project', $project);

        $suggestions = $this->suggestions($source, $limit);
        if ($project->articles()->where('status', 'published')->count() >= 10 || $suggestions->count() >= $limit) {
            return $suggestions;
        }

        return $suggestions
            ->concat($this->youngBlogFallbacks($project))
            ->unique(fn (array $target) => $target['url'] ?? $target['article']?->public_path)
            ->take($limit)
            ->values();
    }

    /** @return Collection<int, array{article: null, anchor: string, score: float, role: string, title: string, url: string}> */
    private function youngBlogFallbacks(SeoProject $project): Collection
    {
        $categories = Category::query()
            ->whereHas('articles', fn ($query) => $query->where('seo_project_id', $project->id)->where('status', 'published'))
            ->orderBy('name')
            ->limit(3)
            ->get()
            ->map(fn (Category $category): array => [
                'article' => null,
                'anchor' => 'nos guides '.mb_strtolower($category->name),
                'title' => 'Guides '.$category->name,
                'url' => route('blog.show', $category->slug, false),
                'score' => 0.0,
                'role' => 'contextual',
            ]);

        return $categories->concat(collect([
            [
                'article' => null,
                'anchor' => 'les derniers guides du blog',
                'title' => 'Blog et guides pratiques',
                'url' => route('blog.index', [], false),
                'score' => 0.0,
                'role' => 'pillar',
            ],
            [
                'article' => null,
                'anchor' => 'la fiche complète de '.$project->name,
                'title' => 'Présentation de '.$project->name,
                'url' => route('tools.show', $project->slug, false),
                'score' => 0.0,
                'role' => 'conversion',
            ],
            [
                'article' => null,
                'anchor' => 'le catalogue des logiciels analysés',
                'title' => 'Tous les outils analysés',
                'url' => route('tools.index', [], false),
                'score' => 0.0,
                'role' => 'complementary',
            ],
        ]));
    }

    /** @return Collection<int, array{article: Article, anchor: string, score: float, role: string}> */
    private function suggestions(Article $article, int $limit = 3): Collection
    {
        $sourceBlueprint = $this->blueprint($article);
        $clusterTargets = $this->clusterTargets($article, $limit);
        $excludedTargetIds = $clusterTargets
            ->pluck('article.id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $candidates = Article::query()
            ->with('project')
            ->where('status', 'published')
            ->whereNull('canonical_article_id')
            ->when($article->exists, fn ($query) => $query->whereKeyNot($article->id))
            ->when($excludedTargetIds !== [], fn ($query) => $query->whereNotIn('id', $excludedTargetIds))
            ->latest('published_at')
            ->get()
            ->map(function (Article $target) use ($article, $sourceBlueprint): ?array {
                $targetBlueprint = $this->blueprint($target);
                $similarity = $this->duplicates->compareBlueprints($sourceBlueprint, $targetBlueprint);
                if ($similarity >= 70) {
                    return null;
                }

                $affinity = $this->topicalAffinity($article, $target);
                $sameProject = (int) $article->seo_project_id === (int) $target->seo_project_id;
                $sameEntity = $sourceBlueprint['entity'] !== '' && $sourceBlueprint['entity'] === $targetBlueprint['entity'];
                if (! $sameProject && ! $sameEntity && $affinity < 0.18) {
                    return null;
                }

                return [
                    'article' => $target,
                    'anchor' => $this->naturalAnchor($target),
                    'score' => $this->complementaryScore($article, $target, $sourceBlueprint, $targetBlueprint, $similarity, $affinity),
                    'affinity' => $affinity,
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->values();

        return $clusterTargets
            ->concat($this->prioritizeThree($candidates, max(0, $limit - $clusterTargets->count())))
            ->take($limit)
            ->values();
    }

    /** @return Collection<int, array{article: Article, anchor: string, score: float, role: string}> */
    private function clusterTargets(Article $article, int $limit): Collection
    {
        $cluster = $article->contentCluster;
        if (! $cluster) {
            return collect();
        }

        if ($cluster->type !== 'pillar' && $cluster->parent_id) {
            $pillar = Article::query()
                ->where('content_cluster_id', $cluster->parent_id)
                ->where('status', 'published')
                ->whereNull('canonical_article_id')
                ->latest('published_at')
                ->first();

            return $pillar && (! $article->exists || ! $pillar->is($article))
                ? collect([['article' => $pillar, 'anchor' => $this->naturalAnchor($pillar), 'score' => 999.0, 'role' => 'pillar']])
                : collect();
        }

        if ($cluster->type !== 'pillar') {
            return collect();
        }

        return Article::query()
            ->whereHas('contentCluster', fn ($query) => $query->where('parent_id', $cluster->id))
            ->where('status', 'published')
            ->whereNull('canonical_article_id')
            ->when($article->exists, fn ($query) => $query->whereKeyNot($article->id))
            ->latest('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Article $target): array => [
                'article' => $target,
                'anchor' => $this->naturalAnchor($target),
                'score' => 980.0,
                'role' => 'satellite',
            ]);
    }

    /**
     * Réserve les trois emplacements SEO avant de compléter par les meilleurs
     * contenus restants lorsque l’une des familles n’existe pas encore.
     *
     * @param  Collection<int, array{article: Article, anchor: string, score: float, affinity: float}>  $candidates
     * @return Collection<int, array{article: Article, anchor: string, score: float, role: string}>
     */
    private function prioritizeThree(Collection $candidates, int $limit): Collection
    {
        $selected = collect();
        $remaining = $candidates->values();
        $take = function (callable $predicate, string $role) use (&$selected, &$remaining, $limit): void {
            if ($selected->count() >= $limit) {
                return;
            }
            $index = $remaining->search($predicate);
            if ($index === false) {
                return;
            }
            $candidate = $remaining->get($index);
            $candidate['role'] = $role;
            $selected->push($candidate);
            $remaining = $remaining->reject(fn (array $item): bool => $item['article']->is($candidate['article']))->values();
        };

        $take(
            fn (array $candidate): bool => in_array($candidate['article']->type, ['pricing', 'comparison'], true),
            'conversion',
        );
        $take(
            fn (array $candidate): bool => (float) $candidate['affinity'] >= 0.12,
            'contextual',
        );
        $take(
            fn (array $candidate): bool => $this->isPillar($candidate['article']),
            'pillar',
        );

        foreach ($remaining as $candidate) {
            if ($selected->count() >= $limit) {
                break;
            }
            $candidate['role'] = 'complementary';
            $selected->push($candidate);
        }

        return $selected->take($limit)->values();
    }

    private function isPillar(Article $article): bool
    {
        $identity = Str::ascii(mb_strtolower(implode(' ', [
            $article->title,
            $article->topic_key,
            $article->content_angle,
            $article->unique_promise,
        ])));

        return preg_match('/\b(?:guide complet|guide ultime|article pilier|page pilier|pillar)\b/', $identity) === 1
            || ($article->type === 'informational' && str_word_count(Str::ascii($article->body)) >= 1800);
    }

    public function refreshProject(SeoProject|int $project): int
    {
        $projectId = $project instanceof SeoProject ? $project->id : $project;
        $count = 0;
        Article::query()
            ->where('seo_project_id', $projectId)
            ->where('status', 'published')
            ->whereNull('canonical_article_id')
            ->orderBy('id')
            ->each(function (Article $article) use (&$count): void {
                $count += $this->refresh($article);
            });

        return $count;
    }

    /** @return array<string, mixed> */
    private function blueprint(Article $article): array
    {
        preg_match_all('/^##\s+(.+)$/mu', $article->body, $headings);

        return [
            'entity' => $article->entity_key ?: Str::slug((string) $article->project?->name),
            'topic' => $article->topic_key ?: $article->primary_keyword ?: $article->title,
            'intent' => $article->search_intent ?: 'informational',
            'angle' => $article->content_angle ?: $article->type,
            'audience' => $article->editorial_audience ?: 'general',
            'primary_keyword' => $article->primary_keyword ?: $article->title,
            'funnel_stage' => $article->funnel_stage ?: 'consideration',
            'problem' => $article->editorial_problem ?: $article->unique_promise ?: '',
            'expected_outcome' => $article->expected_outcome ?: '',
            'unique_promise' => $article->unique_promise ?: $article->excerpt ?: '',
            'excluded_topics' => $article->excluded_topics ?? [],
            'outline' => array_slice($headings[1] ?? [], 0, 12),
        ];
    }

    /** @param array<string, mixed> $sourceBlueprint @param array<string, mixed> $targetBlueprint */
    private function complementaryScore(Article $source, Article $target, array $sourceBlueprint, array $targetBlueprint, float $similarity, float $affinity): float
    {
        $sameProjectBonus = (int) $source->seo_project_id === (int) $target->seo_project_id ? 45 : 0;
        $sameEntityBonus = $sourceBlueprint['entity'] === $targetBlueprint['entity'] ? 25 : 0;
        $sameStyleBonus = $source->type === $target->type ? 15 : 0;
        $sameIntentBonus = $sourceBlueprint['intent'] === $targetBlueprint['intent'] ? 10 : 0;
        $differentTopicBonus = $sourceBlueprint['topic'] !== $targetBlueprint['topic'] ? 12 : 0;
        $anchorAlreadyPresent = str_contains(
            mb_strtolower($source->title.' '.$source->body),
            mb_strtolower($this->naturalAnchor($target)),
        ) ? 30 : 0;
        $recency = $target->published_at?->diffInDays(now()) ?? 365;

        return $sameProjectBonus
            + $sameEntityBonus
            + $sameStyleBonus
            + $sameIntentBonus
            + $differentTopicBonus
            + $anchorAlreadyPresent
            + ($affinity * 35)
            + max(0, 8 - min(8, $recency / 45))
            - ($similarity * 0.20);
    }

    private function naturalAnchor(Article $target): string
    {
        $keyword = trim((string) $target->primary_keyword);
        $wordCount = str_word_count(Str::ascii($keyword));
        if ($keyword !== '' && $wordCount >= 2 && $wordCount <= 10) {
            return mb_substr($keyword, 0, 120);
        }

        $product = trim((string) $target->project?->name) ?: 'ce logiciel';

        return match ($target->type) {
            'pricing' => "les tarifs de {$product}",
            'comparison' => "notre comparatif autour de {$product}",
            'alternatives' => "les alternatives à {$product}",
            'best_tools' => 'notre sélection d’outils adaptés',
            'tool_review' => "notre avis détaillé sur {$product}",
            'question' => "notre tutoriel pas à pas sur {$product}",
            default => "notre guide pratique sur {$product}",
        };
    }

    private function topicalAffinity(Article $source, Article $target): float
    {
        $sourceTokens = $this->tokens(implode(' ', [
            $source->title,
            $source->primary_keyword,
            $source->topic_key,
            $source->content_angle,
            $source->unique_promise,
        ]));
        $targetTokens = $this->tokens(implode(' ', [
            $target->title,
            $target->primary_keyword,
            $target->topic_key,
            $target->content_angle,
            $target->unique_promise,
        ]));
        if ($sourceTokens === [] || $targetTokens === []) {
            return 0.0;
        }

        $intersection = array_intersect($sourceTokens, $targetTokens);
        $shortest = min(count($sourceTokens), count($targetTokens));

        return $shortest > 0 ? count($intersection) / $shortest : 0.0;
    }

    /** @return string[] */
    private function tokens(string $value): array
    {
        $value = mb_strtolower(Str::ascii($value));
        $tokens = preg_split('/[^a-z0-9]+/', $value) ?: [];
        $stopWords = ['avec', 'dans', 'pour', 'sans', 'plus', 'guide', 'comment', 'notre', 'votre', 'leurs', 'entre', 'outil', 'outils', 'logiciel'];

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $token): bool => strlen($token) >= 3 && ! in_array($token, $stopWords, true),
        )));
    }
}
