<?php

namespace App\Livewire\Concerns;

trait WithBulkSelection
{
    public array $selectedIds = [];

    public bool $selectAll = false;

    abstract protected function bulkSelectionIds(): array;

    public function updatedSelectAll(bool $selected): void
    {
        $this->selectedIds = $selected ? $this->bulkSelectionIds() : [];
    }

    public function updatedSelectedIds(): void
    {
        $available = $this->bulkSelectionIds();
        $this->selectedIds = array_values(array_intersect($this->normalizedSelectedIds(), $available));
        $this->selectAll = $available !== [] && count($this->selectedIds) === count($available);
    }

    public function clearSelection(): void
    {
        $this->resetBulkSelection();
    }

    protected function normalizedSelectedIds(): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $this->selectedIds), fn (int $id) => $id > 0)));
    }

    protected function resetBulkSelection(): void
    {
        $this->selectedIds = [];
        $this->selectAll = false;
    }
}
