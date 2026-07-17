<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentRun;
use App\Models\ContentSchedule;
use App\Models\EditorialPlan;
use App\Models\ScheduledContentTask;
use Illuminate\Support\Facades\DB;

final class DevGenerationCircuitBreaker
{
    private const STOP_MESSAGE = 'Génération débranchée en local pour éviter tout appel API caché.';

    /**
     * @return array{runs:int,items:int,schedules:int,tasks:int,plans:int,articles:int}
     */
    public function stopAll(): array
    {
        return DB::transaction(function (): array {
            $now = now();
            $result = [
                'runs' => 0,
                'items' => 0,
                'schedules' => 0,
                'tasks' => 0,
                'plans' => 0,
                'articles' => 0,
            ];

            $result['schedules'] = ContentSchedule::query()
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => $now]);

            $planningPlans = EditorialPlan::query()->where('status', 'planning')->get();
            $result['plans'] = $planningPlans->count();
            $planningPlans->each(fn (EditorialPlan $plan) => $plan->update(['status' => 'failed']));

            $scheduledTasks = ScheduledContentTask::query()
                ->whereIn('status', ['queued', 'retrying', 'generating', 'planning']);
            $result['tasks'] = (clone $scheduledTasks)->count();
            $scheduledTasks->update([
                'status' => 'cancelled',
                'error_message' => self::STOP_MESSAGE,
                'retry_at' => null,
                'completed_at' => $now,
                'updated_at' => $now,
            ]);

            $runs = ContentRun::query()
                ->whereIn('status', ['pending', 'processing'])
                ->with([
                    'editorialPlan',
                    'items' => fn ($query) => $query
                        ->whereIn('status', ['pending', 'processing'])
                        ->with('editorialIdea'),
                ])
                ->get();
            $result['runs'] = $runs->count();

            foreach ($runs as $run) {
                foreach ($run->items as $item) {
                    $item->update([
                        'status' => 'failed',
                        'error_message' => self::STOP_MESSAGE,
                        'started_at' => null,
                        'completed_at' => $now,
                    ]);
                    $result['items']++;

                    if ($item->editorialIdea?->status === 'generating') {
                        $item->editorialIdea->update(['status' => 'accepted']);
                    }
                }

                $run->update([
                    'status' => 'completed_with_errors',
                    'failed_count' => $run->items()->where('status', 'failed')->count(),
                    'completed_at' => $now,
                ]);

                if ($run->editorialPlan?->status === 'generating') {
                    $run->editorialPlan->update(['status' => 'locked']);
                }
            }

            $articles = Article::query()
                ->where(function ($query): void {
                    $query->where('quality_checks->regeneration_status', 'queued')
                        ->orWhere('quality_checks->regeneration_status', 'processing');
                })
                ->get();
            $result['articles'] = $articles->count();
            $articles->each(function (Article $article) use ($now): void {
                $article->update([
                    'quality_checks' => array_merge($article->quality_checks ?? [], [
                        'regeneration_status' => 'cancelled',
                        'regeneration_finished_at' => $now->toDateTimeString(),
                        'regeneration_error' => self::STOP_MESSAGE,
                    ]),
                ]);
            });

            return $result;
        });
    }
}
