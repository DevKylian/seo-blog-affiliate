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

    public string $faqText = '';

    public string $competitorsText = '';

    public string $competitorPricingUrlsText = '';

    public string $message = '';

    public string $search = '';

    public bool $isRetryingAi = false;

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
        
        $faq = collect(preg_split('/\R/', $this->faqText))->map(fn ($line) => trim($line))->filter()
            ->map(function($line) {
                $parts = explode('|', $line);
                return count($parts) === 2 ? ['question' => trim($parts[0]), 'answer' => trim($parts[1])] : null;
            })->filter()->values()->all();

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
            'faq' => $faq,
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

        $this->reset(['editingId', 'name', 'websiteUrl', 'pricingUrl', 'affiliateUrl', 'description', 'positioning', 'featuresText', 'strengthsText', 'limitationsText', 'bestForText', 'faqText', 'competitorsText', 'competitorPricingUrlsText']);
        $this->message = 'Le projet a été enregistré. Vous pouvez maintenant collecter ses sources.';
    }

    public function autofillWithAI(): void
    {
        $this->validate([
            'name' => ['required', 'string'],
            'websiteUrl' => ['required', 'url'],
        ]);

        $key = \App\Models\Setting::value('gemini_api_key', config('services.gemini.key'));
        if (! is_string($key) || trim($key) === '') {
            $this->addError('name', 'Clé API Gemini introuvable dans les réglages.');
            return;
        }

        $model = \App\Models\Setting::value('gemini_model', 'gemini-2.5-flash-lite');
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';

        $prompt = "Agis comme un expert SEO B2B. Je crée une fiche annuaire pour le logiciel \"{$this->name}\" ({$this->websiteUrl}).\nRetourne UNIQUEMENT un objet JSON avec les clés exactes suivantes :\n- \"description\": une ou deux phrases décrivant le logiciel de façon objective.\n- \"positioning\": une phrase courte accrocheuse sur son positionnement (ex: L'alternative simple pour...).\n- \"features\": un tableau de 5 à 8 chaînes courtes listant les fonctionnalités principales.\n- \"strengths\": un tableau de 3 à 5 chaînes courtes listant les points forts.\n- \"limitations\": un tableau de 2 à 4 chaînes courtes listant les limites ou ce qu'il ne fait pas.\n- \"best_for\": un tableau de 2 à 4 types de profils (ex: Freelances, PME, Agences).\n- \"faq\": un tableau d'objets avec les clés \"question\" et \"answer\" (génère 3 ou 4 questions/réponses pertinentes sur le produit).\n\nNe renvoie que le JSON valide, sans bloc de code markdown (pas de ```json).";

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(45)->withHeaders([
                'x-goog-api-key' => trim($key),
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.2],
            ]);

            if ($response->successful()) {
                $this->isRetryingAi = false;
                $content = $response->json('candidates.0.content.parts.0.text');
                $content = str_replace(['```json', '```'], '', $content);
                $data = json_decode(trim($content), true);

                if (is_array($data)) {
                    $this->description = $data['description'] ?? $this->description;
                    $this->positioning = $data['positioning'] ?? $this->positioning;
                    
                    if (!empty($data['features']) && is_array($data['features'])) {
                        $this->featuresText = implode("\n", $data['features']);
                    }
                    if (!empty($data['strengths']) && is_array($data['strengths'])) {
                        $this->strengthsText = implode("\n", $data['strengths']);
                    }
                    if (!empty($data['limitations']) && is_array($data['limitations'])) {
                        $this->limitationsText = implode("\n", $data['limitations']);
                    }
                    if (!empty($data['best_for']) && is_array($data['best_for'])) {
                        $this->bestForText = implode("\n", $data['best_for']);
                    }
                    if (!empty($data['faq']) && is_array($data['faq'])) {
                        $this->faqText = collect($data['faq'])
                            ->map(fn($item) => ($item['question'] ?? '') . ' | ' . ($item['answer'] ?? ''))
                            ->filter(fn($line) => str_contains($line, '|'))
                            ->implode("\n");
                    }

                    $this->message = "Données générées par l'IA ! Vérifiez les informations avant d'enregistrer.";
                } else {
                    $this->addError('name', 'Impossible de décoder la réponse JSON de Gemini.');
                }
            } else {
                if ($response->status() === 503) {
                    $this->isRetryingAi = true;
                    $this->message = "L'IA est surchargée (Erreur 503). Nouvelle tentative automatique dans 5 secondes...";
                } else {
                    $this->isRetryingAi = false;
                    $this->addError('name', 'Erreur API Gemini: ' . $response->body());
                }
            }
        } catch (\Exception $e) {
            $this->isRetryingAi = false;
            $this->addError('name', 'Erreur: ' . $e->getMessage());
        }
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
        $this->faqText = collect($project->faq ?? [])->map(fn($item) => ($item['question'] ?? '') . ' | ' . ($item['answer'] ?? ''))->implode("\n");
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
            $this->reset(['editingId', 'name', 'websiteUrl', 'pricingUrl', 'affiliateUrl', 'description', 'positioning', 'featuresText', 'strengthsText', 'limitationsText', 'bestForText', 'faqText', 'competitorsText', 'competitorPricingUrlsText']);
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
