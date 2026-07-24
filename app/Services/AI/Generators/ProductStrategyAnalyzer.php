<?php

namespace App\Services\AI\Generators;

use App\Models\SeoProject;
use App\Models\SeoArtifact;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductStrategyAnalyzer
{
    /**
     * Analyze market and generate JSON artifact
     */
    public function generate(SeoProject $project): SeoArtifact
    {
        $hash = md5($project->name . $project->website_url . json_encode($project->competitors) . 'v1');
        
        $existing = SeoArtifact::where('seo_project_id', $project->id)
            ->where('type', 'market_analysis')
            ->where('hash', $hash)
            ->first();

        if ($existing) {
            return $existing;
        }

        $prompt = $this->buildPrompt($project);
        $response = $this->callGeminiPro($prompt);

        return SeoArtifact::create([
            'seo_project_id' => $project->id,
            'type' => 'market_analysis',
            'data' => $response,
            'version' => 1,
            'hash' => $hash,
        ]);
    }

    private function buildPrompt(SeoProject $project): string
    {
        return <<<PROMPT
Tu es un "SEO Strategist" expert en affiliation. Ton but n'est pas de générer un article, mais de comprendre en profondeur le produit pour lequel on souhaite construire un cocon sémantique.
Analyse la niche suivante et retourne UNIQUEMENT un objet JSON très complet.

Projet / Produit cible : {$project->name}
URL : {$project->website_url}
Pays : {$project->country}

Fais des recherches dans tes connaissances internes sur ce produit (ex: Indy) et sur son marché.

Structure JSON attendue STRICTEMENT (ne renvoie rien d'autre) :
{
 "product": {
   "name": "string",
   "category": "string",
   "sub_category": "string",
   "promise": "string (La promesse unique, ex: La comptabilité automatique des indépendants)",
   "usp": ["string"],
   "benefits": ["string"]
 },
 "target_audience": {
   "icp": ["string (Ex: Freelance, Auto-entrepreneur, SASU)"],
   "pain_points": ["string (Ex: faire sa compta, perte de temps, expert trop cher)"],
   "objections": ["string (Ex: est-ce fiable ?, compatible ?, TVA ?)"]
 },
 "market": {
   "direct_competitors": ["string"],
   "indirect_competitors": ["string"]
 },
 "search_intents": {
   "commercial": ["string"],
   "informational": ["string"],
   "transactional": ["string"],
   "navigational": ["string"]
 },
 "semantic_clusters": {
   "pillars": ["string (Les grandes thématiques piliers, ex: Logiciel comptabilité, Création entreprise)"],
   "satellites": ["string (Les sous-sujets satellites, ex: Comment déclarer sa TVA, Logiciel de devis)"]
 }
}
PROMPT;
    }

    private function callGeminiPro(string $prompt): array
    {
        $key = \App\Models\Setting::value('gemini_api_key', config('services.gemini.key'));
        
        if (empty($key)) {
            throw new \Exception('Clé API Gemini non configurée dans les paramètres.');
        }

        $model = \App\Models\Setting::value('gemini_model', 'gemini-2.5-flash'); 
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";

        $response = Http::timeout(60)->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if (!$response->successful()) {
            Log::error('Gemini API Error: ' . $response->body());
            throw new \Exception('Failed to call Gemini API.');
        }

        $jsonStr = $response->json('candidates.0.content.parts.0.text');
        return json_decode($jsonStr, true) ?? [];
    }
}
