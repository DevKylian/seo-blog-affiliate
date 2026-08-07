<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\Article;
use App\Services\ArticleRegenerationWorkerLauncher;
use App\Services\EditorialConsolidationService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.admin')]
class Articles extends Component
{
    use WithBulkSelection, WithPagination;

    public string $search = '';

    public string $status = '';

    public string $duplicateFilter = '';

    public bool $massView = false;

    public bool $todayOnly = false;

    public string $message = '';

    public string $error = '';

    public ?int $regeneratingArticleId = null;

    public string $sortField = 'id';

    public string $sortDirection = 'desc';

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function updatedDuplicateFilter(): void
    {
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function mergeDuplicate(int $articleId, EditorialConsolidationService $consolidation): void
    {
        $duplicate = Article::query()->with('canonicalArticle')->findOrFail($articleId);
        if (! $duplicate->canonicalArticle) {
            $this->error = 'Aucun article canonique n’est associé à ce doublon.';

            return;
        }

        $canonical = $consolidation->merge($duplicate, $duplicate->canonicalArticle, $duplicate->duplicate_score);
        $this->message = "Contenu fusionné dans « {$canonical->title} ». Le doublon est archivé.";
        $this->error = '';
    }

    public function archiveDuplicate(int $articleId, EditorialConsolidationService $consolidation): void
    {
        $consolidation->archive(Article::query()->findOrFail($articleId));
        $this->message = 'Le brouillon doublon a été archivé.';
    }

    public function ignoreDuplicate(int $articleId): void
    {
        Article::query()->findOrFail($articleId)->update(['duplicate_status' => 'ignored']);
        $this->message = 'Exception enregistrée. Le contenu reste visible et son score est conservé.';
    }

    public function deleteSelected(): void
    {
        $ids = array_intersect($this->normalizedSelectedIds(), $this->bulkSelectionIds());
        $count = Article::query()->whereIn('id', $ids)->delete();
        $this->resetBulkSelection();
        $this->message = "{$count} article(s) supprimé(s).";
        $this->resetPage();
    }

    public function regenerate(int $articleId, ArticleRegenerationWorkerLauncher $worker): void
    {
        $this->message = '';
        $this->error = '';
        $this->regeneratingArticleId = $articleId;
        $article = null;

        try {
            $article = Article::query()->findOrFail($articleId);
            $checks = $article->quality_checks ?? [];
            if (in_array($checks['regeneration_status'] ?? null, ['queued', 'processing'], true)) {
                $this->message = "Régénération déjà en cours pour « {$article->title} ».";

                return;
            }

            $article->forceFill([
                'quality_checks' => array_merge($checks, [
                    'regeneration_status' => 'queued',
                    'regeneration_queued_at' => now()->toDateTimeString(),
                    'regeneration_started_at' => null,
                    'regeneration_finished_at' => null,
                    'regeneration_error' => null,
                    'regeneration_user_id' => auth()->id(),
                ]),
            ])->save();
            $worker->launch($article->id);
            $this->message = "Régénération lancée en arrière-plan pour « {$article->title} ». Vous pouvez rester sur la page.";
        } catch (Throwable $exception) {
            report($exception);
            if ($article) {
                $article->forceFill([
                    'quality_checks' => array_merge($article->quality_checks ?? [], [
                        'regeneration_status' => 'failed',
                        'regeneration_finished_at' => now()->toDateTimeString(),
                        'regeneration_error' => mb_substr($exception->getMessage(), 0, 1000),
                    ]),
                ])->save();
            }
            $this->error = 'Régénération impossible à lancer : '.$exception->getMessage();
        } finally {
            $this->regeneratingArticleId = null;
        }
    }

    protected function bulkSelectionIds(): array
    {
        return $this->filteredQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function toggleToday(): void
    {
        $this->todayOnly = ! $this->todayOnly;
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function toggleMassView(): void
    {
        $this->massView = ! $this->massView;
        $this->resetPage();
        $this->resetBulkSelection();
    }

    private function filteredQuery(): Builder
    {
        $today = now()->toDateString();

        return Article::query()
            ->when($this->search, fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->duplicateFilter === 'potential', fn ($query) => $query->whereIn('duplicate_status', ['potential', 'needs_differentiation']))
            ->when($this->duplicateFilter === 'merged', fn ($query) => $query->where('duplicate_status', 'merged'))
            ->when($this->duplicateFilter === 'ignored', fn ($query) => $query->where('duplicate_status', 'ignored'))
            ->when($this->todayOnly, function ($query) use ($today) {
                $query->where(function ($q) use ($today) {
                    $q->whereDate('published_at', $today)
                      ->orWhereDate('created_at', $today)
                      ->orWhereDate('scheduled_at', $today);
                });
            });
    }

    public function render()
    {
        $articles = $this->filteredQuery()->with(['project', 'keyword', 'categories', 'canonicalArticle', 'latestAudit'])
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->massView ? 500 : 15);

        return view('livewire.articles', compact('articles'))->title('Articles');
    }
}
