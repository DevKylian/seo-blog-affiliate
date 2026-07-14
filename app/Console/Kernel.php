<?php

namespace App\Console;

use App\Models\Article;
use App\Services\InternalLinkService;
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

        $schedule->call(function (): void {
            $due = Article::query()->where('status', 'scheduled')->where('scheduled_at', '<=', now());
            $projectIds = (clone $due)->pluck('seo_project_id')->filter()->unique();
            $due->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
            $projectIds->each(fn ($projectId) => app(InternalLinkService::class)->refreshProject((int) $projectId));
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
