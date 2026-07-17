<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithBulkSelection;
use App\Models\SeoProject;
use App\Services\CompetitorPricingUrlParser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Projects extends Component
{
    use WithBulkSelection;

    public ?int $editingId = null;

    public string $name = '';

    public string $websiteUrl = '';

    public string $pricingUrl = '';

    public string $affiliateUrl = '';

    public string $country = 'FR';

    public string $currency = 'EUR';

    public string $description = '';

    public string $positioning = '';

    public string $featuresText = '';

    public string $strengthsText = '';

    public string $limitationsText = '';

    public string $bestForText = '';

    public string $competitorsText = '';

    public string $competitorPricingUrlsText = '';

    public string $message = '';

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetBulkSelection();
    }

    public function createProject(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'websiteUrl' => ['required', 'url', 'max:2000'],
            'pricingUrl' => ['nullable', 'url', 'max:2000'],
            'affiliateUrl' => ['nullable', 'url', 'max:2000'],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'competitorsText' => ['nullable', 'string', 'max:5000'],
            'competitorPricingUrlsText' => ['nullable', 'string', 'max:10000'],
        ]);
        $competitorConfig = app(CompetitorPricingUrlParser::class)->parse($this->competitorsText, $this->competitorPricingUrlsText);

        $payload = [
            'name' => $data['name'],
            'website_url' => $data['websiteUrl'],
            'pricing_url' => $data['pricingUrl'] ?: null,
            'affiliate_url' => $data['affiliateUrl'] ?: null,
            'country' => strtoupper($data['country']),
            'currency' => strtoupper($data['currency']),
            'description' => $this->description ?: null,
            'positioning' => $this->positioning ?: null,
            'features' => $this->lines($this->featuresText),
            'strengths' => $this->lines($this->strengthsText),
            'limitations' => $this->lines($this->limitationsText),
            'best_for' => $this->lines($this->bestForText),
            'competitors' => $competitorConfig['competitors'],
            'competitor_pricing_urls' => $competitorConfig['pricing_urls'],
        ];

        if ($this->editingId) {
            $project = SeoProject::query()->findOrFail($this->editingId);
            $project->update($payload);
        } else {
            $project = SeoProject::query()->create(['slug' => $this->uniqueSlug($data['name']), ...$payload]);
        }
        app(\App\Services\AffiliateSeoDefaults::class)->ensureForProject($project);

        $this->reset(['editingId', 'name', 'websiteUrl', 'pricingUrl', 'affiliateUrl', 'description', 'positioning', 'featuresText', 'strengthsText', 'limitationsText', 'bestForText', 'competitorsText', 'competitorPricingUrlsText']);
        $this->message = 'Le projet a été enregistré. Vous pouvez maintenant collecter ses sources.';
    }

    public function editProject(int $projectId): void
    {
        $project = SeoProject::query()->findOrFail($projectId);
        $this->editingId = $project->id;
        $this->name = $project->name;
        $this->websiteUrl = $project->website_url;
        $this->pricingUrl = (string) $project->pricing_url;
        $this->affiliateUrl = (string) $project->affiliate_url;
        $this->country = $project->country;
        $this->currency = $project->currency;
        $this->description = (string) $project->description;
        $this->positioning = (string) $project->positioning;
        $this->featuresText = implode("\n", $project->features ?? []);
        $this->strengthsText = implode("\n", $project->strengths ?? []);
        $this->limitationsText = implode("\n", $project->limitations ?? []);
        $this->bestForText = implode("\n", $project->best_for ?? []);
        $this->competitorsText = implode("\n", $project->competitors ?? []);
        $this->competitorPricingUrlsText = app(CompetitorPricingUrlParser::class)->format($project->competitor_pricing_urls ?? []);
        $this->message = 'Projet chargé dans le formulaire.';
    }

    public function deleteSelected(): void
    {
        $ids = array_intersect($this->normalizedSelectedIds(), $this->bulkSelectionIds());
        $editingProjectDeleted = $this->editingId && in_array($this->editingId, $ids, true);
        $count = SeoProject::query()->whereIn('id', $ids)->delete();
        if ($editingProjectDeleted) {
            $this->reset(['editingId', 'name', 'websiteUrl', 'pricingUrl', 'affiliateUrl', 'description', 'positioning', 'featuresText', 'strengthsText', 'limitationsText', 'bestForText', 'competitorsText', 'competitorPricingUrlsText']);
        }
        $this->resetBulkSelection();
        $this->message = "{$count} projet(s) et toutes leurs données associées supprimés.";
    }

    protected function bulkSelectionIds(): array
    {
        return $this->filteredQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function filteredQuery(): Builder
    {
        return SeoProject::query()
            ->when($this->search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('website_url', 'like', '%'.$this->search.'%')));
    }

    private function lines(string $value): array
    {
        return collect(preg_split('/\R/', $value))->map(fn ($line) => trim($line))->filter()->values()->all();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'outil';
        $slug = $base;
        $counter = 2;
        while (SeoProject::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.projects', [
            'projects' => $this->filteredQuery()->withCount(['sourcePages', 'keywords', 'articles'])->latest()->get(),
        ])->title('Projets');
    }
}
