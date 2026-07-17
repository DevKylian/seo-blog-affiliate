<?php

namespace App\Console;

use App\Models\Article;
use App\Services\InternalLinkService;
use App\Services\PrePublishAuditService;
use App\Services\SearchIndexingSubmissionLauncher;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('content:scheduler-tick')
            ->everyMinute()
            ->name('content-factory-scheduler')
            ->withoutOverlapping();

        if (config('services.semrush.seed_expansion_enabled')) {
            $schedule->command('semrush:expand-seeds --cluster=facturation --limit=10')
                ->dailyAt('02:10')
                ->name('semrush-seed-expansion')
                ->withoutOverlapping();
        }

        $schedule->command('content:plan-refresh --limit=100')
            ->dailyAt('03:20')
            ->name('content-quality-refresh-planner')
            ->withoutOverlapping();

        $schedule->command('seo:intelligence --days=28')
            ->dailyAt('04:10')
            ->name('seo-intelligence-loop')
            ->withoutOverlapping();

        $schedule->command('content:process-refresh --limit=3')
            ->everyThirtyMinutes()
            ->name('content-quality-refresh-worker')
            ->withoutOverlapping();

        $schedule->call(function (): void {
            $dueArticles = Article::query()->where('status', 'scheduled')->where('scheduled_at', '<=', now())->get();
            $publishable = collect();
            $audits = app(PrePublishAuditService::class);

            foreach ($dueArticles as $article) {
                $audit = $audits->audit($article, ['auto_publish' => true]);
                if ($audit->status === 'blocked') {
                    $article->update([
                        'status' => 'review',
                        'published_at' => null,
                        'refresh_status' => 'needs_review',
                        'refresh_reason' => 'Publication programmée bloquée par l’audit pré-publication.',
                    ]);

                    continue;
                }
                $publishable->push($article);
            }

            if ($publishable->isEmpty()) {
                return;
            }

            $projectIds = $publishable->pluck('seo_project_id')->filter()->unique();
            Article::query()->whereKey($publishable->pluck('id'))->update([
                'status' => 'published',
                'published_at' => now(),
                'scheduled_at' => null,
            ]);
            $projectIds->each(fn ($projectId) => app(InternalLinkService::class)->refreshProject((int) $projectId));
            $publishable->each(function (Article $article): void {
                try {
                    app(SearchIndexingSubmissionLauncher::class)->launch($article->id);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
        })->everyMinute()->name('publish-scheduled-articles')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
