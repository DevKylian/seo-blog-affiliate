<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiEmbeddingService
{
    /**
     * @param string $text Le texte à vectoriser (ex: le titre de l'article)
     * @return float[]|null Le vecteur (1024 ou 768 dimensions selon le modèle), null si échec
     */
    public function embed(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $apiKey = config('services.gemini.key');
        if (! $apiKey) {
            Log::error('Clé API Gemini manquante pour GeminiEmbeddingService.');
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent?key={$apiKey}";

        $response = Http::post($url, [
            'model' => 'models/text-embedding-004',
            'content' => [
                'parts' => [
                    ['text' => $text],
                ],
            ],
            'taskType' => 'SEMANTIC_SIMILARITY',
        ]);

        if (! $response->successful()) {
            $error = $response->json('error.message', 'Erreur inconnue');
            Log::error("Échec génération d'embedding Gemini: {$error}", ['status' => $response->status(), 'text' => $text]);
            return null;
        }

        $embedding = $response->json('embedding.values');
        
        if (! is_array($embedding) || empty($embedding)) {
            Log::error("Format de réponse d'embedding inattendu.", ['response' => $response->json()]);
            return null;
        }

        return $embedding;
    }

    /**
     * Calcule la similarité cosinus entre deux vecteurs.
     * 
     * @param float[] $vecA
     * @param float[] $vecB
     * @return float Score entre -1.0 et 1.0 (ou 0 si erreur)
     */
    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        if (empty($vecA) || empty($vecB) || count($vecA) !== count($vecB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($vecA as $i => $valA) {
            $valB = $vecB[$i] ?? 0.0;
            $dotProduct += $valA * $valB;
            $normA += $valA * $valA;
            $normB += $valB * $valB;
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
