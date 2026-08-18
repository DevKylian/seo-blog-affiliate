<?php
namespace App\Services\EditorialPipeline\Agents;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use RuntimeException;

class BriefAgent extends BaseAgent {
    public function getName(): string { return 'brief'; }
    protected function process(array $inputData = []): array
    {
        $articles = $inputData['articles_with_conversion'] ?? [];
        if (empty($articles)) {
            return ['briefs' => [], 'status' => 'briefs_generated'];
        }

        $key = Setting::value('gemini_api_key', config('services.gemini.key'));
        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('Clé API Gemini manquante. Configurez-la dans les réglages admin.');
        }
        $model = Setting::value('gemini_model', 'gemini-2.5-flash');
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $articlesJson = json_encode(array_map(fn($a) => ['title' => $a['title'], 'h2_structure' => $a['h2_structure']], $articles));

        $prompt = "Tu es un rédacteur en chef expert. Voici une liste d'articles prévus avec leur titre et plan H2 :
{$articlesJson}

Pour chaque article, définis un cahier des charges (brief) précis.
1. Le 'tone' : ton à employer (ex: Expert, Rassurant).
2. La 'target_audience' : le public cible.
3. Le 'word_count_target' : nombre de mots visé (ex: 1500).

Renvoie UNIQUEMENT un JSON strict :
{
  \"briefs\": [
    {
      \"title\": \"Titre exact de l'article (pour correspondance)\",
      \"tone\": \"Ton...\",
      \"target_audience\": \"Cible...\",
      \"word_count_target\": 1500
    }
  ]
}";

        Log::info("BriefAgent appel API Gemini");

        $response = Http::withoutVerifying()->timeout(60)->withHeaders([
            'x-goog-api-key' => trim($key),
            'Content-Type' => 'application/json',
        ])->post($endpoint, [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.5, 'responseMimeType' => 'application/json'],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Erreur API Gemini : " . $response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $text = preg_replace('/```json|```/', '', $text);
        $data = json_decode(trim($text), true);

        if (! is_array($data) || ! isset($data['briefs'])) {
            throw new RuntimeException('Gemini n\'a pas retourné le JSON attendu.');
        }

        // Merge brief data into articles
        foreach ($articles as &$article) {
            foreach ($data['briefs'] as $b) {
                if ($b['title'] === ($article['title'] ?? '')) {
                    $article['brief'] = $b;
                }
            }
        }

        return [
            'briefs' => $articles,
            'status' => 'briefs_generated'
        ];
    }
}
