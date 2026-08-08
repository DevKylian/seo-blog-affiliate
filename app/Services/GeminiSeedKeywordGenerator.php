<?php

namespace App\Services;

use App\Models\SeoProject;
use App\Models\SourcePage;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class GeminiSeedKeywordGenerator
{
    /** @return array<int, string> */
    public function generate(SeoProject $project): array
    {
        $scrapedData = '';
        $homepage = SourcePage::query()
            ->where('seo_project_id', $project->id)
            ->where('type', 'homepage')
            ->where('status', 'verified')
            ->first();

        if ($homepage) {
            $scrapedData = $homepage->evidenceChunks()
                ->take(15)
                ->pluck('value')
                ->implode("\n");
        }

        $features = implode(', ', (array) $project->features);
        $positioning = $project->positioning ?? '';
        $name = $project->name;
        $url = $project->website_url ?? $project->affiliate_url ?? '';

        $prompt = <<<PROMPT
Tu es un expert SEO senior chargé de définir la stratégie de mots-clés d'un site d'affiliation B2B/SaaS.
Ton objectif est de générer une liste de "Seed Keywords" (requêtes racines très courtes et larges) pertinents pour le projet suivant :

Nom du projet (Produit cible) : {$name}
URL : {$url}
Positionnement : {$positioning}
Fonctionnalités clés : {$features}

Données extraites du site (Optionnel) :
{$scrapedData}

À partir de ces informations, fournis exactement 10 termes de recherche larges (Seed Keywords) que je pourrai copier-coller dans l'outil de recherche de Semrush pour générer des idées de mots-clés de longue traîne.
Exemples de Seed Keywords pertinents pour un outil comptable : "logiciel facturation", "comptabilité en ligne", "comparatif compte pro", "devis artisan", etc.

RÈGLES BLOQUANTES :
- Ne fournis QUE des requêtes racines courtes (1 à 3 mots, rarement 4).
- Les requêtes doivent avoir un fort potentiel de volume de recherche.
- N'inclus aucune longue traîne spécifique (pas de questions).
- Assure-toi que les thématiques couvrent tout le périmètre fonctionnel du produit (ex: si le produit fait devis, factures, et CRM, donne des seeds pour ces trois domaines).
- N'INVENTE JAMAIS de noms de logiciels B2B fictifs (ex: pas de "InvoiceMaster", "QuickBill", "AccountPro"). Limite-toi aux mots génériques ou aux marques RÉELLES très connues si pertinent.
PROMPT;

        $response = $this->request()->post($this->endpoint(), [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 4096,
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'seeds' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'required' => ['seeds'],
                ],
            ],
        ]);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?: 'Erreur Gemini sans détail.';
            throw new RuntimeException("Gemini HTTP {$response->status()} : {$message}");
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        
        if (is_string($text)) {
            // Nettoyage Markdown
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
            // Nettoyage des virgules terminales (trailing commas)
            $text = preg_replace('/,\s*([\]}])/m', '$1', $text);
            // Nettoyage des caractères de contrôle non échappés
            $text = preg_replace('/[\x00-\x1F]/', '', $text);
            
            file_put_contents(storage_path('logs/debug_gemini.json'), $text);
        }

        $data = is_string($text) ? json_decode($text, true) : null;
        
        if (! is_array($data) || ! isset($data['seeds']) || ! is_array($data['seeds'])) {
            $err = json_last_error_msg();
            throw new RuntimeException("Gemini JSON error: {$err}.");
        }

        return array_map('strval', $data['seeds']);
    }

    private function request(): PendingRequest
    {
        $key = Setting::value('gemini_api_key', config('services.gemini.key'));
        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('Ajoutez d’abord votre clé API Gemini dans Réglages.');
        }

        return Http::withoutVerifying()->withHeaders([
                'x-goog-api-key' => trim($key),
                'Content-Type' => 'application/json',
            ])
            ->timeout(60)
            ->retry(2, 500);
    }

    private function endpoint(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    }
}
