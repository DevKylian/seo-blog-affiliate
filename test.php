<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $project = App\Models\SeoProject::first();
    $gen = app(App\Services\GeminiSeedKeywordGenerator::class);
    
    $scrapedData = '';
    $homepage = App\Models\SourcePage::query()
        ->where('seo_project_id', $project->id)
        ->where('type', 'homepage')
        ->where('status', 'verified')
        ->first();

    if ($homepage) {
        $scrapedData = $homepage->evidenceChunks()->take(15)->pluck('value')->implode("\n");
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
PROMPT;

    $key = App\Models\Setting::value('gemini_api_key', config('services.gemini.key'));

    $response = Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
        'x-goog-api-key' => trim($key),
        'Content-Type' => 'application/json',
    ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', [
        'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 1024,
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
    
    file_put_contents('debug_full.json', $response->body());
    dump("Saved full response to debug_full.json");
    
} catch (\Throwable $e) {
    dump("Error: " . $e->getMessage());
}
