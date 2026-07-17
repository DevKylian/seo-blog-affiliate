<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentRun;
use App\Models\ContentSchedule;
use App\Models\EditorialIdea;
use App\Models\EditorialPlan;
use App\Models\ScheduledContentTask;
use App\Models\SeoProject;
use App\Models\User;
use App\Services\DevGenerationCircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DevGenerationCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stops_active_runs_plans_schedules_and_queued_tasks(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Kill Switch Tool',
            'slug' => 'kill-switch-tool',
            'website_url' => 'https://example.com',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        $schedule = ContentSchedule::query()->create([
            'seo_project_id' => $project->id,
            'user_id' => $admin->id,
            'articles_per_week' => 5,
            'is_active' => true,
        ]);
        $planningPlan = EditorialPlan::query()->create([
            'seo_project_id' => $project->id,
            'content_schedule_id' => $schedule->id,
            'user_id' => $admin->id,
            'name' => 'Planning en cours',
            'requested_count' => 3,
            'status' => 'planning',
        ]);
        $runPlan = EditorialPlan::query()->create([
            'seo_project_id' => $project->id,
            'user_id' => $admin->id,
            'name' => 'Generation en cours',
            'requested_count' => 2,
            'status' => 'generating',
        ]);
        $generatingIdea = $this->idea($runPlan, 'generating', 1);
        $pendingIdea = $this->idea($runPlan, 'accepted', 2);
        $queuedIdea = $this->idea($planningPlan, 'accepted', 3);
        $retryingIdea = $this->idea($planningPlan, 'accepted', 4);
        $scheduledGeneratingIdea = $this->idea($planningPlan, 'accepted', 5);

        $run = ContentRun::query()->create([
            'seo_project_id' => $project->id,
            'user_id' => $admin->id,
            'editorial_plan_id' => $runPlan->id,
            'name' => 'Campagne active',
            'requested_count' => 2,
            'status' => 'processing',
            'started_at' => now(),
        ]);
        $run->items()->create([
            'editorial_idea_id' => $generatingIdea->id,
            'content_type' => 'informational',
            'status' => 'processing',
            'started_at' => now(),
        ]);
        $run->items()->create([
            'editorial_idea_id' => $pendingIdea->id,
            'content_type' => 'informational',
            'status' => 'pending',
        ]);

        foreach ([['queued', $queuedIdea], ['retrying', $retryingIdea], ['generating', $scheduledGeneratingIdea]] as [$status, $idea]) {
            ScheduledContentTask::query()->create([
                'content_schedule_id' => $schedule->id,
                'seo_project_id' => $project->id,
                'editorial_idea_id' => $idea->id,
                'editorial_plan_id' => $planningPlan->id,
                'content_run_id' => $status === 'generating' ? $run->id : null,
                'status' => $status,
                'scheduled_for' => now(),
                'retry_at' => $status === 'retrying' ? now() : null,
            ]);
        }
        $regeneratingArticle = Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Article en regeneration',
            'slug' => 'article-en-regeneration',
            'status' => 'review',
            'body' => 'Ancien contenu.',
            'quality_checks' => ['regeneration_status' => 'queued'],
        ]);

        $result = app(DevGenerationCircuitBreaker::class)->stopAll();

        $this->assertSame(['runs' => 1, 'items' => 2, 'schedules' => 1, 'tasks' => 3, 'plans' => 1, 'articles' => 1], $result);
        $this->assertFalse($schedule->fresh()->is_active);
        $this->assertSame('failed', $planningPlan->fresh()->status);
        $this->assertSame('locked', $runPlan->fresh()->status);
        $this->assertSame('completed_with_errors', $run->fresh()->status);
        $this->assertSame(2, $run->fresh()->failed_count);
        $this->assertSame(2, $run->items()->where('status', 'failed')->count());
        $this->assertSame('accepted', $generatingIdea->fresh()->status);
        $this->assertSame(3, ScheduledContentTask::query()->where('status', 'cancelled')->count());
        $this->assertSame(0, ScheduledContentTask::query()->whereIn('status', ['queued', 'retrying', 'generating'])->count());
        $this->assertSame('cancelled', $regeneratingArticle->fresh()->quality_checks['regeneration_status']);
    }

    public function test_local_admin_can_see_and_use_the_dev_stop_button(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Couper les générations');

        $this->actingAs($admin)
            ->withSession(['_token' => 'test-token'])
            ->post(route('admin.dev.stop-generations'), ['_token' => 'test-token'])
            ->assertRedirect()
            ->assertSessionHas('dev_generation_stop');
    }

    private function idea(EditorialPlan $plan, string $status, int $position): EditorialIdea
    {
        return EditorialIdea::query()->create([
            'editorial_plan_id' => $plan->id,
            'title' => "Sujet {$position}",
            'primary_keyword' => "mot cle {$position}",
            'entity_key' => 'kill-switch-tool',
            'topic_key' => "topic-{$position}",
            'intent' => 'informational',
            'angle' => "angle-{$position}",
            'audience' => 'pme',
            'problem' => 'Eviter les couts caches.',
            'expected_outcome' => 'Generation stoppee.',
            'funnel_stage' => 'tofu',
            'unique_promise' => 'Stopper les appels API invisibles.',
            'excluded_topics' => [],
            'outline' => ['Contexte', 'Action', 'Verification'],
            'fingerprint' => "kill-switch-tool|topic-{$position}|informational|pme|angle-{$position}",
            'content_type' => 'informational',
            'status' => $status,
            'position' => $position,
        ]);
    }
}
