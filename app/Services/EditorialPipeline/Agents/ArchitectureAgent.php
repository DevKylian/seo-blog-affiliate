<?php
namespace App\Services\EditorialPipeline\Agents;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use RuntimeException;
use Illuminate\Support\Str;

class ArchitectureAgent extends BaseAgent {
    public function getName(): string { return 'architecture'; }
    protected function process(array $inputData = []): array
    {
        $clusters = $inputData['analyzed_clusters'] ?? [];
        if (empty($clusters)) {
            return ['articles' => [], 'status' => 'architecture_built'];
        }

        $key = Setting::value('gemini_api_key', config('services.gemini.key'));
        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('Clé API Gemini manquante. Configurez-la dans les réglages admin.');
        }

        $model = Setting::value('gemini_model', 'gemini-2.5-flash');
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        // Limit clusters for API limits if needed, or process them all
        $clustersToProcess = array_slice($clusters, 0, 5); // Let's limit to 5 articles for the prototype to avoid giant prompts
        $clustersJson = json_encode(array_map(fn($c) => ['main_keyword' => $c['main_keyword'], 'keywords' => $c['keywords']], $clustersToProcess));

        $prompt = "Tu es un expert SEO. Voici une liste de clusters de mots-clés :
{$clustersJson}

Pour chaque cluster, trouve :
1. Un titre d'article très accrocheur, naturel et optimisé SEO (ÉVITE ABSOLUMENT les titres génériques comme 'Le guide ultime').
2. Un slug SEO friendly.
3. Un plan H2 détaillé et intelligent qui répond à l'intention de recherche.

Renvoie UNIQUEMENT un JSON avec cette structure :
{
  \"articles\": [
    {
      \"main_keyword\": \"mot_clé_du_cluster\",
      \"title\": \"Titre accrocheur et expert\",
      \"slug\": \"slug-optimise\",
      \"h2_structure\": [\"H2 1\", \"H2 2\", \"H2 3\"]
    }
  ]
}";

        Log::info("ArchitectureAgent appel API Gemini");

        $response = Http::withoutVerifying()->timeout(60)->withHeaders([
            'x-goog-api-key' => trim($key),
            'Content-Type' => 'application/json',
        ])->post($endpoint, [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Erreur API Gemini : " . $response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $text = preg_replace('/```json|```/', '', $text);
        
        $data = json_decode(trim($text), true);

        if (! is_array($data) || ! isset($data['articles'])) {
            throw new RuntimeException('Gemini n\'a pas retourné le JSON attendu.');
        }

        // Merge back the opportunity score and other data
        foreach ($data['articles'] as &$article) {
            foreach ($clustersToProcess as $c) {
                if ($c['main_keyword'] === ($article['main_keyword'] ?? '')) {
                    $article['opportunity_score'] = $c['opportunity_score'] ?? 0;
                    $article['target_keywords'] = $c['keywords'] ?? [];
                }
            }
        }

        return [
            'articles' => $data['articles'],
            'status' => 'architecture_built'
        ];
    }
}
