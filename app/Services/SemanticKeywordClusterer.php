<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentBrief;
use App\Models\ContentCluster;
use App\Models\ContentSchedule;
use App\Models\EditorialIdea;
use App\Models\Keyword;
use App\Models\ScheduledContentTask;
use App\Models\SeoProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SemanticKeywordClusterer
{
    /** @return Collection<int, ContentCluster> */
    public function rebuildProject(SeoProject $project): Collection
    {
        $keywords = $project->keywords()->get();
        if ($keywords->isEmpty()) {
            return collect();
        }

        return DB::transaction(function () use ($project, $keywords): Collection {
            $metaByKey = [];
            $clusters = collect();

            foreach ($keywords->groupBy(fn (Keyword $keyword): string => $this->signature($keyword->keyword)['key']) as $key => $group) {
                $signature = $this->signature((string) $group->first()->keyword);
                $primary = $this->primaryKeyword($group);
                $cluster = ContentCluster::query()->firstOrNew([
                    'seo_project_id' => $project->id,
                    'normalized_key' => $key,
                ]);
                $cluster->fill($this->clusterAttributes($group, $primary, $signature));
                if (! $cluster->exists || $cluster->status === 'archived') {
                    $cluster->status = 'queued';
                }
                $cluster->save();

                Keyword::query()
                    ->whereIn('id', $group->pluck('id'))
                    ->update(['content_cluster_id' => $cluster->id]);

                $metaByKey[$key] = $signature;
                $clusters->push($cluster->fresh());
            }

            $clusters = $clusters->keyBy('normalized_key');
            foreach ($clusters as $key => $cluster) {
                $parentKey = $metaByKey[$key]['parent_key'] ?? null;
                $parent = $parentKey && $parentKey !== $key ? $clusters->get($parentKey) : null;
                $cluster->update(['parent_id' => $parent?->id]);
            }

            $this->syncExistingContent($project);
            $this->markStaleClusters($project, $clusters->keys());

            return $clusters->values();
        });
    }

    /** @return Collection<int, ContentCluster> */
    public function queueableClusters(ContentSchedule $schedule, int $limit): Collection
    {
        if ($this->projectNeedsRebuild($schedule->project)) {
            $this->rebuildProject($schedule->project);
        }

        $clusters = $schedule->project->contentClusters()
            ->where('status', '!=', 'archived')
            ->where('keyword_count', '>', 0)
            ->whereDoesntHave('articles', fn ($query) => $query->whereIn('status', ['draft', 'review', 'scheduled', 'published']))
            ->whereDoesntHave('scheduledTasks', fn ($query) => $query->whereNotIn('status', ['cancelled', 'failed']))
            ->with(['canonicalKeyword', 'parent'])
            ->get()
            ->sortByDesc('opportunity_score')
            ->values();

        return $this->factorySequence($clusters)->take($limit)->values();
    }

    private function projectNeedsRebuild(SeoProject $project): bool
    {
        return ! $project->contentClusters()->exists()
            || $project->keywords()->whereNull('content_cluster_id')->exists();
    }

    /** @return array{key:string,parent_key:string,core:string,niche:string[],tokens:string[]} */
    private function signature(string $keyword): array
    {
        $normalized = $this->normalized($keyword);
        $tokens = $this->tokens($normalized);
        $mapped = array_map(fn (string $token): string => $this->synonym($token), $tokens);
        $niche = $this->nicheModifiers($normalized, $mapped);
        $generic = $this->genericModifiers();
        $nicheTokens = collect($niche)->flatMap(fn (string $phrase): array => explode('-', $phrase))->all();

        $coreTokens = collect($mapped)
            ->reject(fn (string $token): bool => in_array($token, $this->stopWords(), true))
            ->reject(fn (string $token): bool => in_array($token, $generic, true))
            ->reject(fn (string $token): bool => in_array($token, $nicheTokens, true))
            ->filter(fn (string $token): bool => (strlen($token) >= 2 || preg_match('/^\d+$/', $token) === 1) && preg_match('/^20\d{2}$/', $token) !== 1)
            ->unique()
            ->sort()
            ->values();

        if ($coreTokens->isEmpty()) {
            $coreTokens = collect($mapped)
                ->reject(fn (string $token): bool => in_array($token, $this->stopWords(), true))
                ->take(4)
                ->values();
        }

        $core = $coreTokens->implode('-') ?: Str::slug($normalized);
        $niche = collect($niche)->unique()->sort()->values()->all();
        $key = $niche === [] ? $core : $core.'--'.implode('-', $niche);

        return [
            'key' => $key,
            'parent_key' => $core,
            'core' => $core,
            'niche' => $niche,
            'tokens' => $coreTokens->all(),
        ];
    }

    /** @param Collection<int, Keyword> $keywords */
    private function primaryKeyword(Collection $keywords): Keyword
    {
        return $keywords
            ->sortByDesc(fn (Keyword $keyword): float => ((int) $keyword->search_volume * 2)
                + ((float) $keyword->opportunity_score * 12)
                + ((float) ($keyword->cpc ?? 0) * 40)
                - ((float) $keyword->keyword_difficulty * 3))
            ->first();
    }

    /** @param Collection<int, Keyword> $keywords @param array<string, mixed> $signature */
    private function clusterAttributes(Collection $keywords, Keyword $primary, array $signature): array
    {
        $volume = (int) $keywords->sum('search_volume');
        $averageDifficulty = round((float) $keywords->avg('keyword_difficulty'), 2);
        $maxDifficulty = round((float) $keywords->max('keyword_difficulty'), 2);
        $maxCpc = $keywords->pluck('cpc')->filter(fn ($value): bool => $value !== null)->max();
        $type = $this->clusterType($keywords, $signature['niche'] !== [], $volume, (float) ($maxCpc ?? 0));

        return [
            'canonical_keyword_id' => $primary->id,
            'name' => $this->clusterName($primary->keyword, $type),
            'slug' => Str::slug($primary->keyword) ?: 'cluster-'.$primary->id,
            'type' => $type,
            'intent' => $this->dominantIntent($keywords),
            'intent_type' => $this->dominantIntentType($keywords),
            'affiliate_cluster' => $this->dominantAffiliateCluster($keywords),
            'keyword_count' => $keywords->count(),
            'total_search_volume' => $volume,
            'average_difficulty' => $averageDifficulty,
            'max_difficulty' => $maxDifficulty,
            'max_cpc' => $maxCpc,
            'opportunity_score' => $this->clusterScore($keywords, $type, $volume, $averageDifficulty, (float) ($maxCpc ?? 0)),
            'affiliate_priority' => round((float) $keywords->max('affiliate_priority'), 2),
        ];
    }

    /** @param Collection<int, Keyword> $keywords */
    private function clusterType(Collection $keywords, bool $hasNicheModifier, int $volume, float $maxCpc): string
    {
        if ($hasNicheModifier) {
            return 'niche';
        }
        if ($volume >= 1000 || $keywords->count() >= 2 || $keywords->contains(fn (Keyword $keyword): bool => $keyword->strategyTier() === 'pillar')) {
            return 'pillar';
        }
        if ($maxCpc >= 5 || $keywords->contains(fn (Keyword $keyword): bool => $keyword->strategyTier() === 'niche')) {
            return 'niche';
        }

        return 'supporting';
    }

    /** @param Collection<int, Keyword> $keywords */
    private function clusterScore(Collection $keywords, string $type, int $volume, float $difficulty, float $maxCpc): float
    {
        $bestOpportunity = (float) $keywords->max('opportunity_score');
        $typeBoost = match ($type) {
            'pillar' => 18,
            'niche' => 12,
            default => 4,
        };
        $score = (log10($volume + 10) * 18)
            + min(18, $maxCpc * 2.4)
            + max(0, 18 - ($difficulty * .25))
            + ($bestOpportunity * .32)
            + $typeBoost;

        return round(min(100, max(0, $score)), 2);
    }

    /** @param Collection<int, Keyword> $keywords */
    private function dominantIntent(Collection $keywords): ?string
    {
        return $keywords
            ->pluck('intent')
            ->filter()
            ->map(fn (string $intent): string => trim($intent))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }

    /** @param Collection<int, Keyword> $keywords */
    private function dominantIntentType(Collection $keywords): string
    {
        return $keywords
            ->pluck('intent_type')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first() ?: 'information';
    }

    /** @param Collection<int, Keyword> $keywords */
    private function dominantAffiliateCluster(Collection $keywords): ?string
    {
        return $keywords
            ->pluck('affiliate_cluster')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }

    private function clusterName(string $keyword, string $type): string
    {
        $prefix = match ($type) {
            'pillar' => 'Pilier',
            'niche' => 'Niche',
            default => 'Support',
        };

        return $prefix.' - '.$keyword;
    }

    /** @param Collection<int, ContentCluster> $clusters */
    private function factorySequence(Collection $clusters): Collection
    {
        $pillars = $clusters->where('type', 'pillar')->values();
        $niches = $clusters->where('type', 'niche')->values();
        $supporting = $clusters->where('type', 'supporting')->values();
        $sequence = collect();

        foreach ($pillars->take(2) as $pillar) {
            $sequence->push($pillar);
        }
        $pillars = $pillars->slice(2)->values();

        while ($pillars->isNotEmpty() || $niches->isNotEmpty() || $supporting->isNotEmpty()) {
            if ($niches->isNotEmpty()) {
                $sequence->push($niches->shift());
            }
            if ($pillars->isNotEmpty()) {
                $sequence->push($pillars->shift());
            }
            if ($supporting->isNotEmpty() && ($niches->isEmpty() || $sequence->count() % 3 === 0)) {
                $sequence->push($supporting->shift());
            }
        }

        return $sequence->unique('id')->values();
    }

    private function syncExistingContent(SeoProject $project): void
    {
        $project->keywords()->whereNotNull('content_cluster_id')->each(function (Keyword $keyword): void {
            Article::query()->where('keyword_id', $keyword->id)->whereNull('content_cluster_id')->update(['content_cluster_id' => $keyword->content_cluster_id]);
            ContentBrief::query()->where('keyword_id', $keyword->id)->whereNull('content_cluster_id')->update(['content_cluster_id' => $keyword->content_cluster_id]);
            EditorialIdea::query()->where('keyword_id', $keyword->id)->whereNull('content_cluster_id')->update(['content_cluster_id' => $keyword->content_cluster_id]);
            ScheduledContentTask::query()->where('keyword_id', $keyword->id)->whereNull('content_cluster_id')->update(['content_cluster_id' => $keyword->content_cluster_id]);
        });
    }

    /** @param Collection<int, string> $activeKeys */
    private function markStaleClusters(SeoProject $project, Collection $activeKeys): void
    {
        $project->contentClusters()
            ->whereNotIn('normalized_key', $activeKeys->all())
            ->whereDoesntHave('articles')
            ->whereDoesntHave('scheduledTasks', fn ($query) => $query->whereNotIn('status', ['cancelled', 'failed']))
            ->update(['status' => 'archived']);
    }

    private function normalized(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    /** @return string[] */
    private function tokens(string $normalized): array
    {
        return array_values(array_filter(preg_split('/\s+/', $normalized) ?: []));
    }

    private function synonym(string $token): string
    {
        return match ($token) {
            'facturation', 'facturations', 'factures' => 'facture',
            'logiciels' => 'logiciel',
            'outils' => 'outil',
            'gratuite', 'gratuits', 'gratuites' => 'gratuit',
            'tarifs' => 'tarif',
            'prix' => 'tarif',
            'comparaison' => 'comparatif',
            'independants', 'independant' => 'independant',
            'entrepreneurs' => 'entrepreneur',
            default => $token,
        };
    }

    /** @param string[] $tokens @return string[] */
    private function nicheModifiers(string $normalized, array $tokens): array
    {
        $phrases = [
            'auto entrepreneur', 'micro entrepreneur', 'profession liberale',
            'artisan', 'paysagiste', 'garage', 'btp', 'batiment', 'chantier',
            'restaurant', 'association', 'freelance', 'independant', 'pme', 'tpe',
            'sas', 'sasu', 'eurl', 'mac', 'excel', 'shopify', 'woocommerce',
            'wordpress', 'immobilier', 'agence', 'consultant', 'startup',
            'ecommerce', 'e commerce',
        ];

        return collect($phrases)
            ->filter(fn (string $phrase): bool => str_contains($normalized, $phrase)
                || collect(explode(' ', $phrase))->every(fn (string $token): bool => in_array($token, $tokens, true)))
            ->map(fn (string $phrase): string => str_replace(' ', '-', $phrase))
            ->values()
            ->all();
    }

    /** @return string[] */
    private function stopWords(): array
    {
        return [
            'a', 'au', 'aux', 'avec', 'ce', 'ces', 'dans', 'de', 'des', 'du',
            'en', 'et', 'la', 'le', 'les', 'leur', 'leurs', 'mon', 'nos', 'notre',
            'ou', 'par', 'pour', 'que', 'qui', 'sans', 'sur', 'un', 'une', 'vos',
            'votre', 'd', 'l',
        ];
    }

    /** @return string[] */
    private function genericModifiers(): array
    {
        return [
            'logiciel', 'outil', 'app', 'application', 'solution',
            'gratuit', 'meilleur', 'meilleurs', 'simple', 'facile',
            'avis', 'test', 'comparatif', 'alternative', 'alternatives',
            'prix', 'tarif', 'cout', 'coute', 'obligatoire', 'professionnel',
            'pro', 'guide', 'complet', 'rapide', 'online', 'ligne',
        ];
    }
}
