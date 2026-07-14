<?php

namespace Tests\Feature;

use App\Livewire\ContentSchedulerDashboard;
use App\Models\EditorialIdea;
use App\Models\EditorialPlan;
use App\Models\Keyword;
use App\Models\SeoProject;
use App\Models\User;
use App\Services\ContentScheduler;
use App\Services\InternalLinkService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ContentSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_keywords_are_spread_from_monday_to_sunday_without_two_articles_per_day(): void
    {
        Carbon::setTestNow('2026-07-14 07:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Factory Tool', 'slug' => 'factory-tool', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);
        $keywords = collect(range(1, 3))->map(function (int $position) use ($project) {
            return Keyword::query()->create([
                'seo_project_id' => $project->id,
                'keyword' => "mot clé factory {$position}",
                'search_volume' => 100 - $position,
                'keyword_difficulty' => 10 + $position,
                'opportunity_score' => 80 - $position,
            ]);
        });

        $scheduler = app(ContentScheduler::class);
        $schedule = $scheduler->configure($project->id, $admin->id, 5, false);
        $schedule->update(['articles_per_week' => 7]);
        $this->assertSame(30, $scheduler->inventoryTarget($schedule->fresh()));
        $plan = $this->lockedPlan($schedule->id, $project->id, $admin->id, $keywords);
        $scheduler->prepareInventory($schedule);
        $this->assertCount(3, $schedule->tasks()->get());
        $slots = $scheduler->futureSlots($schedule, 7, Carbon::parse('2026-07-13 07:00:00'));
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $slots->map->dayOfWeekIso->all());
        $this->assertSame(7, $slots->map->toDateString()->unique()->count());
        $this->assertTrue($schedule->tasks->every(fn ($task) => $task->editorial_idea_id !== null && $task->editorial_plan_id === $plan->id));

        $scheduledDates = $schedule->tasks()->orderBy('id')->pluck('scheduled_for')->map->toDateTimeString()->all();
        $this->assertSame(3, $scheduler->generateAllNow($schedule));
        $this->assertSame(3, $schedule->tasks()->where('priority', 0)->count());
        $this->assertSame($scheduledDates, $schedule->tasks()->orderBy('id')->pluck('scheduled_for')->map->toDateTimeString()->all());

        $first = $schedule->tasks()->oldest('id')->first();
        $scheduler->moveTask($first, Carbon::parse('2026-07-20'));
        $manualDate = $first->fresh()->scheduled_for->toDateTimeString();
        $newKeyword = Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'nouveau mot clé ajouté plus tard',
            'search_volume' => 150,
            'keyword_difficulty' => 9,
            'opportunity_score' => 92,
        ]);
        $newPlan = $this->lockedPlan($schedule->id, $project->id, $admin->id, collect([$newKeyword]));
        $newPlan->ideas()->update(['title' => 'Déployer un nouveau workflow de prospection']);

        $scheduler->prepareInventory($schedule);
        $this->assertSame($manualDate, $first->fresh()->scheduled_for->toDateTimeString());
        $this->assertDatabaseHas('scheduled_content_tasks', [
            'content_schedule_id' => $schedule->id,
            'keyword_id' => $newKeyword->id,
            'status' => 'queued',
        ]);
    }

    public function test_admin_can_open_the_content_factory_with_live_statuses(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Calendar Tool', 'slug' => 'calendar-tool', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);
        Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel calendrier éditorial',
            'search_volume' => 100,
            'keyword_difficulty' => 18,
            'opportunity_score' => 75,
        ]);
        app(ContentScheduler::class)->configure($project->id, $admin->id, 7, false);

        Livewire::actingAs($admin)->test(ContentSchedulerDashboard::class)
            ->assertSee('Content Factory')
            ->assertSee('Calendrier éditorial')
            ->assertSee('Articles par semaine')
            ->assertSee('Publication automatique')
            ->assertSee('Générer la semaine (7 contenus)');
    }

    public function test_a_young_blog_gets_safe_navigation_fallbacks_for_internal_linking(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'Young Tool', 'slug' => 'young-tool', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);

        $suggestions = app(InternalLinkService::class)->suggestionsForBlueprint($project, [
            'title' => 'Guide Young Tool',
            'primary_keyword' => 'young tool',
            'topic' => 'prise-en-main',
            'intent' => 'informational',
            'angle' => 'configuration',
            'audience' => 'pme',
            'outline' => ['Introduction', 'Configuration', 'Conclusion'],
        ]);

        $this->assertCount(3, $suggestions);
        $this->assertContains(route('blog.index'), $suggestions->pluck('url'));
        $this->assertContains(route('tools.show', $project->slug), $suggestions->pluck('url'));
        $this->assertTrue($suggestions->every(fn (array $suggestion) => filled($suggestion['title']) && filled($suggestion['url'])));
    }

    public function test_duplicate_retained_ideas_are_pruned_before_they_reach_the_writer(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Dedupe Factory', 'slug' => 'dedupe-factory', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);
        $keywords = collect(['revolut pro indépendants', 'banque en ligne pour les professionnels'])->map(fn (string $value) => Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => $value,
            'search_volume' => 100,
            'keyword_difficulty' => 30,
            'opportunity_score' => 70,
        ]));
        $scheduler = app(ContentScheduler::class);
        $schedule = $scheduler->configure($project->id, $admin->id, 7, false);
        $plan = $this->lockedPlan($schedule->id, $project->id, $admin->id, $keywords);
        $ideas = $plan->ideas()->orderBy('id')->get();
        $ideas->each->update(['primary_keyword' => 'banque en ligne pour les professionnels', 'intent' => 'commercial']);
        foreach ($ideas as $index => $idea) {
            $schedule->tasks()->create([
                'seo_project_id' => $project->id,
                'keyword_id' => $idea->keyword_id,
                'editorial_idea_id' => $idea->id,
                'editorial_plan_id' => $plan->id,
                'status' => 'queued',
                'priority' => $index + 1,
                'scheduled_for' => now()->addDays($index + 1),
            ]);
        }

        $this->assertSame(1, $scheduler->pruneScheduledDuplicates($schedule));
        $this->assertSame(1, $schedule->tasks()->count());
        $this->assertSame(1, $plan->ideas()->where('status', 'rejected')->count());
    }

    private function lockedPlan(int $scheduleId, int $projectId, int $userId, $keywords): EditorialPlan
    {
        $plan = EditorialPlan::query()->create([
            'content_schedule_id' => $scheduleId,
            'seo_project_id' => $projectId,
            'user_id' => $userId,
            'name' => 'Plan Content Factory',
            'requested_count' => $keywords->count(),
            'accepted_count' => $keywords->count(),
            'status' => 'locked',
            'locked_at' => now(),
        ]);
        foreach ($keywords->values() as $index => $keyword) {
            $titles = ['Configurer la tarification SaaS', 'Automatiser une migration bancaire', 'Comparer les fonctions de reporting'];
            EditorialIdea::query()->create([
                'editorial_plan_id' => $plan->id,
                'keyword_id' => $keyword->id,
                'title' => $titles[$index] ?? 'Idée éditoriale '.$keyword->keyword,
                'primary_keyword' => $keyword->keyword,
                'entity_key' => 'factory-tool',
                'topic_key' => 'topic-'.$keyword->id,
                'intent' => 'commercial',
                'angle' => 'angle-'.$keyword->id,
                'audience' => 'pme',
                'problem' => 'Choisir une solution.',
                'expected_outcome' => 'Prendre une décision.',
                'funnel_stage' => 'consideration',
                'unique_promise' => 'Répondre précisément au besoin.',
                'excluded_topics' => [],
                'outline' => ['Introduction', 'Critères', 'Conclusion'],
                'fingerprint' => 'factory|'.$keyword->id,
                'content_type' => 'best_tools',
                'status' => 'accepted',
                'seo_score' => 75,
                'position' => $index + 1,
            ]);
        }

        return $plan;
    }
}
