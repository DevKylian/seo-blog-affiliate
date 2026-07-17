<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class SemrushKeywordMetricsClient
{
    public function hasApiKey(): bool
    {
        return $this->apiKey() !== '';
    }

    /** @return array<string, mixed> */
    public function metricRow(string $keyword, string $country): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            throw new RuntimeException('Clé API Semrush manquante. Ajoutez SEMRUSH_API_KEY dans .env ou enregistrez semrush_api_key en base.');
        }

        $response = Http::acceptJson()
            ->withHeaders(['Authorization' => 'Apikey '.$apiKey])
            ->timeout(25)
            ->retry(2, 500, throw: false)
            ->get((string) config('services.semrush.metrics_url'), [
                'keyword' => $keyword,
                'country' => strtoupper($country ?: 'FR'),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Semrush API a répondu '.$response->status().' pour "'.$keyword.'".');
        }

        $payload = $response->json();
        if (! is_array($payload) || data_get($payload, 'meta.success') === false) {
            throw new RuntimeException('Réponse Semrush invalide pour "'.$keyword.'".');
        }

        $data = (array) data_get($payload, 'data', []);

        return [
            'keyword' => (string) data_get($payload, 'meta.keyword', $keyword),
            'country' => (string) data_get($payload, 'meta.country', strtoupper($country ?: 'FR')),
            'search_volume' => data_get($data, 'search_volume'),
            'keyword_difficulty' => data_get($data, 'keyword_difficulty'),
            'intent' => $this->intentLabel((array) data_get($data, 'intents', [])),
            'cpc' => $this->cpcFromSemrush(data_get($data, 'cpc')),
            'competition' => data_get($data, 'competitive_density'),
            'serp_features' => implode(', ', (array) data_get($data, 'serp_features', [])),
            'trend' => implode(',', (array) data_get($data, 'trends', [])),
        ];
    }

    private function apiKey(): string
    {
        return trim((string) Setting::value('semrush_api_key', config('services.semrush.key')));
    }

    private function cpcFromSemrush(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round(((float) $value) / 100, 2);
    }

    /** @param array<int, string> $intents */
    private function intentLabel(array $intents): string
    {
        $labels = collect($intents)
            ->map(fn (string $intent): string => match (strtoupper($intent)) {
                'INFORMATIONAL' => 'Informationnelle',
                'NAVIGATIONAL' => 'Navigationnelle',
                'COMMERCIAL' => 'Commerciale',
                'TRANSACTIONAL' => 'Transactionnelle',
                default => ucfirst(strtolower($intent)),
            })
            ->filter()
            ->unique()
            ->values();

        return $labels->implode(', ');
    }
}
