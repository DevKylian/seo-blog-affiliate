<?php

namespace App\Services;

use App\Models\KnowledgeEntity;
use App\Models\KnowledgeIntent;
use App\Models\KnowledgeScenario;
use App\Models\SeoProject;
use Illuminate\Support\Collection;

final class KnowledgeGraphStrategyBuilder
{
    public function generateSubjects(SeoProject $project, array $personas = []): Collection
    {
        $subjects = collect();
        
        // Fetch the Market Analysis / Product Profile
        $artifact = \App\Models\SeoArtifact::where('seo_project_id', $project->id)
            ->where('type', 'market_analysis')
            ->latest('version')
            ->first();

        if (!$artifact || empty($artifact->data)) {
            // Fallback to old behavior or return empty
            return collect();
        }

        $data = is_string($artifact->data) ? json_decode($artifact->data, true) : $artifact->data;
        
        // Generate Subjects based on the new Fiche IA
        $product = $data['product'] ?? [];
        $audience = $data['target_audience'] ?? [];
        $market = $data['market'] ?? [];
        $clusters = $data['semantic_clusters'] ?? [];

        // 1. Pillars
        foreach ($clusters['pillars'] ?? [] as $pillar) {
            $subjects->push([
                'title' => "Guide Complet : " . $pillar,
                'intent' => 'Pillar',
                'entity' => $product['name'] ?? $project->name,
                'persona' => $audience['icp'][0] ?? 'Général',
                'source' => 'knowledge_graph',
                'score' => 100 // Highest priority
            ]);
        }

        // 2. Satellites
        foreach ($clusters['satellites'] ?? [] as $satellite) {
            $subjects->push([
                'title' => $satellite,
                'intent' => 'Satellite',
                'entity' => $product['name'] ?? $project->name,
                'persona' => $audience['icp'][0] ?? 'Général',
                'source' => 'knowledge_graph',
                'score' => 70
            ]);
        }

        // 3. Comparatifs
        foreach ($market['direct_competitors'] ?? [] as $competitor) {
            $subjects->push([
                'title' => "Comparatif : " . ($product['name'] ?? $project->name) . " vs " . $competitor,
                'intent' => 'Commercial',
                'entity' => $product['name'] ?? $project->name,
                'persona' => $audience['icp'][0] ?? 'Général',
                'source' => 'knowledge_graph',
                'score' => 90
            ]);
        }

        // 4. Alternatives
        foreach ($market['direct_competitors'] ?? [] as $competitor) {
            $subjects->push([
                'title' => "Les meilleures alternatives à " . $competitor,
                'intent' => 'Commercial',
                'entity' => $competitor,
                'persona' => $audience['icp'][0] ?? 'Général',
                'source' => 'knowledge_graph',
                'score' => 85
            ]);
        }
        
        // 5. Pain points / Objections
        foreach ($audience['pain_points'] ?? [] as $pain) {
            $subjects->push([
                'title' => "Comment résoudre : " . $pain,
                'intent' => 'Informational',
                'entity' => $product['name'] ?? $project->name,
                'persona' => $audience['icp'][0] ?? 'Général',
                'source' => 'knowledge_graph',
                'score' => 60
            ]);
        }

        return $subjects->unique('title')->sortByDesc('score')->values();
    }


}
