<?php
namespace App\Services\EditorialPipeline\Agents;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use RuntimeException;

class WritingAgent extends BaseAgent {
    public function getName(): string { return 'writing'; }
    protected function process(array $inputData = []): array
    {
        $briefs = $inputData['briefs'] ?? [];
        if (empty($briefs)) {
            return ['drafts' => [], 'status' => 'content_drafted'];
        }

        $key = Setting::value('gemini_api_key', config('services.gemini.key'));
        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('Clé API Gemini manquante. Configurez-la dans les réglages admin.');
        }
        $model = Setting::value('gemini_model', 'gemini-2.5-flash');
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $drafts = [];
        foreach ($briefs as $brief) {
            $prompt = "Tu es un rédacteur web expert. 
Rédige un article complet en HTML (uniquement le contenu, pas de <html> ni <body>).
Titre : {$brief['title']}
Plan H2 : " . implode(", ", $brief['h2_structure'] ?? []) . "
Ton : " . ($brief['brief']['tone'] ?? 'Expert') . "
Cible : " . ($brief['brief']['target_audience'] ?? 'Général') . "

Utilise des balises <h2>, <h3>, <p>, <ul> pour formater le texte de manière très lisible et professionnelle. 
Évite les introductions ennuyeuses, va droit au but.

Renvoie UNIQUEMENT le code HTML brut, sans bloc Markdown ```html.";

            Log::info("WritingAgent appel API Gemini pour : " . $brief['title']);

            $response = Http::withoutVerifying()->timeout(120)->withHeaders([
                'x-goog-api-key' => trim($key),
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.7],
            ]);

            if (! $response->successful()) {
                throw new RuntimeException("Erreur API Gemini : " . $response->body());
            }

            $html = $response->json('candidates.0.content.parts.0.text');
            $html = preg_replace('/```html|```/', '', $html);

            $brief['draft_content'] = trim($html);
            $drafts[] = $brief;
        }

        return [
            'drafts' => $drafts,
            'status' => 'content_drafted'
        ];
    }
}
