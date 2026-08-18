<?php
namespace App\Services\EditorialPipeline\Agents;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use RuntimeException;

class CritiqueAgent extends BaseAgent {
    public function getName(): string { return 'critique'; }
    protected function process(array $inputData = []): array
    {
        $drafts = $inputData['drafts'] ?? [];
        if (empty($drafts)) {
            return ['final_articles' => [], 'status' => 'reviewed_and_ready'];
        }

        $key = Setting::value('gemini_api_key', config('services.gemini.key'));
        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('Clé API Gemini manquante. Configurez-la dans les réglages admin.');
        }
        $model = Setting::value('gemini_model', 'gemini-2.5-flash');
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $finalArticles = [];
        $defaultProjectId = \App\Models\SeoProject::first()->id ?? 1;

        foreach ($drafts as $draft) {
            $content = $draft['draft_content'] ?? '';
            
            $prompt = "Tu es un relecteur SEO (Rédacteur en chef).
Lis cet article et donne une note sur 100 pour sa qualité, son ton et son optimisation.
Donne aussi 2 ou 3 remarques courtes d'amélioration.

Article:
{$content}

Renvoie UNIQUEMENT un JSON strict :
{
  \"score\": 85,
  \"notes\": [\"Remarque 1\", \"Remarque 2\"]
}";

            Log::info("CritiqueAgent appel API Gemini pour : " . $draft['title']);

            $response = Http::withoutVerifying()->timeout(60)->withHeaders([
                'x-goog-api-key' => trim($key),
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.2, 'responseMimeType' => 'application/json'],
            ]);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text');
                $text = preg_replace('/```json|```/', '', $text);
                $reviewData = json_decode(trim($text), true);
                
                $draft['quality_score'] = $reviewData['score'] ?? 80;
                $draft['critique_notes'] = $reviewData['notes'] ?? [];
            } else {
                $draft['quality_score'] = 80;
                $draft['critique_notes'] = ['Erreur lors de la relecture API'];
            }
            
            $draft['final_content'] = $content;
            $finalArticles[] = $draft;

            \App\Models\Article::create([
                'title' => $draft['title'] ?? 'Article IA',
                'slug' => ($draft['slug'] ?? \Illuminate\Support\Str::slug($draft['title'] ?? 'Article')) . '-' . substr(uniqid(), -5),
                'type' => 'guide',
                'status' => 'draft',
                'seo_project_id' => $this->pipeline->seo_project_id ?? $defaultProjectId,
                'body' => $draft['final_content'],
                'content_blocks' => [
                    ['type' => 'html', 'content' => $draft['final_content']]
                ]
            ]);
        }

        return [
            'final_articles' => $finalArticles,
            'status' => 'reviewed_and_ready'
        ];
    }
}
