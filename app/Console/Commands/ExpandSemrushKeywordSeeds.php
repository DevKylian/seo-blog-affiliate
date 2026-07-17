<?php

namespace App\Console\Commands;

use App\Models\SeoProject;
use App\Services\SemrushSeedExpansionEngine;
use Illuminate\Console\Command;

final class ExpandSemrushKeywordSeeds extends Command
{
    protected $signature = 'semrush:expand-seeds
        {project? : Slug ou ID du projet}
        {--cluster=facturation : Cluster affiliate prioritaire, laissez vide pour tous}
        {--limit=20 : Nombre maximum de requetes Semrush}
        {--dry-run : Affiche les requetes sans appeler Semrush}';

    protected $description = 'Alimente la Content Factory depuis keyword_seeds via Semrush, avec tagging intent/cluster affiliate';

    public function handle(SemrushSeedExpansionEngine $engine): int
    {
        $projects = $this->projects();
        if ($projects->isEmpty()) {
            $this->warn('Aucun projet actif trouve.');

            return self::SUCCESS;
        }

        $cluster = trim((string) $this->option('cluster')) ?: null;
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $totalImported = 0;

        foreach ($projects as $project) {
            $stats = $engine->runForProject($project, $cluster, $limit, $dryRun);
            $totalImported += $stats['imported'];

            $this->line(sprintf(
                '%s : %d seed(s), %d requete(s), %d keyword(s), %d erreur(s)',
                $project->slug,
                $stats['seeds'],
                $stats['requested'],
                $stats['imported'],
                $stats['errors'],
            ));

            if ($dryRun && $stats['planned'] !== []) {
                foreach ($stats['planned'] as $query) {
                    $this->line('  - '.$query);
                }
            }
        }

        $this->info($dryRun ? 'Dry-run termine.' : "{$totalImported} mot(s)-cles importes depuis Semrush.");

        return self::SUCCESS;
    }

    private function projects()
    {
        $identifier = $this->argument('project');
        if ($identifier !== null) {
            return SeoProject::query()
                ->where('slug', $identifier)
                ->orWhere('id', (int) $identifier)
                ->get();
        }

        return SeoProject::query()->where('status', 'active')->orderBy('id')->get();
    }
}
