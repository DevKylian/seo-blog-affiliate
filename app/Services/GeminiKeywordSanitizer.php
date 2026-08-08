<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class GeminiKeywordSanitizer
{
    /**
     * Filtre un tableau de mots-clés via Gemini.
     * @param string[] $keywords
     * @return string[] Le tableau des mots-clés approuvés
     */
    public function sanitize(array $keywords): array
    {
        if (empty($keywords)) {
            return [];
        }

        $approved = [];
        $chunks = array_chunk(array_values(array_unique($keywords)), 100);

        foreach ($chunks as $chunk) {
            $approved = array_merge($approved, $this->processChunk($chunk));
        }

        return array_values(array_unique($approved));
    }

    private function processChunk(array $keywords): array
    {
        $keywordsJson = json_encode($keywords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $prompt = <<<PROMPT
Tu es un expert SEO ultra-sélectif pour un logiciel dédié EXCLUSIVEMENT aux indépendants et petites entreprises (TPE).
Voici une liste de mots-clés bruts extraite de Semrush. Ton seul travail est de les évaluer un par un.

RÈGLE D'OR : Tu dois définir "keep": true SI ET SEULEMENT SI le sujet du mot-clé fait partie d'une de ces catégories (niche Indy) :
1. Comptabilité & Liasse Fiscale
2. Facturation & Devis
3. Création d'entreprise & Statuts juridiques (micro-entreprise, SASU, etc.)
4. Compte Bancaire Professionnel (exclusivité bancaire)

REJET STRICT ("keep": false) :
- Si le mot-clé traite d'un autre domaine (RH, immobilier, transport, énergie, médical, e-commerce, annonces, etc.)
- Si le mot-clé mentionne un nom de logiciel, marque ou service qui NE FIGURE PAS dans cette liste de marques connues du secteur : Indy, Abby, Dougs, Pennylane, Tiime, Qonto, Shine, Boursorama, Revolut, Blank, BNP Paribas, Société Générale, LCL, Crédit Agricole, Caisse d'Épargne, La Banque Postale, La Poste, CMB, Sage, EBP, Ciel, Henrri, Free Pro. Si un nom de marque t'est inconnu ou te semble inventé/composite (ex: "InvoiceMaster", "EasyCompta Business", "FinancePro Plus"), REJETTE-le même s'il contient un mot de la niche comme "facturation" ou "comptabilité" — un mot-clé niche avec une marque inventée reste un mot-clé invalide.
- Si le mot-clé semble être un identifiant technique interne plutôt qu'une requête humaine réelle (mots comme "interpreteur", "module", ou toute chaîne qui ne ressemble pas à une façon naturelle de chercher sur Google), REJETTE-le.

IMPORTANT : Un mot-clé contenant "facturation", "comptabilité", "devis", "banque pro", "sasu" ou "auto-entrepreneur" doit être analysé avec attention plutôt que rejeté par réflexe pour hors-sujet — mais cela NE DISPENSE JAMAIS de vérifier la validité de la marque/l'entité mentionnée selon la règle ci-dessus. La présence d'un mot de la niche n'est pas une garantie de validité.

Renvoie un tableau JSON contenant TOUS les mots-clés fournis en entrée, avec la valeur "keep" appropriée pour chacun, et un champ "reason" court expliquant le rejet le cas échéant.

Mots-clés à évaluer :
{$keywordsJson}
PROMPT;

        $response = $this->request()->post($this->endpoint(), [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => ['type' => 'string'],
                            'keep' => ['type' => 'boolean'],
                            'reason' => ['type' => 'string']
                        ],
                        'required' => ['keyword', 'keep']
                    ],
                ],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('GeminiKeywordSanitizer error', ['status' => $response->status(), 'error' => $response->json('error.message')]);
            throw new RuntimeException("Erreur Gemini lors du nettoyage des mots-clés : " . ($response->json('error.message') ?: 'Erreur inconnue'));
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $results = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($results)) {
            Log::error('GeminiKeywordSanitizer parse error', ['text' => $text]);
            return [];
        }

        $approved = [];
        foreach ($results as $item) {
            if (!empty($item['keep']) && !empty($item['keyword'])) {
                $approved[] = $item['keyword'];
            }
        }

        return $approved;
    }

    private function request(): PendingRequest
    {
        $key = Setting::value('gemini_api_key', config('services.gemini.key'));
        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('Ajoutez d’abord votre clé API Gemini dans Réglages.');
        }

        return Http::timeout(45)->connectTimeout(8)->retry(3, 2000, function ($exception, $request) {
            if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                return $exception->response->status() >= 500 || $exception->response->status() === 429;
            }
            return true; // Connection timeout, etc.
        })->withHeaders([
            'x-goog-api-key' => trim($key),
            'Content-Type' => 'application/json',
        ]);
    }

    private function endpoint(): string
    {
        $model = (string) Setting::value('gemini_model', 'gemini-2.5-flash-lite');
        if (! in_array($model, ['gemini-2.5-flash-lite', 'gemini-2.5-flash'], true)) {
            $model = 'gemini-2.5-flash-lite';
        }

        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
    }
}
