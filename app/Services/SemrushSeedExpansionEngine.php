<?php

namespace App\Services;

use App\Models\KeywordSeed;
use App\Models\SeoProject;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

final class SemrushSeedExpansionEngine
{
    private const JOBS = ['developpeur', 'consultant', 'graphiste', 'coach', 'formateur', 'redacteur', 'photographe', 'artisan'];

    public function __construct(
        private readonly SemrushKeywordMetricsClient $client,
        private readonly SemrushCsvImporter $importer,
    ) {}

    /** @return array{project:string,cluster:?string,seeds:int,requested:int,imported:int,errors:int,planned:array<int,string>} */
    public function runForProject(SeoProject $project, ?string $cluster = 'facturation', int $limit = 20, bool $dryRun = false): array
    {
        if ($limit < 1) {
            throw new RuntimeException('La limite Semrush doit être supérieure à zéro.');
        }
        if (! $dryRun && ! $this->client->hasApiKey()) {
            throw new RuntimeException('Clé API Semrush manquante. Ajoutez SEMRUSH_API_KEY avant de lancer la boucle.');
        }

        $stats = [
            'project' => $project->slug,
            'cluster' => $cluster,
            'seeds' => 0,
            'requested' => 0,
            'imported' => 0,
            'errors' => 0,
            'planned' => [],
        ];

        $seeds = $this->seeds($project, $cluster);
        foreach ($seeds as $seed) {
            if ($stats['requested'] >= $limit) {
                break;
            }

            $stats['seeds']++;
            $importedForSeed = 0;

            foreach ($this->queriesForSeed($seed) as $query) {
                if ($stats['requested'] >= $limit) {
                    break;
                }

                $stats['requested']++;
                if ($dryRun) {
                    $stats['planned'][] = $query;

                    continue;
                }

                try {
                    $row = $this->client->metricRow($query, $project->country ?: 'FR');
                    $keyword = $this->importer->importMetricRow($project, $row, refreshProject: false);
                    if ($keyword) {
                        $stats['imported']++;
                        $importedForSeed++;
                    }
                } catch (Throwable $exception) {
                    $stats['errors']++;
                    $seed->forceFill(['last_error' => $exception->getMessage()])->save();
                }
            }

            if (! $dryRun && $importedForSeed > 0) {
                $seed->forceFill([
                    'last_expanded_at' => now(),
                    'fetched_keywords_count' => (int) $seed->fetched_keywords_count + $importedForSeed,
                    'last_error' => null,
                ])->save();
            }
        }

        if ($stats['imported'] > 0) {
            $this->importer->refreshProject($project);
        }

        return $stats;
    }

    /** @return Collection<int, KeywordSeed> */
    private function seeds(SeoProject $project, ?string $cluster): Collection
    {
        return $project->keywordSeeds()
            ->where('is_active', true)
            ->when($cluster, fn ($query) => $query->where('affiliate_cluster', $cluster))
            ->orderBy('last_expanded_at')
            ->orderByDesc('indy_fit')
            ->orderBy('id')
            ->get();
    }

    /** @return array<int, string> */
    private function queriesForSeed(KeywordSeed $seed): array
    {
        $queries = [$seed->seed];
        foreach ((array) $seed->variations as $variation) {
            foreach ($this->expandVariation((string) $variation) as $query) {
                $queries[] = $query;
            }
        }

        return collect($queries)
            ->map(fn (string $query): string => trim(preg_replace('/\s+/u', ' ', str_replace('+', ' ', $query)) ?: ''))
            ->filter(fn (string $query): bool => $query !== '')
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function expandVariation(string $variation): array
    {
        $normalized = mb_strtolower($variation);
        if (str_contains($normalized, 'metier')) {
            return array_map(fn (string $job): string => str_ireplace('metier', $job, $variation), self::JOBS);
        }

        return [$variation];
    }
}
