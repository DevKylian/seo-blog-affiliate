<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\ContentRun;
use App\Models\EditorialIdea;
use App\Models\EditorialPlan;
use App\Models\SeoProject;
use App\Services\ContentRunWorkerLauncher;
use App\Services\GeminiContentGenerator;
use Illuminate\Support\Str;
use Livewire\Component;

class GenerateComparisons extends Component
{
    public string $message = '';
    public string $messageType = 'success';

    public function generateAllMissing()
    {
        $launcher = app(ContentRunWorkerLauncher::class);
        $projects = SeoProject::where('status', 'active')->get();
        $competitorMap = [
            'indy' => ['Pennylane', 'Dougs', 'Abby', 'Shine'],
            'pennylane' => ['Indy', 'Dougs', 'Abby', 'Shine', 'Qonto'],
            'dougs' => ['Indy', 'Pennylane', 'Abby', 'Shine'],
            'shine' => ['Indy', 'Qonto', 'Pennylane', 'Dougs'],
            'abby' => ['Indy', 'Pennylane', 'Dougs'],
        ];

        $totalLaunched = 0;

        foreach ($projects as $project) {
            $competitors = $competitorMap[$project->slug] ?? ['Pennylane', 'Dougs', 'Abby', 'Shine'];
            $missingCompetitors = [];

            foreach ($competitors as $competitor) {
                $duelSlug1 = Str::slug($project->name . ' vs ' . $competitor);
                $duelSlug2 = Str::slug($competitor . ' vs ' . $project->name);

                $exists = Article::whereIn('slug', [$duelSlug1, $duelSlug2])->exists();
                if (!$exists) {
                    $missingCompetitors[] = $competitor;
                }
            }

            if (empty($missingCompetitors)) {
                continue;
            }

            $plan = EditorialPlan::create([
                'seo_project_id' => $project->id,
                'name' => 'Génération comparatifs pour ' . $project->name,
                'status' => 'generating',
                'requested_count' => count($missingCompetitors),
            ]);

            $ideas = [];
            foreach ($missingCompetitors as $index => $competitor) {
                $title = $project->name . ' vs ' . $competitor;
                $ideas[] = EditorialIdea::create([
                    'editorial_plan_id' => $plan->id,
                    'title' => $title,
                    'thumbnail_title' => $title,
                    'primary_keyword' => mb_strtolower($title),
                    'entity_key' => $project->name . '/' . $competitor,
                    'topic_key' => 'comparaison',
                    'intent' => 'Commercial',
                    'angle' => "Comparatif direct entre {$project->name} et {$competitor}",
                    'content_type' => 'comparison',
                    'status' => 'accepted',
                    'position' => $index + 1,
                    'seo_score' => 90,
                    'audience' => 'Indépendants/TPE',
                    'problem' => "Quel outil choisir entre {$project->name} et {$competitor} ?",
                    'expected_outcome' => "Comprendre les différences clés entre {$project->name} et {$competitor}.",
                    'unique_promise' => "Le comparatif complet {$project->name} vs {$competitor}.",
                    'funnel_stage' => 'decision',
                    'excluded_topics' => [],
                    'outline' => ["Verdict rapide", "Tableau comparatif", "Analyse détaillée", "Tarifs comparés", "FAQ"],
                    'fingerprint' => mb_strtolower($title . '|comparatif|decision'),
                ]);
            }

            $run = ContentRun::create([
                'seo_project_id' => $project->id,
                'user_id' => auth()->id() ?? 1,
                'editorial_plan_id' => $plan->id,
                'name' => 'Comparatifs ' . $project->name,
                'requested_count' => count($missingCompetitors),
                'status' => 'pending',
                'publication_days' => null,
            ]);

            foreach ($ideas as $idea) {
                $run->items()->create([
                    'editorial_idea_id' => $idea->id,
                    'content_type' => $idea->content_type,
                    'status' => 'pending',
                ]);
            }

            $launcher->launch($run->id);
            $totalLaunched += count($missingCompetitors);
        }

        if ($totalLaunched > 0) {
            $this->message = "✨ {$totalLaunched} comparatifs manquants ont été lancés en arrière-plan !";
            $this->messageType = 'success';
        } else {
            $this->message = "Tous les comparatifs sont déjà générés et en ligne !";
            $this->messageType = 'info';
        }
    }

    public function generateSingle(string $projectSlug, string $competitor)
    {
        $project = SeoProject::where('slug', $projectSlug)->firstOrFail();
        $title = $project->name . ' vs ' . $competitor;
        $slug = Str::slug($title);

        $existingArticle = Article::where('slug', $slug)->orWhere('slug', Str::slug($competitor . ' vs ' . $project->name))->first();
        if ($existingArticle) {
            $this->message = "L'article pour {$title} existe déjà.";
            $this->messageType = 'info';
            return;
        }

        $plan = EditorialPlan::create([
            'seo_project_id' => $project->id,
            'name' => 'Comparatif ' . $title,
            'status' => 'generating',
            'requested_count' => 1,
        ]);

        $idea = EditorialIdea::create([
            'editorial_plan_id' => $plan->id,
            'title' => $title,
            'thumbnail_title' => $title,
            'primary_keyword' => mb_strtolower($title),
            'entity_key' => $project->name . '/' . $competitor,
            'topic_key' => 'comparaison',
            'intent' => 'Commercial',
            'angle' => "Comparatif direct entre {$project->name} et {$competitor}",
            'content_type' => 'comparison',
            'status' => 'accepted',
            'position' => 1,
            'seo_score' => 90,
            'audience' => 'Indépendants/TPE',
            'problem' => "Quel outil choisir entre {$project->name} et {$competitor} ?",
            'expected_outcome' => "Comprendre les différences clés entre {$project->name} et {$competitor}.",
            'unique_promise' => "Le comparatif complet {$project->name} vs {$competitor}.",
            'funnel_stage' => 'decision',
            'excluded_topics' => [],
            'outline' => ["Verdict rapide", "Tableau comparatif", "Analyse", "Tarifs", "FAQ"],
            'fingerprint' => mb_strtolower($title . '|comparatif|decision'),
        ]);

        try {
            $generator = app(GeminiContentGenerator::class);
            $article = $generator->generateFromIdea($project, $idea);
            $article->update(['status' => 'published', 'published_at' => now()]);

            $this->message = "✅ L'article {$title} a été généré et publié avec succès !";
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = "Erreur lors de la génération de {$title} : " . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function render()
    {
        $projects = SeoProject::where('status', 'active')->orderBy('name')->get();
        $competitorMap = [
            'indy' => ['Pennylane', 'Dougs', 'Abby', 'Shine'],
            'pennylane' => ['Indy', 'Dougs', 'Abby', 'Shine', 'Qonto'],
            'dougs' => ['Indy', 'Pennylane', 'Abby', 'Shine'],
            'shine' => ['Indy', 'Qonto', 'Pennylane', 'Dougs'],
            'abby' => ['Indy', 'Pennylane', 'Dougs'],
        ];

        $duels = [];
        $totalCount = 0;
        $publishedCount = 0;
        $missingCount = 0;

        foreach ($projects as $project) {
            $competitors = $competitorMap[$project->slug] ?? ['Pennylane', 'Dougs', 'Abby', 'Shine'];

            foreach ($competitors as $competitor) {
                $totalCount++;
                $slug1 = Str::slug($project->name . ' vs ' . $competitor);
                $slug2 = Str::slug($competitor . ' vs ' . $project->name);

                $article = Article::whereIn('slug', [$slug1, $slug2])->first();

                if ($article) {
                    $publishedCount++;
                    $status = 'published';
                } else {
                    $missingCount++;
                    $status = 'missing';
                }

                $duels[] = [
                    'project' => $project,
                    'competitor' => $competitor,
                    'title' => $project->name . ' vs ' . $competitor,
                    'slug' => $slug1,
                    'status' => $status,
                    'article' => $article,
                ];
            }
        }

        return view('livewire.generate-comparisons', [
            'duels' => $duels,
            'totalCount' => $totalCount,
            'publishedCount' => $publishedCount,
            'missingCount' => $missingCount,
        ])->layout('layouts.admin');
    }
}
