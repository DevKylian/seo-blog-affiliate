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
            $modified = false;
            
            // Règles de remplacement prudentes
            $replacements = [
                // Dépôt de capital
                '/Dépôt de capital.*?(?:69|69\s*€|69€)/ui' => 'Dépôt de capital à 1 € HT (avec un abonnement Pennylane)',
                '/69\s*€\s*pour\s*le\s*dépôt\s*de\s*capital/ui' => '1 € HT pour le dépôt de capital',
                
                // TPE
                '/TPE.*?à partir de\s*(?:99|99\s*€|99€)/ui' => 'TPE à partir de 89€',
                
                // Starter plan
                '/Starter.*?(?:14|14\s*€|14€)/ui' => 'Starter (7 € HT/mois)',
                
                // Pricing "à partir de"
                '/à partir de 14\s*€/ui' => 'à partir de 7 €',
                '/dès 14\s*€/ui' => 'dès 7 €',
            ];

            // 1. Process Body
            if (!empty($article->body)) {
                $newBody = $article->body;
                foreach ($replacements as $pattern => $replacement) {
                    $newBody = preg_replace($pattern, $replacement, $newBody);
                }
                if ($newBody !== $article->body) {
                    $article->body = $newBody;
                    $modified = true;
                }
            }

            // 2. Process Content Blocks (JSON)
            if (is_array($article->content_blocks)) {
                $newBlocks = $article->content_blocks;
                $blocksModified = false;
                
                array_walk_recursive($newBlocks, function (&$value, $key) use ($replacements, &$blocksModified) {
                    if (is_string($value)) {
                        $original = $value;
                        foreach ($replacements as $pattern => $replacement) {
                            $value = preg_replace($pattern, $replacement, $value);
                        }
                        if ($original !== $value) {
                            $blocksModified = true;
                        }
                    }
                });

                if ($blocksModified) {
                    $article->content_blocks = $newBlocks;
                    $modified = true;
                }
            }
            
            if ($modified) {
                // Remove verified_at to force re-verification if needed
                $article->verified_at = null;
                $article->save();
                $count++;
            }
        }

        $this->info("✔️  {$count} articles ont été mis à jour dans leur contenu (body/content_blocks).");
        $this->info("ℹ️  Note : " . $articles->count() . " articles au total parlent de Pennylane. Pensez à relancer des audits de contenu ou rafraîchissements si nécessaire.");
    }
}
