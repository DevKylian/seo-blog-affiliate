<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\Plan;
use App\Models\SeoProject;
use App\Models\SourcePage;
use App\Services\SourceCrawlWorkerLauncher;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin')]
class Research extends Component
{
    use WithBulkSelection;

    public ?int $projectId = null;

    public string $url = '';

    public string $type = 'homepage';

    public string $message = '';

    public string $error = '';

    public ?int $activeSourceId = null;

    public function mount(): void
    {
        $this->projectId = SeoProject::query()->value('id');
        $this->activeSourceId = SourcePage::query()
            ->where('status', 'processing')
            ->latest('updated_at')
            ->value('id');
    }

    public function updatedProjectId(): void
    {
        $this->resetBulkSelection();
        $this->prefillUrl();
    }

    public function updatedType(): void
    {
        $this->prefillUrl();
    }

    public function crawl(SourceCrawlWorkerLauncher $worker): void
    {
        $this->validate([
            'projectId' => ['required', 'exists:seo_projects,id'],
            'url' => ['required', 'url', 'max:2000'],
            'type' => ['required', 'in:homepage,pricing,features,integrations,faq,documentation,other'],
        ]);

        $this->message = '';
        $this->error = '';
        $source = null;
        $project = null;
        try {
            $project = SeoProject::query()->findOrFail($this->projectId);
            $source = SourcePage::query()->updateOrCreate(
                ['seo_project_id' => $project->id, 'url' => $this->url],
                ['type' => $this->type, 'status' => 'processing', 'error_message' => null],
            );
            $project->update(['crawl_status' => 'processing']);
            $this->activeSourceId = $source->id;
            if (! app()->runningUnitTests()) {
                $worker->launch($source->id);
            }
            $this->message = 'Collecte lancée en arrière-plan. Cette page se met à jour automatiquement.';
        } catch (Throwable $exception) {
            $source?->update(['status' => 'failed', 'error_message' => mb_substr($exception->getMessage(), 0, 2000)]);
            $project?->update(['crawl_status' => 'failed']);
            $this->activeSourceId = null;
            report($exception);
            $this->error = $exception->getMessage();
        }
    }

    public function refreshCrawlStatus(): void
    {
        if (! $this->activeSourceId) {
            return;
        }

        $source = SourcePage::query()->withCount('evidenceChunks')->find($this->activeSourceId);
        if (! $source) {
            $this->activeSourceId = null;

            return;
        }
        if ($source->status === 'verified') {
            $this->message = "Source vérifiée : {$source->evidence_chunks_count} éléments de preuve extraits.";
            $this->error = '';
            $this->activeSourceId = null;
        } elseif ($source->status === 'failed') {
            $this->error = $source->error_message ?: 'La collecte a échoué.';
            $this->message = '';
            $this->activeSourceId = null;
        }
    }

    public function deleteSelected(): void
    {
        $ids = array_intersect($this->normalizedSelectedIds(), $this->bulkSelectionIds());
        $count = DB::transaction(function () use ($ids): int {
            Plan::query()->whereIn('source_page_id', $ids)->delete();

            return SourcePage::query()->where('seo_project_id', $this->projectId)->whereIn('id', $ids)->delete();
        });
        $this->resetBulkSelection();
        $this->message = "{$count} source(s) et leurs tarifs associés supprimés.";
    }

    protected function bulkSelectionIds(): array
    {
        if (! $this->projectId) {
            return [];
        }

        return SourcePage::query()->where('seo_project_id', $this->projectId)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function prefillUrl(): void
    {
        $project = $this->projectId ? SeoProject::query()->find($this->projectId) : null;
        $this->url = $this->type === 'pricing' ? (string) $project?->pricing_url : (string) $project?->website_url;
    }

    public function render()
    {
        $selected = $this->projectId ? SeoProject::query()->find($this->projectId) : null;

        return view('livewire.research', [
            'projects' => SeoProject::query()->orderBy('name')->get(),
            'sources' => $selected?->sourcePages()->withCount('evidenceChunks')->latest()->get() ?? collect(),
            'plans' => $selected?->plans()->latest()->get() ?? collect(),
        ])->title('Collecte & preuves');
    }
}
