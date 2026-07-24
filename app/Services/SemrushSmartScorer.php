<?php

namespace App\Services;

use App\Models\Keyword;
use App\Models\SeoProject;

class SemrushSmartScorer
{
    /**
     * Calcule le SEO Score Prioritaire selon la formule complète du SEO Strategist :
     * Volume × Business Value × Conversion × KD_inverse × Topical Authority × Intent
     */
    public function calculateScore(Keyword $keyword, SeoProject $project): float
    {
        $volume = max((float) $keyword->search_volume, 1.0);
        $kd = max((float) $keyword->keyword_difficulty, 1.0);
        $kdInverse = 100 / $kd; // Plus le KD est bas, plus le multiplicateur est élevé

        // Intent Multiplier
        $intent = mb_strtolower($keyword->intent);
        $intentMultiplier = match (true) {
            str_contains($intent, 'transaction') || str_contains($intent, 'commercial') => 2.5,
            str_contains($intent, 'navigation') => 1.8,
            str_contains($intent, 'investigation') => 1.5,
            default => 1.0,
        };

        // Business Value & Conversion Proxy (via Affiliate Intent)
        $affiliate = preg_match('/avis|prix|tarif|vs|alternative|meilleur|promo|essai|devis|comparatif/iu', $keyword->keyword);
        $businessValue = $affiliate ? 2.0 : 1.0;
        $conversion = $affiliate ? 1.5 : 0.8;

        // Topical Authority Proxy (Relevance to Brand/Pillars)
        $relevance = stripos($keyword->keyword, $project->name) !== false ? 2.0 : 1.2;
        $topicalAuthority = $relevance;

        // Final Score Formula
        $rawScore = $volume * $businessValue * $conversion * $kdInverse * $topicalAuthority * $intentMultiplier;

        // Normalize (logarithmic scale) to keep scores between 0 and 100 roughly
        return min(100.0, max(0.0, log10(max($rawScore, 1)) * 15));
    }
}
