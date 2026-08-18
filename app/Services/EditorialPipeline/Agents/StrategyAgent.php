<?php

namespace App\Services\EditorialPipeline\Agents;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use RuntimeException;
use Illuminate\Support\Str;

class StrategyAgent extends BaseAgent
{
    public function getName(): string
    {
        return 'strategy';
    }

    protected function process(array $inputData = []): array
    {
        $theme = $this->pipeline->theme;
        
        $key = Setting::value('gemini_api_key', config('services.gemini.key'));
        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('Clé API Gemini manquante. Configurez-la dans les réglages admin.');
        }

        $model = Setting::value('gemini_model', 'gemini-2.5-flash');
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $prompt = "Tu es un expert SEO et stratège de contenu pour le logiciel de comptabilité Indy.
Ta mission est d'établir la cartographie sémantique exhaustive (stratégie de contenu globale) pour la thématique : \"{$theme}\".
Indy cible les indépendants (freelances, micro-entrepreneurs, SASU, BNC, professions libérales).

Tu dois générer une carte complète de l'univers de cette niche avec des grands thèmes (clusters), des sous-thèmes, et les mots-clés racines associés à taper dans Semrush.
Renvoie UNIQUEMENT un objet JSON qui respecte très exactement ce schéma :
{
  \"request_id\": \"une chaine unique (ex: REQ-1234)\",
  \"theme\": \"{$theme}\",
  \"themes\": [
    {
      \"name\": \"Nom du Grand Thème (ex: Création d'activité)\",
      \"subthemes\": [\"sous-thème 1\", \"sous-thème 2\", \"...\"],
      \"seed_keywords\": [\"mot clé racine 1\", \"mot clé racine 2\", \"...\" (10 à 15 mots clés racines TRÈS GÉNÉRIQUES pour ce thème à taper dans Semrush)]
    }
  ],
  \"semrush_exports_needed\": [\"keyword_magic\", \"questions\", \"related\"],
  \"required_columns\": [\"keyword\", \"volume\", \"keyword_difficulty\", \"cpc\", \"intent\"],
  \"status\": \"waiting_for_external_data\"
}

Consignes strictes : 
1. Sois TRÈS EXHAUSTIF. Ne te contente pas de 'comptabilité freelance'. Explore : Statuts juridiques, Fiscalité, Facturation, Banque, Métiers, Obligations, etc.
2. Crée au moins 6 à 10 'themes' (Grands clusters).
3. Pour chaque thème, trouve 5 à 10 'subthemes'.
4. Pour chaque thème, génère 10 à 20 'seed_keywords' (mots très courts, 2-3 mots max, ex: 'tva freelance', 'statut sasu', 'compte pro') destinés à être tapés dans le Keyword Magic Tool de Semrush.";

        Log::info("StrategyAgent appel API Gemini pour la thématique: {$theme}");

        $response = Http::withoutVerifying()->timeout(45)->withHeaders([
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
        
        // Nettoyer les éventuels backticks markdown
        $text = preg_replace('/```json|```/', '', $text);
        
        $data = json_decode(trim($text), true);

        if (! is_array($data) || ! isset($data['themes'])) {
            throw new RuntimeException('Gemini n\'a pas retourné le JSON attendu (clé "themes" manquante).');
        }

        // Forcer le request_id pour être sûr
        $data['request_id'] = 'REQ-' . $this->pipeline->id . '-' . Str::random(5);
        $data['status'] = 'waiting_for_external_data';

        return $data;
    }
}
