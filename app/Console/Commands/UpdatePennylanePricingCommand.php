<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Plan;
use App\Models\SeoProject;
use App\Models\Article;

class UpdatePennylanePricingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-pennylane-pricing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Met à jour les tarifs et les offres de Pennylane (plans et articles)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🚀 Début de la mise à jour des informations Pennylane...");

        $this->updatePlans();
        $this->updateArticles();

        $this->info("✅ Mise à jour terminée avec succès !");
        $this->info("👉 Vous pouvez maintenant vérifier les articles modifiés en production.");
    }

    private function updatePlans()
    {
        $this->info("⏳ Mise à jour des Plans (SeoProject & Competitors)...");
        
        $newPlans = [
            [
                'name' => 'Gratuit 🆓',
                'monthly_price' => 0.0,
                'features' => ['Conformité Facturation électronique', 'Facturation simplifiée', 'Jusqu\'à 1 200 factures/an'],
            ],
            [
                'name' => 'Starter',
                'monthly_price' => 7.0,
                'features' => ['Tout Gratuit', 'Compte Pro & cartes inclus', 'Gestion des achats'],
            ],
            [
                'name' => 'Basique',
                'monthly_price' => 14.0,
                'features' => ['Tout Starter', 'Gestion complète ventes/achats', '2 modèles perso'],
            ],
            [
                'name' => 'Essentiel ⭐',
                'monthly_price' => 24.0,
                'features' => ['Tout Basique', 'Notes de frais', 'Prévisionnel de trésorerie', 'Rapports analytiques', '5 modèles'],
            ],
            [
                'name' => 'Premium',
                'monthly_price' => 79.0,
                'features' => ['Tout Essentiel', 'Comptabilité complète', 'Révision, clôtures, déclarations EDI', 'Comptes annuels'],
            ]
        ];

        // 1. Projects named Pennylane
        $pennylaneProjects = SeoProject::where('name', 'like', 'Pennylane%')->get();
        foreach ($pennylaneProjects as $project) {
            $project->plans()->delete();
            $position = 1;
            foreach ($newPlans as $planData) {
                $project->plans()->create([
                    'name' => $planData['name'],
                    'monthly_price' => $planData['monthly_price'],
                    'features' => $planData['features'],
                    'position' => $position++,
                    'is_active' => true,
                    'tax_included' => false,
                    'currency' => 'EUR',
                ]);
            }
            $this->info("✔️  Plans mis à jour pour le projet : {$project->name}");
        }

        // 2. Competitor plans named Pennylane
        $competitorProjects = SeoProject::whereHas('competitorPlans', function($q) {
            $q->where('competitor_name', 'like', 'Pennylane%');
        })->get();

        foreach ($competitorProjects as $project) {
            $project->competitorPlans()->where('competitor_name', 'like', 'Pennylane%')->delete();
            
            $position = 1;
            foreach ($newPlans as $planData) {
                $project->pricingPlans()->create([
                    'competitor_name' => 'Pennylane',
                    'name' => $planData['name'],
                    'monthly_price' => $planData['monthly_price'],
                    'features' => $planData['features'],
                    'position' => $position++,
                    'is_active' => true,
                    'tax_included' => false,
                    'currency' => 'EUR',
                ]);
            }
            $this->info("✔️  Plans concurrents Pennylane mis à jour pour : {$project->name}");
        }
    }

    private function updateArticles()
    {
        $this->info("⏳ Recherche d'articles mentionnant Pennylane...");
        
        $articles = Article::where(function($q) {
            $q->where('body', 'like', '%Pennylane%')
              ->orWhere('content_blocks', 'like', '%Pennylane%');
        })->get();

        $count = 0;
        foreach ($articles as $article) {
            \App\Models\ContentRefreshTask::firstOrCreate([
                'article_id' => $article->id,
                'reason' => 'Mise à jour des tarifs et offres Pennylane 2026',
                'status' => 'queued',
            ], [
                'seo_project_id' => $article->seo_project_id,
                'priority' => 80,
                'scheduled_at' => now(),
                'payload' => [
                    'context' => 'Update Pennylane pricing: Starter 7€, Basique 14€, Essentiel 24€, Premium 79€. TPE 89€. Dépôt capital 1€.'
                ]
            ]);
            $count++;
        }

        $this->info("✔️  {$count} articles ont été mis en file d'attente pour un rafraîchissement de contenu (ContentRefreshTask).");
        $this->info("ℹ️  Note : L'IA va repasser sur ces articles pour corriger les prix automatiquement.");
    }
}
