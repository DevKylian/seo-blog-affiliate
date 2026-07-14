<?php

namespace App\Services\Scraping;

use App\Models\SeoProject;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class GeminiPricingNormalizer
{
    /**
     * Effectue au maximum un appel Gemini pour toute la page. Les montants
     * numériques restent ceux calculés par PHP : l'IA ne peut modifier que les
     * libellés, le contexte, le public et la sélection de quatre fonctions.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    public function normalize(SeoProject $project, string $url, array $candidates): array
    {
        $key = Setting::value('gemini_api_key', config('services.gemini.key'));
        if (! is_string($key) || trim($key) === '' || $candidates === []) {
            return $candidates;
        }

        $input = collect($candidates)->values()->map(fn (array $candidate, int $index): array => [
            'candidate_id' => $index,
            'name' => $candidate['name'],
            'variant_context' => $candidate['variant'] ?? '',
            'audience' => $candidate['audience'] ?? '',
            'verified_price' => $candidate['raw'],
            'verified_variants' => $candidate['price_variants'] ?? [],
            'features' => array_slice($candidate['features'] ?? [], 0, 4),
        ])->all();
        $inputJson = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $prompt = <<<PROMPT
Tu es un assistant spécialisé dans la consolidation de données de tarification SaaS.

Page officielle : {$url}
Produit : {$project->name}

Le PHP a déjà supprimé menus, FAQ, témoignages, matrices détaillées et boutons. Il a aussi regroupé les onglets cachés et les bascules mensuel/annuel. Chaque candidate_id représente maintenant UNE formule commerciale principale ; verified_variants contient ses tarifs sourcés selon le profil ou les conditions.

MISSION
Retourne un objet JSON contenant offers. Produis exactement une entrée par candidate_id fourni, sans en omettre, sans recréer une ligne par variante et sans fusionner deux formules portant des noms différents.

Pour chaque entrée :
- candidate_id : recopie l'identifiant sans le modifier ;
- name : nom commercial propre et concis de l'offre, de 1 à 5 mots, sans prix et sans bouton ;
- variant : résumé très court des facteurs qui font varier le tarif (statut, taille, volume, marché ou facturation), ou chaîne vide si aucune variation n'est prouvée ;
- audience : public ou objectif en une phrase courte, uniquement d'après la source ;
- features : 3 à 4 fonctionnalités clés maximum, exclusivement présentes dans la candidate source.

RÈGLES STRICTES
- Ne calcule, ne corrige et n'invente aucun montant. Les champs de prix sont gérés par PHP et ne font pas partie de ta sortie.
- Préserve les offres gratuites et les offres sur demande.
- Une même formule apparaissant plusieurs fois doit rester UNE SEULE entrée. Résume ses conditions dans variant ; la fourchette de prix vérifiée est déjà calculée par PHP.
- Un prix à 0 répété dans plusieurs onglets ne doit jamais être transformé en plusieurs offres.
- Ne concatène jamais le nom, le prix, la durée ou le public.
- Si le titre source est une phrase marketing ou une action, remplace-le par un libellé neutre et court fidèle à la source (par exemple « Option Expert » pour une offre de délégation à un expert-comptable). N'utilise jamais une phrase complète comme nom d'offre.
- Supprime Démarrer, Comparer les offres, Prendre rendez-vous, En savoir plus et tout autre appel à l'action.
- Aucune introduction, conclusion, note marketing ou Markdown.

CANDIDATS NETTOYÉS
{$inputJson}
PROMPT;

        try {
            $model = (string) Setting::value('gemini_model', 'gemini-2.5-flash-lite');
            if (! in_array($model, ['gemini-2.5-flash-lite', 'gemini-2.5-flash'], true)) {
                $model = 'gemini-2.5-flash-lite';
            }
            $response = Http::timeout(45)->connectTimeout(8)->withHeaders([
                'x-goog-api-key' => trim($key),
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 16384,
                    'thinkingConfig' => ['thinkingBudget' => 512],
                    'responseMimeType' => 'application/json',
                    'responseJsonSchema' => $this->schema(),
                ],
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Gemini pricing HTTP '.$response->status().': '.($response->json('error.message') ?: 'erreur sans détail'));
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            $data = is_string($text) ? json_decode($text, true) : null;
            if (! is_array($data) || ! is_array($data['offers'] ?? null)) {
                throw new \RuntimeException('Gemini n’a pas retourné de récapitulatif tarifaire structuré.');
            }

            $normalized = collect($data['offers'])->keyBy('candidate_id');
            $result = collect($candidates)->values()->map(function (array $candidate, int $index) use ($normalized): array {
                $offer = $normalized->get($index);
                if (! is_array($offer)) {
                    return $candidate;
                }

                $name = $this->clean((string) ($offer['name'] ?? $candidate['name']));
                $variant = $this->clean((string) ($offer['variant'] ?? ''));
                $candidate['name'] = mb_substr($name ?: (string) $candidate['name'], 0, 255);
                $candidate['variant'] = $variant;
                $candidate['audience'] = $this->clean((string) ($offer['audience'] ?? $candidate['audience'] ?? ''));
                $candidate['features'] = $this->verifiedFeatures(
                    $offer['features'] ?? [],
                    $candidate['features'] ?? [],
                );

                return $candidate;
            })->all();

            $usage = $response->json('usageMetadata', []);
            Log::info('Gemini usage', [
                'operation' => 'pricing_normalization',
                'model' => $model,
                'prompt_tokens' => (int) ($usage['promptTokenCount'] ?? 0),
                'output_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
                'thinking_tokens' => (int) ($usage['thoughtsTokenCount'] ?? 0),
                'total_tokens' => (int) ($usage['totalTokenCount'] ?? 0),
            ]);

            return $result;
        } catch (Throwable $exception) {
            Log::warning('Normalisation IA des tarifs indisponible, conservation du résultat PHP.', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return $candidates;
        }
    }

    private function schema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'offers' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'candidate_id' => ['type' => 'integer'],
                            'name' => $string,
                            'variant' => $string,
                            'audience' => $string,
                            'features' => ['type' => 'array', 'items' => $string],
                        ],
                        'required' => ['candidate_id', 'name', 'variant', 'audience', 'features'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['offers'],
            'additionalProperties' => false,
        ];
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', Str::of($value)->stripTags()->toString()) ?: '');
    }

    /** @param array<int, mixed> $selected @param array<int, mixed> $source */
    private function verifiedFeatures(array $selected, array $source): array
    {
        $source = collect($source)->map(fn ($feature) => $this->clean((string) $feature))->filter()->unique()->values();
        $verified = collect($selected)->map(function ($feature) use ($source): ?string {
            $selectedFeature = $this->clean((string) $feature);
            $normalized = Str::ascii(mb_strtolower($selectedFeature));
            $compact = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? $normalized;

            $matched = $source->first(function (string $sourceFeature) use ($normalized, $compact): bool {
                $sourceNormalized = Str::ascii(mb_strtolower($sourceFeature));
                $sourceCompact = preg_replace('/[^a-z0-9]+/', '', $sourceNormalized) ?? $sourceNormalized;

                return $normalized === $sourceNormalized
                    || $compact === $sourceCompact
                    || (mb_strlen($normalized) >= 12 && str_contains($sourceNormalized, $normalized))
                    || (mb_strlen($sourceNormalized) >= 12 && str_contains($normalized, $sourceNormalized))
                    || (mb_strlen($compact) >= 12 && str_contains($sourceCompact, $compact))
                    || (mb_strlen($sourceCompact) >= 12 && str_contains($compact, $sourceCompact));
            });

            // La formulation nettoyée par Gemini est conservée seulement si
            // elle correspond bien à une fonctionnalité présente dans le DOM.
            return $matched !== null ? $selectedFeature : null;
        })->filter()->unique()->take(4)->values();

        return ($verified->isNotEmpty() ? $verified : $source->take(4))->all();
    }
}
