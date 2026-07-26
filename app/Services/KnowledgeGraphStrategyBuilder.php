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
        if (mb_strtolower($project->name) === 'businesskit') {
            return collect($this->businessKitStrategy())->map(function ($subject) {
                $subject['source'] = 'knowledge_graph';
                return $subject;
            });
        }

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

    private function businessKitStrategy(): array
    {
        return [
            // TOP 10 STRICT PRIORITY (Score 100 to 91)
            ['title' => 'Logiciel de gestion comptable', 'intent' => 'Pillar', 'entity' => 'Comptabilité', 'persona' => 'Indépendants/TPE', 'score' => 100],
            ['title' => 'Micro-entreprise', 'intent' => 'Pillar', 'entity' => 'Statut', 'persona' => 'Micro-entrepreneurs', 'score' => 99],
            ['title' => 'Logiciel de facturation', 'intent' => 'Pillar', 'entity' => 'Facturation', 'persona' => 'Indépendants', 'score' => 98],
            ['title' => 'Compte pro en ligne', 'intent' => 'Pillar', 'entity' => 'Banque', 'persona' => 'Créateurs/TPE', 'score' => 97],
            ['title' => 'Signature électronique', 'intent' => 'Pillar', 'entity' => 'Démarches', 'persona' => 'Entreprises', 'score' => 96],
            ['title' => 'Indy avis', 'intent' => 'Commercial', 'entity' => 'Indy', 'persona' => 'Indépendants', 'score' => 95],
            ['title' => 'Indy vs Abby', 'intent' => 'Commercial', 'entity' => 'Indy/Abby', 'persona' => 'Micro-entrepreneurs', 'score' => 94],
            ['title' => 'Indy vs Freebe', 'intent' => 'Commercial', 'entity' => 'Indy/Freebe', 'persona' => 'Freelances', 'score' => 93],
            ['title' => 'Calculateur TVA', 'intent' => 'Outil', 'entity' => 'TVA', 'persona' => 'Général', 'score' => 92],
            ['title' => 'Calculateur TJM', 'intent' => 'Outil', 'entity' => 'TJM', 'persona' => 'Freelances', 'score' => 91],

            // OTHER PILLARS (Score 90)
            ['title' => 'Création d’entreprise', 'intent' => 'Pillar', 'entity' => 'Statut', 'persona' => 'Créateurs', 'score' => 90],
            ['title' => 'Méthodologie / Transparence', 'intent' => 'Pillar', 'entity' => 'BusinessKit', 'persona' => 'Général', 'score' => 90],
            ['title' => 'Outils gratuits', 'intent' => 'Pillar', 'entity' => 'Outils', 'persona' => 'Général', 'score' => 90],

            // MONEY PAGES - LOGICIELS (Score 85)
            ['title' => 'Abby avis', 'intent' => 'Commercial', 'entity' => 'Abby', 'persona' => 'Micro', 'score' => 85],
            ['title' => 'Freebe avis', 'intent' => 'Commercial', 'entity' => 'Freebe', 'persona' => 'Freelance', 'score' => 85],
            ['title' => 'Pennylane avis', 'intent' => 'Commercial', 'entity' => 'Pennylane', 'persona' => 'TPE', 'score' => 85],
            ['title' => 'Qonto avis', 'intent' => 'Commercial', 'entity' => 'Qonto', 'persona' => 'TPE', 'score' => 85],
            ['title' => 'Shine avis', 'intent' => 'Commercial', 'entity' => 'Shine', 'persona' => 'Indépendants', 'score' => 85],
            ['title' => 'Blank avis', 'intent' => 'Commercial', 'entity' => 'Blank', 'persona' => 'Indépendants', 'score' => 85],
            ['title' => 'Indy tarifs', 'intent' => 'Commercial', 'entity' => 'Indy', 'persona' => 'Indépendants', 'score' => 85],
            ['title' => 'Indy alternatives', 'intent' => 'Commercial', 'entity' => 'Indy', 'persona' => 'Indépendants', 'score' => 85],
            ['title' => 'Logiciel comptable freelance', 'intent' => 'Commercial', 'entity' => 'Comptabilité', 'persona' => 'Freelance', 'score' => 85],
            ['title' => 'Logiciel comptable micro-entreprise', 'intent' => 'Commercial', 'entity' => 'Comptabilité', 'persona' => 'Micro', 'score' => 85],
            ['title' => 'Meilleur logiciel comptable', 'intent' => 'Commercial', 'entity' => 'Comptabilité', 'persona' => 'Général', 'score' => 85],

            // MONEY PAGES - COMPTE PRO (Score 84)
            ['title' => 'Compte pro gratuit', 'intent' => 'Commercial', 'entity' => 'Banque', 'persona' => 'Général', 'score' => 84],
            ['title' => 'Compte pro SASU', 'intent' => 'Commercial', 'entity' => 'Banque', 'persona' => 'SASU', 'score' => 84],
            ['title' => 'Compte pro micro-entreprise', 'intent' => 'Commercial', 'entity' => 'Banque', 'persona' => 'Micro', 'score' => 84],
            ['title' => 'Meilleur compte pro', 'intent' => 'Commercial', 'entity' => 'Banque', 'persona' => 'Général', 'score' => 84],
            ['title' => 'Banque pro freelance', 'intent' => 'Commercial', 'entity' => 'Banque', 'persona' => 'Freelance', 'score' => 84],

            // MONEY PAGES - SIGNATURE ELECTRONIQUE (Score 83)
            ['title' => 'Signature électronique gratuite', 'intent' => 'Commercial', 'entity' => 'Signature', 'persona' => 'Général', 'score' => 83],
            ['title' => 'Logiciel signature électronique', 'intent' => 'Commercial', 'entity' => 'Signature', 'persona' => 'Général', 'score' => 83],
            ['title' => 'Signature électronique PDF', 'intent' => 'Commercial', 'entity' => 'Signature', 'persona' => 'Général', 'score' => 83],
            ['title' => 'Comment signer un PDF électroniquement', 'intent' => 'Informational', 'entity' => 'Signature', 'persona' => 'Général', 'score' => 83],

            // COMPARATIFS PRIORITAIRES (Score 80)
            ['title' => 'Indy vs Pennylane', 'intent' => 'Commercial', 'entity' => 'Indy/Pennylane', 'persona' => 'Indépendants/TPE', 'score' => 80],
            ['title' => 'Abby vs Freebe', 'intent' => 'Commercial', 'entity' => 'Abby/Freebe', 'persona' => 'Micro/Freelance', 'score' => 80],
            ['title' => 'Pennylane vs Qonto', 'intent' => 'Commercial', 'entity' => 'Pennylane/Qonto', 'persona' => 'TPE', 'score' => 80],
            ['title' => 'Shine vs Qonto', 'intent' => 'Commercial', 'entity' => 'Shine/Qonto', 'persona' => 'TPE', 'score' => 80],
            ['title' => 'Indy vs Qonto', 'intent' => 'Commercial', 'entity' => 'Indy/Qonto', 'persona' => 'Indépendants', 'score' => 80],
            ['title' => 'Indy vs Shine', 'intent' => 'Commercial', 'entity' => 'Indy/Shine', 'persona' => 'Indépendants', 'score' => 80],
            ['title' => 'Qonto vs Blank', 'intent' => 'Commercial', 'entity' => 'Qonto/Blank', 'persona' => 'Indépendants', 'score' => 80],
            ['title' => 'Freebe vs Pennylane', 'intent' => 'Commercial', 'entity' => 'Freebe/Pennylane', 'persona' => 'Freelances', 'score' => 80],
        ];
    }


}
