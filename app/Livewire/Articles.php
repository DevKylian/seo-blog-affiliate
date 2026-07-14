<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\Article;
use App\Services\EditorialConsolidationService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Articles extends Component
{
    use WithBulkSelection, WithPagination;

    public string $search = '';

    public string $status = '';

    public string $duplicateFilter = '';

    public string $message = '';

    public string $error = '';

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

    protected function bulkSelectionIds(): array
    {
        return $this->filteredQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function filteredQuery(): Builder
    {
        return Article::query()
            ->when($this->search, fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->duplicateFilter === 'potential', fn ($query) => $query->whereIn('duplicate_status', ['potential', 'needs_differentiation']))
            ->when($this->duplicateFilter === 'merged', fn ($query) => $query->where('duplicate_status', 'merged'))
            ->when($this->duplicateFilter === 'ignored', fn ($query) => $query->where('duplicate_status', 'ignored'));
    }

    public function render()
    {
        $articles = $this->filteredQuery()->with(['project', 'keyword', 'categories', 'canonicalArticle'])
            ->latest()->paginate(15);

        return view('livewire.articles', compact('articles'))->title('Articles');
    }
}
