<?php

namespace App\Console\Commands;

use App\Models\ContentRefreshTask;
use App\Services\ContentClaimService;
use App\Services\PrePublishAuditService;
use App\Services\Scraping\StaticSiteScraper;
use Illuminate\Console\Command;
use Throwable;

final class ProcessContentRefreshTasks extends Command
{
    protected $signature = 'content:process-refresh {--limit=5 : Nombre maximum de tâches à traiter}';

    protected $description = 'Traite les tâches de rafraîchissement sources/claims/audits';

    public function handle(
        StaticSiteScraper $scraper,
        ContentClaimService $claims,
        PrePublishAuditService $audits,
    ): int {
        set_time_limit(0);
        $limit = max(1, (int) $this->option('limit'));
        $tasks = ContentRefreshTask::query()
            ->with(['project', 'article', 'sourcePage.articles', 'claim.sourcePage'])
            ->where('status', 'queued')
            ->where(fn ($query) => $query->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->orderBy('priority')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($tasks as $task) {
            $task->update(['status' => 'processing', 'error_message' => null]);

            try {
                $source = $task->sourcePage ?: $task->claim?->sourcePage;
                if ($source && $task->project) {
                    $source = $scraper->scrape($task->project, $source->url, $source->type, $source->competitor_name);
                    $claims->syncProject($task->project);
                    $source->articles()->whereIn('status', ['review', 'scheduled', 'published'])->update([
                        'refresh_status' => 'source_refreshed',
                        'refresh_reason' => $this->reasonLabel($task->reason),
                    ]);
                }

                if ($task->article) {
                    $task->article->forceFill([
                        'refresh_status' => 'needs_review',
                        'refresh_reason' => $this->reasonLabel($task->reason),
                    ])->save();
                    $audits->audit($task->article->fresh());
                }

                $task->update(['status' => 'done', 'processed_at' => now()]);
                $this->line("Tâche #{$task->id} traitée.");
            } catch (Throwable $exception) {
                report($exception);
                $task->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'processed_at' => now(),
                ]);
                $this->error("Tâche #{$task->id} échouée : {$exception->getMessage()}");
            }
        }

        $this->info($tasks->count().' tâche(s) inspectée(s).');

        return self::SUCCESS;
    }

    private function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'pricing_source_stale' => 'Source tarifaire rafraîchie, vérifiez les prix dans les contenus liés.',
            'claim_stale' => 'Claim rafraîchi ou à vérifier.',
            'audit_outdated' => 'Audit pré-publication obsolète.',
            'high_risk_article_refresh' => 'Contenu à risque à réviser.',
            'conversion_review' => 'Page affiliée à optimiser après analyse de conversion.',
            'performance_refresh' => 'Signal Search Console/Bing : contenu à renforcer avec les requêtes visibles.',
            default => str_replace('_', ' ', $reason),
        };
    }
}
