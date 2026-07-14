<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\AdminAccessLog;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class AccessLogs extends Component
{
    use WithBulkSelection, WithPagination;

    public string $search = '';

    public string $message = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->resetBulkSelection();
    }

    public function deleteSelected(): void
    {
        $ids = array_intersect($this->normalizedSelectedIds(), $this->bulkSelectionIds());
        $count = AdminAccessLog::query()->whereIn('id', $ids)->delete();
        $this->resetBulkSelection();
        $this->message = "{$count} entrée(s) de journal supprimée(s).";
        $this->resetPage();
    }

    protected function bulkSelectionIds(): array
    {
        return $this->filteredQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function filteredQuery(): Builder
    {
        return AdminAccessLog::query()
            ->when($this->search, fn ($query) => $query->where(fn ($query) => $query->where('path', 'like', '%'.$this->search.'%')->orWhere('ip_address', 'like', '%'.$this->search.'%')));
    }

    public function render()
    {
        $logs = $this->filteredQuery()->with('user')
            ->latest('created_at')->paginate(20);

        return view('livewire.access-logs', ['logs' => $logs])->title('Logs d’accès admin');
    }
}
