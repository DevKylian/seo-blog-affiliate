<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\Keyword;
use App\Models\SeoProject;
use App\Services\SemrushCsvImporter;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Services\GeminiSeedKeywordGenerator;
use Throwable;

#[Layout('layouts.admin')]
class Keywords extends Component
{
    use WithBulkSelection, WithFileUploads, WithPagination;

    public ?int $projectId = null;

    public $csv;

    public string $pastedKeywords = '';

    public array $suggestedSeeds = [];

    public string $search = '';

    public string $message = '';

    public string $error = '';

    public array $rejectedKeywords = [];

    public bool $showCleaningConfirmation = false;

    public function mount(): void
    {
        $this->projectId = SeoProject::query()->value('id');
    }

    public function updatedProjectId(): void
    {
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function import(SemrushCsvImporter $importer): void
    {
        $this->validate(['projectId' => ['required', 'exists:seo_projects,id'], 'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);
        $this->message = '';
        $this->error = '';
        try {
            $count = $importer->import(SeoProject::query()->findOrFail($this->projectId), $this->csv->getRealPath());
            $this->message = "{$count} mots-clés importés ou mis à jour.";
            $this->reset('csv');
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function importPasted(SemrushCsvImporter $importer): void
    {
        $this->validate([
            'projectId' => ['required', 'exists:seo_projects,id'],
            'pastedKeywords' => ['required', 'string', 'max:1500000'],
        ]);
        $this->message = '';
        $this->error = '';
        try {
            $count = $importer->importText(
                SeoProject::query()->findOrFail($this->projectId),
                $this->pastedKeywords,
            );
            $this->message = "{$count} mots-clés Semrush collés ont été importés ou mis à jour.";
            $this->reset('pastedKeywords');
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function generateSeeds(GeminiSeedKeywordGenerator $generator): void
    {
        $this->validate(['projectId' => ['required', 'exists:seo_projects,id']]);
        $this->message = '';
        $this->error = '';
        $this->suggestedSeeds = [];
        try {
            $project = SeoProject::query()->findOrFail($this->projectId);
            $this->suggestedSeeds = $generator->generate($project);
            $this->message = "L'IA a généré " . count($this->suggestedSeeds) . " mots-clés de base à rechercher sur Semrush.";
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function analyzeForCleaning(\App\Services\GeminiKeywordSanitizer $sanitizer): void
    {
        set_time_limit(180);
        $this->message = '';
        $this->error = '';
        $this->rejectedKeywords = [];
        
        $ids = array_intersect($this->normalizedSelectedIds(), $this->bulkSelectionIds());
        if (empty($ids)) {
            $ids = $this->bulkSelectionIds();
        }

        if (empty($ids)) {
            $this->error = 'Aucun mot-clé à analyser.';
            return;
        }

        $keywords = Keyword::query()->whereIn('id', $ids)->get();
        $keywordStrings = $keywords->pluck('keyword')->all();

        try {
            $approved = $sanitizer->sanitize($keywordStrings);
            $approvedSet = array_flip($approved);
            
            foreach ($keywords as $kw) {
                if (!isset($approvedSet[$kw->keyword])) {
                    $this->rejectedKeywords[] = ['id' => $kw->id, 'keyword' => $kw->keyword];
                }
            }

            if (empty($this->rejectedKeywords)) {
                $this->message = 'L\'IA a analysé les mots-clés et n\'a trouvé aucun hors-sujet !';
            } else {
                $this->showCleaningConfirmation = true;
            }
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function confirmCleaning(): void
    {
        $ids = array_column($this->rejectedKeywords, 'id');
        if (!empty($ids)) {
            $count = Keyword::query()->whereIn('id', $ids)->delete();
            $this->message = "{$count} mot(s)-clé(s) hors-sujet supprimé(s) avec succès.";
        }
        
        $this->resetBulkSelection();
        $this->cancelCleaning();
        $this->resetPage();
    }

    public function cancelCleaning(): void
    {
        $this->showCleaningConfirmation = false;
        $this->rejectedKeywords = [];
    }

    public function deleteSelected(): void
    {
        $ids = array_intersect($this->normalizedSelectedIds(), $this->bulkSelectionIds());
        $count = Keyword::query()->whereIn('id', $ids)->delete();
        $this->resetBulkSelection();
        $this->message = "{$count} mot(s)-clé(s) supprimé(s).";
        $this->resetPage();
    }

    public function copyAllKeywords(): void
    {
        $keywords = $this->filteredQuery()->orderByDesc('opportunity_score')->pluck('keyword')->implode("\n");
        $this->dispatch('copy-to-clipboard', text: $keywords);
        $this->message = "Tous les mots-clés ont été copiés dans le presse-papier.";
    }

    public function exportCsv()
    {
        $keywords = $this->filteredQuery()->orderByDesc('opportunity_score')->get();

        $headers = [
            'Content-type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="opportunites-editoriales.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = ['Mot-clé', 'Stratégie', 'Cluster', 'Intention', 'Volume', 'KD', 'CPC', 'Score Opportunité', 'Date import'];

        $callback = function () use ($keywords, $columns) {
            $file = fopen('php://output', 'w');
            // Ajout du BOM UTF-8 pour Excel
            fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, $columns, ';');

            foreach ($keywords as $keyword) {
                fputcsv($file, [
                    $keyword->keyword,
                    $keyword->strategy_tier,
                    $keyword->cluster,
                    $keyword->intent,
                    $keyword->search_volume,
                    $keyword->keyword_difficulty,
                    $keyword->cpc,
                    round((float)$keyword->opportunity_score),
                    $keyword->created_at?->format('d/m/Y'),
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    protected function bulkSelectionIds(): array
    {
        return $this->filteredQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function filteredQuery(): Builder
    {
        return Keyword::query()
            ->when($this->projectId, fn ($query) => $query->where('seo_project_id', $this->projectId))
            ->when($this->search, fn ($query) => $query->where('keyword', 'like', '%'.$this->search.'%'));
    }

    public function render()
    {
        $keywords = $this->filteredQuery()
            ->orderByDesc('opportunity_score')->paginate(12);

        return view('livewire.keywords', ['projects' => SeoProject::query()->orderBy('name')->get(), 'keywords' => $keywords])->title('Mots-clés Semrush');
    }
}
