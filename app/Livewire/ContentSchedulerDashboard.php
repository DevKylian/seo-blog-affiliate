<?php

namespace App\Livewire;

use App\Models\ContentSchedule;
use App\Models\ScheduledContentTask;
use App\Models\SeoProject;
use App\Models\Setting;
use App\Services\ContentScheduler;
use App\Services\ContentSchedulerWorkerLauncher;
use App\Services\SemrushCsvImporter;
use App\Services\SemrushSeedExpansionEngine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.admin')]
final class ContentSchedulerDashboard extends Component
{
    use WithFileUploads;

    public ?int $projectId = null;

    public $csv;

    public string $pastedKeywords = '';

    public int $articlesPerWeek = 5;

    public bool $autoPublish = false;

    public bool $isActive = true;

    public string $instructions = '';

    public string $month = '';

    public string $message = '';

    public string $error = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->projectId = ContentSchedule::query()->latest('updated_at')->value('seo_project_id')
            ?? SeoProject::query()->whereHas('keywords')->latest('id')->value('id')
            ?? SeoProject::query()->latest('id')->value('id');
        $this->loadSchedule();
    }

    public function updatedProjectId(): void
    {
        $this->loadSchedule();
        $this->resetMessages();
    }

    public function saveFactory(ContentScheduler $scheduler, ContentSchedulerWorkerLauncher $worker, SemrushCsvImporter $importer): void
    {
        $this->validate([
            'projectId' => ['required', 'exists:seo_projects,id'],
            'articlesPerWeek' => ['required', 'integer', 'min:1', 'max:7'],
            'csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:10240'],
            'pastedKeywords' => ['nullable', 'string', 'max:1500000'],
            'instructions' => ['nullable', 'string', 'max:10000'],
        ]);
        $this->resetMessages();

        try {
            if (! Setting::value('gemini_api_key', config('services.gemini.key'))) {
                throw new \RuntimeException('Configurez d’abord la clé Gemini dans les réglages.');
            }
            $project = SeoProject::query()->findOrFail($this->projectId);
            $imported = 0;
            if ($this->csv) {
                $imported += $importer->import($project, $this->csv->getRealPath());
                $this->reset('csv');
            }
            if (trim($this->pastedKeywords) !== '') {
                $imported += $importer->importText($project, $this->pastedKeywords);
                $this->pastedKeywords = '';
            }
            if (! $project->keywords()->exists()) {
                throw new \RuntimeException('Ajoutez au moins un mot-clé Semrush avant d’activer l’usine.');
            }

            $previous = ContentSchedule::query()->where('seo_project_id', $project->id)->first();
            $rateChanged = $previous && (int) $previous->articles_per_week !== $this->articlesPerWeek;
            $schedule = $scheduler->configure($project->id, auth()->id(), $this->articlesPerWeek, $this->autoPublish, $this->instructions);
            if ($rateChanged) {
                $scheduler->redistributeQueuedTasks($schedule);
            }
            $plan = $scheduler->prepareInventory($schedule, $imported > 0 || ! $previous);
            $worker->launch($schedule->id);

            $this->isActive = true;
            $details = collect([
                $imported > 0 ? "{$imported} mot(s)-clé(s) importé(s)" : null,
                $plan ? "planification de {$plan->requested_count} idée(s) éditoriale(s) lancée" : null,
            ])->filter()->implode(' · ');
            $this->message = 'Usine à contenu activée'.($details ? ' — '.$details : '. La file existante est à jour.');
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function toggleFactory(ContentSchedulerWorkerLauncher $worker): void
    {
        $schedule = $this->schedule();
        if (! $schedule) {
            $this->error = 'Enregistrez d’abord la configuration de production.';

            return;
        }
        $nextState = ! $schedule->is_active;
        $schedule->update(['is_active' => $nextState]);
        if ($nextState) {
            $worker->launch($schedule->id);
        }
        $this->isActive = $nextState;
        $this->message = $nextState ? 'Production automatique reprise.' : 'Production mise en pause. Les contenus déjà générés sont conservés.';
        $this->error = '';
    }

    public function generateWeek(ContentScheduler $scheduler, ContentSchedulerWorkerLauncher $worker): void
    {
        $schedule = $this->schedule();
        if (! $schedule) {
            $this->error = 'Activez d’abord la Content Factory.';

            return;
        }

        try {
            $result = $scheduler->generateBatch($schedule, (int) $schedule->articles_per_week);
            $worker->launch($schedule->id);
            $this->error = '';
            $this->message = $result['scheduled'] > 0
                ? "Les {$result['scheduled']} prochains contenus retenus remplissent désormais les prochains créneaux."
                : 'Le prochain lot d’idées est en cours de préparation et remplira automatiquement la semaine.';
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function expandFacturationSeeds(SemrushSeedExpansionEngine $engine, ContentScheduler $scheduler): void
    {
        if (! $this->projectId) {
            $this->error = 'Selectionnez un projet avant d analyser Semrush.';

            return;
        }

        try {
            $project = SeoProject::query()->findOrFail($this->projectId);
            $stats = $engine->runForProject($project, 'facturation', 10);

            if ($schedule = $this->schedule()) {
                $scheduler->prepareInventory($schedule, $stats['imported'] > 0);
            }

            $this->error = '';
            $this->message = $stats['imported'] > 0
                ? "{$stats['imported']} mot(s)-cles Facturation ajoutes depuis Semrush."
                : 'Analyse Semrush terminee, aucun nouveau mot-cle exploitable ajoute.';
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function generateAllNow(ContentScheduler $scheduler, ContentSchedulerWorkerLauncher $worker): void
    {
        $schedule = $this->schedule();
        if (! $schedule) {
            $this->error = 'Activez d’abord la Content Factory.';

            return;
        }

        $count = $scheduler->generateAllNow($schedule);
        if ($count === 0) {
            $this->error = 'Aucun contenu en attente à générer.';

            return;
        }

        $schedule->update(['is_active' => true]);
        $worker->launch($schedule->id);
        $this->isActive = true;
        $this->message = "Production immédiate lancée pour {$count} contenu(s). Ils seront générés automatiquement, un par un.";
        $this->error = '';
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->addMonth()->format('Y-m');
    }

    public function moveTask(int $taskId, string $date, ContentScheduler $scheduler): void
    {
        try {
            $scheduler->moveTask($this->ownedTask($taskId), Carbon::createFromFormat('Y-m-d', $date));
            $this->message = 'Contenu déplacé au '.Carbon::parse($date)->translatedFormat('j F Y').'.';
            $this->error = '';
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function prioritize(int $taskId, ContentScheduler $scheduler): void
    {
        $scheduler->prioritize($this->ownedTask($taskId));
        $this->message = 'Ce contenu passe en priorité et sera pris au prochain cycle.';
        $this->error = '';
    }

    public function generateTaskNow(int $taskId, ContentScheduler $scheduler, ContentSchedulerWorkerLauncher $worker): void
    {
        $task = $this->ownedTask($taskId);
        if (! $scheduler->generateNow($task)) {
            $this->error = 'Seul un contenu en attente peut être généré immédiatement.';

            return;
        }

        $task->schedule->update(['is_active' => true]);
        $worker->launch($task->content_schedule_id);
        $this->isActive = true;
        $this->message = 'Ce contenu sera généré dès que la production active sera terminée.';
        $this->error = '';
    }

    public function cancelTask(int $taskId): void
    {
        $task = $this->ownedTask($taskId);
        if (! in_array($task->status, ['queued', 'retrying', 'failed'], true)) {
            $this->error = 'Un contenu déjà en production ne peut pas être retiré depuis le calendrier.';

            return;
        }
        $task->update(['status' => 'cancelled', 'completed_at' => now(), 'retry_at' => null]);
        $this->message = 'Contenu retiré de la file.';
        $this->error = '';
    }

    public function retryTask(int $taskId): void
    {
        $task = $this->ownedTask($taskId);
        if ($task->status !== 'failed') {
            return;
        }
        $task->update([
            'editorial_plan_id' => null,
            'content_run_id' => null,
            'article_id' => null,
            'status' => 'queued',
            'priority' => 1,
            'scheduled_for' => now()->addMinute(),
            'retry_at' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);
        $this->message = 'Nouvelle tentative programmée immédiatement.';
        $this->error = '';
    }

    public function render()
    {
        $projects = SeoProject::query()->withCount('keywords')->orderBy('name')->get();
        $schedule = $this->schedule();
        $calendarStart = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth()->startOfWeek();
        $calendarEnd = $calendarStart->copy()->addDays(41)->endOfDay();
        $tasks = $schedule
            ? $schedule->tasks()->with(['keyword', 'contentCluster', 'editorialIdea', 'article'])->whereNotIn('status', ['cancelled'])->whereBetween('scheduled_for', [$calendarStart, $calendarEnd])->orderBy('scheduled_for')->get()
            : collect();
        $latestPlan = $schedule?->editorialPlans()
            ->with(['ideas' => fn ($query) => $query->with(['keyword', 'contentCluster'])->whereNotIn('status', ['rejected'])->orderBy('position')->orderByDesc('seo_score')])
            ->latest('id')
            ->first();

        return view('livewire.content-scheduler-dashboard', [
            'projects' => $projects,
            'schedule' => $schedule,
            'calendarTitle' => Carbon::createFromFormat('Y-m', $this->month)->translatedFormat('F Y'),
            'days' => $this->calendarDays($calendarStart, $tasks),
            'queue' => $schedule ? $schedule->tasks()->with(['keyword', 'contentCluster', 'editorialIdea', 'article'])->whereNotIn('status', ['cancelled'])->latest('updated_at')->limit(80)->get() : collect(),
            'latestPlan' => $latestPlan,
            'stats' => $this->stats($schedule),
            'hasApiKey' => (bool) Setting::value('gemini_api_key', config('services.gemini.key')),
            'hasSemrushKey' => (bool) Setting::value('semrush_api_key', config('services.semrush.key')),
        ])->title('Calendrier éditorial');
    }

    private function loadSchedule(): void
    {
        $schedule = $this->schedule();
        $this->articlesPerWeek = (int) ($schedule?->articles_per_week ?? 5);
        $this->autoPublish = (bool) ($schedule?->auto_publish ?? false);
        $this->isActive = (bool) ($schedule?->is_active ?? true);
        $this->instructions = (string) ($schedule?->instructions ?? '');
    }

    private function schedule(): ?ContentSchedule
    {
        return $this->projectId ? ContentSchedule::query()->where('seo_project_id', $this->projectId)->first() : null;
    }

    private function ownedTask(int $taskId): ScheduledContentTask
    {
        return ScheduledContentTask::query()
            ->where('seo_project_id', $this->projectId)
            ->whereHas('schedule', fn ($query) => $query->where('user_id', auth()->id()))
            ->findOrFail($taskId);
    }

    private function resetMessages(): void
    {
        $this->message = '';
        $this->error = '';
    }

    private function calendarDays(Carbon $start, Collection $tasks): Collection
    {
        return collect(range(0, 41))->map(function (int $offset) use ($start, $tasks): array {
            $date = $start->copy()->addDays($offset);

            return [
                'date' => $date->format('Y-m-d'),
                'day' => $date->day,
                'current' => $date->format('Y-m') === $this->month,
                'today' => $date->isToday(),
                'tasks' => $tasks->filter(fn (ScheduledContentTask $task) => $task->scheduled_for?->isSameDay($date))->values(),
            ];
        });
    }

    private function stats(?ContentSchedule $schedule): array
    {
        if (! $schedule) {
            return ['queued' => 0, 'active' => 0, 'ready' => 0, 'published' => 0, 'failed' => 0];
        }
        $counts = $schedule->tasks()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'queued' => (int) ($counts['queued'] ?? 0) + (int) ($counts['retrying'] ?? 0),
            'active' => (int) ($counts['generating'] ?? 0) + $schedule->editorialPlans()->where('status', 'planning')->count(),
            'ready' => (int) ($counts['review'] ?? 0),
            'published' => (int) ($counts['published'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
        ];
    }
}
