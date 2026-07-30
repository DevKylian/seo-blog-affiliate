<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\ContentRun;
use App\Models\ContentSchedule;
use App\Models\EditorialIdea;
use App\Models\EditorialPlan;
use App\Models\ScheduledContentTask;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ContentScheduler
{
    public function __construct(
        private readonly EditorialPlanBuilder $plans,
        private readonly EditorialPlanWorkerLauncher $planWorkers,
        private readonly ContentRunWorkerLauncher $runWorkers,
        private readonly InternalLinkService $internalLinks,
        private readonly EditorialDuplicateDetector $duplicates,
        private readonly SemanticKeywordClusterer $clusters,
        private readonly SearchIndexingSubmissionLauncher $indexing,
        private readonly PrePublishAuditService $audits,
    ) {}

    public function configure(int $projectId, ?int $userId, int $articlesPerWeek, bool $autoPublish, string $instructions = ''): ContentSchedule
    {
        return ContentSchedule::query()->updateOrCreate(
            ['seo_project_id' => $projectId],
            [
                'user_id' => $userId,
                // Un rythme hebdomadaire ne peut jamais créer deux contenus le
                // même jour : sept jours calendaires, donc sept articles max.
                'articles_per_week' => max(1, min(7, $articlesPerWeek)),
                'auto_publish' => $autoPublish,
                'is_active' => true,
                'timezone' => config('app.timezone', 'Europe/Paris'),
                'instructions' => trim($instructions) ?: null,
            ],
        );
    }

    /**
     * Construit d'abord un vrai lot d'idées éditoriales. Aucun mot-clé brut
     * ne peut être programmé ou envoyé au rédacteur par cette méthode.
     */
    public function prepareInventory(ContentSchedule $schedule, bool $force = false): ?EditorialPlan
    {
        $this->synchronizeEditorialPlans($schedule);
        $this->pruneScheduledDuplicates($schedule);

        $planning = $schedule->editorialPlans()->where('status', 'planning')->latest('id')->first();
        if ($planning) {
            if ($planning->updated_at->lt(now()->subMinutes(2))) {
                $this->planWorkers->launch($planning->id);
            }

            return $planning;
        }

        $target = $this->inventoryTarget($schedule);
        $inventory = $schedule->tasks()->whereIn('status', ['queued', 'generating', 'retrying'])->count();
        if (! $force && $inventory >= $target) {
            return null;
        }

        $availableClusters = $this->clusters->queueableClusters($schedule, max(150, $target));
        if ($availableClusters->isEmpty()) {
            return null;
        }

        $requested = min(30, $availableClusters->count(), $force ? $target : max(1, $target - $inventory));
        $selectedClusters = $availableClusters->take($requested)->values();
        $plan = $this->plans->createPlan(
            $schedule->project,
            $schedule->user_id,
            $requested,
            (string) $schedule->instructions,
            $selectedClusters->pluck('canonical_keyword_id')->filter()->map(fn ($id) => (int) $id)->all(),
            $selectedClusters->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );
        $plan->update(['content_schedule_id' => $schedule->id]);
        $this->planWorkers->launch($plan->id);

        return $plan;
    }

    public function pruneScheduledDuplicates(ContentSchedule $schedule): int
    {
        $tasks = $schedule->tasks()
            ->whereIn('status', ['queued', 'retrying'])
            ->whereNotNull('editorial_idea_id')
            ->with('editorialIdea')
            ->get()
            ->sortByDesc(fn (ScheduledContentTask $task) => (float) $task->editorialIdea?->seo_score)
            ->values();
        $kept = collect();
        $removed = 0;
        $planIds = collect();

        foreach ($tasks as $task) {
            $idea = $task->editorialIdea;
            if (! $idea) {
                $task->delete();
                $removed++;

                continue;
            }
            $duplicate = $kept->first(fn (EditorialIdea $existing) => $this->ideasOverlap($idea, $existing));
            if (! $duplicate) {
                $kept->push($idea);

                continue;
            }

            $planIds->push($idea->editorial_plan_id);
            $idea->update([
                'status' => 'rejected',
                'rejection_reason' => 'Doublon retiré automatiquement avant programmation avec « '.$duplicate->title.' ».',
            ]);
            $task->delete();
            $removed++;
        }

        $planIds->unique()->each(function (int $planId): void {
            $plan = EditorialPlan::query()->find($planId);
            if ($plan) {
                $plan->update(['accepted_count' => $plan->ideas()->where('status', 'accepted')->count()]);
            }
        });

        return $removed;
    }

    public function inventoryTarget(ContentSchedule $schedule): int
    {
        return max((int) $schedule->articles_per_week, (int) ceil(((int) $schedule->articles_per_week * 30) / 7));
    }

    /** @return array{scheduled:int, plan:?EditorialPlan} */
    public function generateBatch(ContentSchedule $schedule, ?int $count = null): array
    {
        $this->synchronizeEditorialPlans($schedule);
        $count = max(1, min(7, $count ?? (int) $schedule->articles_per_week));
        $tasks = $schedule->tasks()
            ->whereIn('status', ['queued', 'retrying'])
            ->whereNotNull('editorial_idea_id')
            ->orderBy('priority')
            ->orderBy('scheduled_for')
            ->limit($count)
            ->get();
        $plan = null;
        if ($tasks->count() < $count) {
            $plan = $this->prepareInventory($schedule, true);
        }

        $slots = $this->futureSlots($schedule, $tasks->count());
        foreach ($tasks as $index => $task) {
            $task->update([
                'scheduled_for' => $slots[$index],
                'retry_at' => null,
                'priority' => $index + 1,
            ]);
        }

        return ['scheduled' => $tasks->count(), 'plan' => $plan];
    }

    public function redistributeQueuedTasks(ContentSchedule $schedule): void
    {
        $tasks = $schedule->tasks()
            ->whereIn('status', ['queued', 'retrying'])
            ->whereNotNull('editorial_idea_id')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
        $slots = $this->futureSlots($schedule, $tasks->count());

        foreach ($tasks as $index => $task) {
            $task->update(['scheduled_for' => $slots[$index], 'priority' => 100 + $index]);
        }
    }

    /** @return Collection<int, Carbon> */
    public function futureSlots(ContentSchedule $schedule, int $count, ?Carbon $from = null): Collection
    {
        $from ??= now()->addMinute();
        $rate = max(1, min(7, (int) $schedule->articles_per_week));
        $week = $from->copy()->startOfWeek();
        $slots = collect();
        $maximumWeeks = max(104, (int) ceil($count / $rate) + 4);

        for ($weekOffset = 0; $slots->count() < $count && $weekOffset < $maximumWeeks; $weekOffset++) {
            $weekSlots = collect();
            for ($position = 0; $position < $rate; $position++) {
                // La semaine éditoriale va du lundi au dimanche. À 7/semaine,
                // chaque jour reçoit exactement un créneau à 08:00.
                $slot = $week->copy()->addWeeks($weekOffset)->addDays($position)->setTime(8, 0);
                if ($slot->gte($from)) {
                    $weekSlots->push($slot);
                }
            }
            $slots = $slots->concat($weekSlots->sort()->values());
        }

        return $slots->take($count)->values();
    }

    public function moveTask(ScheduledContentTask $task, Carbon $date): void
    {
        if (! in_array($task->status, ['queued', 'retrying'], true)) {
            throw new RuntimeException('Seuls les contenus en attente peuvent être déplacés.');
        }
        $dateAlreadyOccupied = $task->schedule->tasks()
            ->whereKeyNot($task->id)
            ->whereNotIn('status', ['failed', 'cancelled'])
            ->whereDate('scheduled_for', $date->toDateString())
            ->exists();
        if ($dateAlreadyOccupied) {
            throw new RuntimeException('Cette journée contient déjà un article. Choisissez un autre jour.');
        }
        $time = $task->scheduled_for?->format('H:i') ?: '09:00';
        $task->update(['scheduled_for' => $date->copy()->setTimeFromTimeString($time), 'priority' => 1]);
    }

    public function prioritize(ScheduledContentTask $task): void
    {
        if (! in_array($task->status, ['queued', 'retrying'], true)) {
            return;
        }
        $slot = $this->availableSlots($task->schedule, 1)->first() ?? now()->addDay()->setTime(8, 0);
        $task->update(['scheduled_for' => $slot, 'retry_at' => null, 'priority' => 1]);
    }

    /**
     * Rend un contenu immédiatement éligible sans modifier sa date dans le
     * calendrier. Le worker le prendra dès que la génération active se termine.
     */
    public function generateNow(ScheduledContentTask $task): bool
    {
        if (! in_array($task->status, ['queued', 'retrying'], true)) {
            return false;
        }

        $changes = ['priority' => 0];
        if ($task->status === 'retrying') {
            $changes['retry_at'] = now();
        }
        $task->update($changes);

        return true;
    }

    /** Marque toute la file existante pour une production immédiate et séquentielle. */
    public function generateAllNow(ContentSchedule $schedule): int
    {
        $queued = $schedule->tasks()->where('status', 'queued')->get();
        $retrying = $schedule->tasks()->where('status', 'retrying')->get();

        $queued->each(fn (ScheduledContentTask $task) => $task->update(['priority' => 0]));
        $retrying->each(fn (ScheduledContentTask $task) => $task->update([
            'priority' => 0,
            'retry_at' => now(),
        ]));

        return $queued->count() + $retrying->count();
    }

    public function tick(): array
    {
        $lock = Cache::lock('content-scheduler-tick', 55);
        if (! $lock->get()) {
            return ['state' => 'locked', 'message' => 'Un autre ordonnanceur est actif.'];
        }

        try {
            ContentSchedule::query()->where('is_active', true)->with('project')->each(
                fn (ContentSchedule $schedule) => $this->prepareInventory($schedule),
            );
            $this->synchronizeActiveTasks();

            if (ScheduledContentTask::query()->where('status', 'generating')->exists()) {
                return ['state' => 'busy', 'message' => 'Une production planifiée est déjà active.'];
            }

            $scheduledRunIds = ScheduledContentTask::query()->whereNotNull('content_run_id')->pluck('content_run_id');
            if (ContentRun::query()->whereIn('status', ['pending', 'processing'])->whereNotIn('id', $scheduledRunIds)->exists()) {
                return ['state' => 'busy', 'message' => 'Une campagne manuelle est active ; la production planifiée attend son tour.'];
            }

            $task = ScheduledContentTask::query()
                ->whereNotNull('editorial_idea_id')
                ->whereHas('schedule', fn ($query) => $query->where('is_active', true))
                ->where(function ($query): void {
                    $query->where(fn ($queued) => $queued->where('status', 'queued')->where(function ($due): void {
                        $due->where('scheduled_for', '<=', now())->orWhere('priority', 0);
                    }))
                        ->orWhere(fn ($retry) => $retry->where('status', 'retrying')->where('retry_at', '<=', now()));
                })
                ->with(['schedule.project', 'editorialIdea.plan', 'keyword', 'contentCluster', 'run.items'])
                ->orderBy('priority')
                ->orderBy('scheduled_for')
                ->first();
            if (! $task) {
                return ['state' => 'idle', 'message' => 'Aucun contenu éditorial arrivé à échéance.'];
            }

            try {
                if ($task->status === 'retrying' && $task->run) {
                    $this->retryRun($task);
                } else {
                    $this->launchRun($task);
                }
            } catch (Throwable $exception) {
                $message = $exception->getMessage();
                $task->update($this->isTransient($message)
                    ? ['status' => 'retrying', 'retry_at' => now()->addMinutes($this->retryDelayMinutes($task)), 'error_message' => $message]
                    : ['status' => 'failed', 'error_message' => $message, 'completed_at' => now()]);
                $task->contentCluster?->update(['status' => $this->isTransient($message) ? 'retrying' : 'failed']);
            }

            return ['state' => $task->fresh()->status, 'message' => "Tâche #{$task->id} prise en charge."];
        } finally {
            $lock->release();
        }
    }

    private function synchronizeEditorialPlans(ContentSchedule $schedule): void
    {
        $schedule->editorialPlans()->where('status', 'locked')->with(['ideas.keyword.contentCluster', 'ideas.contentCluster'])->each(
            fn (EditorialPlan $plan) => $this->materializePlan($schedule, $plan),
        );
    }

    private function materializePlan(ContentSchedule $schedule, EditorialPlan $plan): void
    {
        $ideas = $plan->ideas()
            ->where('status', 'accepted')
            ->whereDoesntHave('scheduledTasks', fn ($query) => $query->where('content_schedule_id', $schedule->id))
            ->with(['keyword.contentCluster', 'contentCluster'])
            ->orderBy('position')
            ->get();
        if ($ideas->isEmpty()) {
            return;
        }

        $ideas = $this->factoryIdeaSequence($ideas);
        $slots = $this->availableSlots($schedule, $ideas->count());
        $basePriority = $schedule->tasks()->whereIn('status', ['queued', 'retrying'])->max('priority') ?? 99;
        $created = 0;
        foreach ($ideas as $idea) {
            $clusterId = $idea->content_cluster_id ?: $idea->keyword?->content_cluster_id;
            if ($clusterId && $this->clusterAlreadyQueued($schedule, (int) $clusterId)) {
                continue;
            }

            $schedule->tasks()->create([
                'seo_project_id' => $schedule->seo_project_id,
                'keyword_id' => $idea->keyword_id,
                'content_cluster_id' => $clusterId,
                'editorial_idea_id' => $idea->id,
                'editorial_plan_id' => $plan->id,
                'status' => 'queued',
                'priority' => $basePriority + $created + 1,
                'scheduled_for' => $slots[$created] ?? now()->addDays($created + 1)->setTime(8, 0),
            ]);
            if ($clusterId) {
                ContentCluster::query()->whereKey($clusterId)->update(['status' => 'scheduled']);
            }
            $created++;
        }
    }

    /** @return Collection<int, Carbon> */
    private function availableSlots(ContentSchedule $schedule, int $count): Collection
    {
        $occupied = $schedule->tasks()
            ->whereIn('status', ['queued', 'generating', 'retrying'])
            ->where('scheduled_for', '>=', now())
            ->pluck('scheduled_for')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d H:i'))
            ->flip();

        return $this->futureSlots($schedule, $count + $occupied->count() + 100)
            ->reject(fn (Carbon $slot) => $occupied->has($slot->format('Y-m-d H:i')))
            ->take($count)
            ->values();
    }

    private function synchronizeActiveTasks(): void
    {
        ScheduledContentTask::query()->where('status', 'generating')->with([
            'run.items.article', 'run.items.editorialIdea', 'schedule', 'editorialPlan', 'contentCluster',
        ])->each(function (ScheduledContentTask $task): void {
            $run = $task->run;
            if (! $run) {
                $task->update(['status' => 'failed', 'error_message' => 'Campagne de génération introuvable.', 'completed_at' => now()]);

                $task->contentCluster?->update(['status' => 'failed']);

                return;
            }
            if (in_array($run->status, ['pending', 'processing'], true)) {
                if ($run->updated_at->lt(now()->subMinutes(3))) {
                    $this->runWorkers->launch($run->id);
                }

                return;
            }

            $item = $run->items()->whereNotNull('article_id')->with(['article', 'editorialIdea'])->first();
            if ($item?->article instanceof Article) {
                $task->update([
                    'editorial_idea_id' => $item->editorial_idea_id,
                    'keyword_id' => $item->keyword_id,
                ]);
                $this->completeTask($task, $item->article);

                return;
            }

            $error = (string) $run->items()->where('status', 'failed')->value('error_message');
            if ($this->isTransient($error)) {
                $task->update(['status' => 'retrying', 'retry_at' => now()->addMinutes($this->retryDelayMinutes($task)), 'error_message' => $error]);
                $task->contentCluster?->update(['status' => 'retrying']);
            } else {
                $task->update(['status' => 'failed', 'error_message' => $error ?: 'La génération a échoué sans produire d’article.', 'completed_at' => now()]);
                $task->contentCluster?->update(['status' => 'failed']);
                $this->finalizePlanIfFinished($task->editorialPlan);
            }
        });
    }

    private function launchRun(ScheduledContentTask $task): void
    {
        $idea = $task->editorialIdea;
        $plan = $idea?->plan;
        if (! $idea || ! $plan) {
            throw new RuntimeException('Brief éditorial introuvable : le mot-clé brut ne sera pas généré.');
        }

        $run = DB::transaction(function () use ($plan, $idea): ContentRun {
            $run = ContentRun::query()->create([
                'seo_project_id' => $plan->seo_project_id,
                'user_id' => $plan->user_id,
                'editorial_plan_id' => $plan->id,
                'name' => 'Production planifiée — '.$idea->title,
                'requested_count' => 1,
                'status' => 'pending',
                'instructions' => $plan->instructions,
            ]);
            $run->items()->create([
                'editorial_idea_id' => $idea->id,
                'keyword_id' => $idea->keyword_id,
                'content_type' => $idea->content_type,
                'status' => 'pending',
            ]);

            return $run;
        });

        $task->update([
            'content_run_id' => $run->id,
            'status' => 'generating',
            'attempts' => $task->attempts + 1,
            'started_at' => $task->started_at ?? now(),
            'retry_at' => null,
            'error_message' => null,
        ]);
        $task->contentCluster?->update(['status' => 'generating']);
        $task->schedule->update(['last_dispatched_at' => now()]);
        $this->runWorkers->launch($run->id);
    }

    private function retryRun(ScheduledContentTask $task): void
    {
        $run = $task->run;
        DB::transaction(function () use ($run): void {
            $run->items()->where('status', 'failed')->update([
                'status' => 'pending', 'error_message' => null, 'api_attempts' => 0,
                'started_at' => null, 'completed_at' => null,
            ]);
            $run->update(['status' => 'pending', 'failed_count' => 0, 'completed_at' => null]);
        });
        $task->update(['status' => 'generating', 'retry_at' => null, 'attempts' => $task->attempts + 1, 'error_message' => null]);
        $task->contentCluster?->update(['status' => 'generating']);
        $this->runWorkers->launch($run->id);
    }

    private function completeTask(ScheduledContentTask $task, Article $article): void
    {
        if ($task->content_cluster_id && ! $article->content_cluster_id) {
            $article->update(['content_cluster_id' => $task->content_cluster_id]);
        }
        $shouldPublish = $task->schedule->auto_publish || ($task->scheduled_for && $task->scheduled_for->isPast());

        if ($shouldPublish) {
            try {
                $this->audits->audit($article, ['auto_publish' => true]);
            } catch (Throwable $e) {
                report($e);
            }
            $article->update(['status' => 'published', 'published_at' => now(), 'scheduled_at' => null]);
            $this->internalLinks->refreshProject($article->seo_project_id);
            try {
                $this->indexing->launch($article->id);
            } catch (Throwable $exception) {
                report($exception);
            }
            $status = 'published';
        } else {
            $status = 'review';
        }
        $task->update([
            'article_id' => $article->id,
            'status' => $status,
            'error_message' => null,
            'retry_at' => null,
            'completed_at' => now(),
        ]);
        $task->contentCluster?->update(['status' => $status]);
        $this->finalizePlanIfFinished($task->editorialPlan);
    }

    private function finalizePlanIfFinished(?EditorialPlan $plan): void
    {
        if (! $plan || $plan->scheduledTasks()->whereIn('status', ['queued', 'generating', 'retrying'])->exists()) {
            return;
        }
        $plan->update(['status' => 'completed']);
    }

    private function ideasOverlap(EditorialIdea $candidate, EditorialIdea $existing): bool
    {
        $sameKeyword = mb_strtolower(trim($candidate->primary_keyword)) === mb_strtolower(trim($existing->primary_keyword));
        $sameIntent = mb_strtolower($candidate->intent) === mb_strtolower($existing->intent);
        if ($sameKeyword && $sameIntent) {
            return true;
        }

        similar_text(Str::slug($candidate->title), Str::slug($existing->title), $titleSimilarity);
        if ($sameIntent && $titleSimilarity >= 78) {
            return true;
        }

        $candidateBlueprint = $this->duplicates->normalizeBlueprint($candidate->blueprint());
        $existingBlueprint = $this->duplicates->normalizeBlueprint($existing->blueprint());

        return $candidateBlueprint['topic'] === $existingBlueprint['topic']
            && $candidateBlueprint['intent'] === $existingBlueprint['intent']
            && $this->duplicates->compareBlueprints($candidateBlueprint, $existingBlueprint) >= 70;
    }

    private function isTransient(string $message): bool
    {
        $message = mb_strtolower($message);

        return str_contains($message, '503')
            || str_contains($message, '429')
            || str_contains($message, 'high demand')
            || str_contains($message, 'high traffic')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'resource exhausted')
            || str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'curl error 28');
    }

    /** @param Collection<int, EditorialIdea> $ideas */
    private function factoryIdeaSequence(Collection $ideas): Collection
    {
        $ranked = $ideas
            ->sortByDesc(fn (EditorialIdea $idea): float => (float) $idea->seo_score + match ($idea->contentCluster?->type ?? $idea->keyword?->contentCluster?->type) {
                'pillar' => 18,
                'niche' => 12,
                default => 0,
            })
            ->values();
        $pillars = $ranked->filter(fn (EditorialIdea $idea): bool => ($idea->contentCluster?->type ?? $idea->keyword?->contentCluster?->type) === 'pillar')->values();
        $niches = $ranked->filter(fn (EditorialIdea $idea): bool => ($idea->contentCluster?->type ?? $idea->keyword?->contentCluster?->type) === 'niche')->values();
        $supporting = $ranked->reject(fn (EditorialIdea $idea): bool => in_array($idea->contentCluster?->type ?? $idea->keyword?->contentCluster?->type, ['pillar', 'niche'], true))->values();
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
            if ($supporting->isNotEmpty()) {
                $sequence->push($supporting->shift());
            }
        }

        return $sequence->unique('id')->values();
    }

    private function clusterAlreadyQueued(ContentSchedule $schedule, int $clusterId): bool
    {
        return $schedule->tasks()
            ->where('content_cluster_id', $clusterId)
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->exists();
    }

    private function retryDelayMinutes(ScheduledContentTask $task): int
    {
        $attempt = max(1, (int) $task->attempts);

        return min(240, 5 * (2 ** min(5, $attempt - 1)));
    }
}
