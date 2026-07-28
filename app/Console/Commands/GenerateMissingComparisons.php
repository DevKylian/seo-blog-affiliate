<?php

namespace App\Console\Commands;

use App\Models\ContentRun;
use App\Models\EditorialIdea;
use App\Models\EditorialPlan;
use App\Models\SeoProject;
use App\Services\ContentRunWorkerLauncher;
use Illuminate\Console\Command;

class GenerateMissingComparisons extends Command
{
    protected $signature = 'blog:generate-missing-comparisons {slug}';

    public function handle(ContentRunWorkerLauncher $launcher)
    {
        $slug = $this->argument('slug');
        $project = SeoProject::where('slug', $slug)->firstOrFail();
        
        $competitors = ['Pennylane', 'Dougs', 'Abby', 'Shine'];
        
        $plan = EditorialPlan::create([
            'seo_project_id' => $project->id,
            'name' => 'Génération des comparatifs pour ' . $project->name,
            'status' => 'generating',
            'requested_count' => count($competitors),
        ]);

        $ideas = [];
        foreach ($competitors as $index => $competitor) {
            $title = $project->name . ' vs ' . $competitor;
            $ideas[] = EditorialIdea::create([
                'editorial_plan_id' => $plan->id,
                'title' => $title,
                'thumbnail_title' => $title,
                'primary_keyword' => mb_strtolower($title),
                'entity_key' => $project->name . '/' . $competitor,
                'topic_key' => 'comparaison',
                'intent' => 'Commercial',
                'content_type' => 'comparison',
                'status' => 'accepted',
                'position' => $index + 1,
                'seo_score' => 90,
                'audience' => 'Indépendants/TPE',
                'problem' => "Quel outil choisir entre {$project->name} et {$competitor} ?",
                'expected_outcome' => "Comprendre les différences clés entre {$project->name} et {$competitor}.",
                'unique_promise' => "Le comparatif complet {$project->name} vs {$competitor}.",
                'funnel_stage' => 'decision',
            ]);
        }

        $run = ContentRun::create([
            'seo_project_id' => $project->id,
            'user_id' => 1,
            'editorial_plan_id' => $plan->id,
            'name' => 'Comparatifs ' . $project->name,
            'requested_count' => count($competitors),
            'status' => 'pending',
            'publication_days' => [],
        ]);

        foreach ($ideas as $idea) {
            $run->items()->create([
                'editorial_idea_id' => $idea->id,
                'content_type' => $idea->content_type,
                'status' => 'pending',
            ]);
        }

        $launcher->launch($run);

        $this->info("Génération lancée pour " . count($competitors) . " comparatifs.");
    }
}
