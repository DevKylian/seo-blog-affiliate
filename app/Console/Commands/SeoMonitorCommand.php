<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Article;
use App\Models\SeoTask;

class SeoMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyse les performances SEO et génère des tâches d\'optimisation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Démarrage de l\'analyse SEO...');
        
        $articles = Article::where('status', 'published')->get();
        
        foreach ($articles as $article) {
            // Simulation d'une métrique faible pour déclencher une tâche
            $needsUpdate = rand(1, 100) > 80; // 20% de chance d'avoir un problème détecté
            
            if ($needsUpdate) {
                $priority = rand(1, 3);
                $actions = ['rewrite_title', 'add_faq', 'reinforce_internal_links'];
                $action = $actions[array_rand($actions)];
                
                SeoTask::create([
                    'seo_project_id' => $article->seo_project_id,
                    'url' => $article->slug,
                    'action_type' => $action,
                    'priority' => $priority,
                    'status' => 'pending',
                    'metrics_snapshot' => [
                        'impressions' => rand(100, 5000),
                        'clicks' => rand(0, 50),
                        'position' => rand(11, 50)
                    ]
                ]);
                
                $this->line("Tâche créée pour {$article->slug}: {$action} (Priorité {$priority})");
            }
        }
        
        $this->info('Analyse SEO terminée.');
    }
}
