<?php

namespace App\Console\Commands;

use App\Models\SeoProject;
use App\Services\ContentRefreshPlanner;
use Illuminate\Console\Command;

final class PlanContentRefreshTasks extends Command
{
    protected $signature = 'content:plan-refresh {--project= : ID du projet SEO} {--limit=100 : Nombre maximum de tâches à créer}';

    protected $description = 'Planifie les rafraîchissements de sources, claims et audits éditoriaux';

    public function handle(ContentRefreshPlanner $planner): int
    {
        $project = $this->option('project')
            ? SeoProject::query()->findOrFail((int) $this->option('project'))
            : null;
        $limit = max(1, (int) $this->option('limit'));

        $result = $planner->plan($project, $limit);
        $this->info("Refresh planifié : {$result['created']} tâche(s) créée(s), {$result['skipped']} déjà en file.");

        return self::SUCCESS;
    }
}
