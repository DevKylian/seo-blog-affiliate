<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\EditorialIdea;
use App\Models\EditorialPlan;
use App\Models\SeoProject;
use App\Models\SourcePage;
use App\Services\GeminiContentGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateCatalogCommand extends Command
{
    protected $signature = 'blog:build-catalog';
    protected $description = 'Génère et publie immédiatement tous les comparatifs, avis, tarifs et guides manquants du catalogue';

    public function handle(GeminiContentGenerator $generator)
    {
        $this->info("Début de la génération directe du catalogue...");

        $softwares = [
            ['name' => 'Indy', 'slug' => 'indy', 'desc' => 'L\'outil tout-en-un de comptabilité et facturation pour indépendants.'],
            ['name' => 'Pennylane', 'slug' => 'pennylane', 'desc' => 'La plateforme de gestion financière et comptabilité pour TPE/PME.'],
            ['name' => 'Dougs', 'slug' => 'dougs', 'desc' => 'L\'expert-comptable en ligne qui simplifie votre gestion.'],
            ['name' => 'Shine', 'slug' => 'shine', 'desc' => 'Le compte pro avec facturation et comptabilité intégrées.'],
            ['name' => 'Abby', 'slug' => 'abby', 'desc' => 'L\'application de gestion pour les auto-entrepreneurs.'],
        ];

        $allCompetitorNames = ['Pennylane', 'Dougs', 'Abby', 'Shine', 'Qonto', 'Indy', 'Tiime', 'Freebe'];

        $projects = [];
        foreach ($softwares as $soft) {
            $project = SeoProject::updateOrCreate(
                ['slug' => $soft['slug']],
                [
                    'name' => $soft['name'],
                    'description' => $soft['desc'],
                    'status' => 'active',
                    'website_url' => 'https://' . $soft['slug'] . '.fr',
                    'competitors' => array_values(array_filter($allCompetitorNames, fn($n) => strtolower($n) !== strtolower($soft['name']))),
                ]
            );

            SourcePage::firstOrCreate(
                ['seo_project_id' => $project->id, 'url' => $project->website_url],
                [
                    'title' => 'Page officielle ' . $project->name,
                    'type' => 'pricing',
                    'status' => 'verified',
                    'verified_at' => now(),
                    'confidence_score' => 1.0,
                    'content' => 'Tarifs, caractéristiques et fonctionnalités de ' . $project->name . '.',
                ]
            );

            $projects[$soft['slug']] = $project;
        }

        $indy = $projects['indy'];
        $pennylane = $projects['pennylane'];
        $dougs = $projects['dougs'];
        $shine = $projects['shine'];
        $abby = $projects['abby'];

        $catalog = [
            // DUELS (comparison)
            ['title' => 'Indy vs Pennylane', 'type' => 'comparison', 'slug' => 'indy-vs-pennylane', 'project' => $indy, 'competitor' => 'Pennylane'],
            ['title' => 'Indy vs Dougs', 'type' => 'comparison', 'slug' => 'indy-vs-dougs', 'project' => $indy, 'competitor' => 'Dougs'],
            ['title' => 'Indy vs Abby', 'type' => 'comparison', 'slug' => 'indy-vs-abby', 'project' => $indy, 'competitor' => 'Abby'],
            ['title' => 'Indy vs Shine', 'type' => 'comparison', 'slug' => 'indy-vs-shine', 'project' => $indy, 'competitor' => 'Shine'],
            ['title' => 'Pennylane vs Dougs', 'type' => 'comparison', 'slug' => 'pennylane-vs-dougs', 'project' => $pennylane, 'competitor' => 'Dougs'],
            ['title' => 'Shine vs Qonto', 'type' => 'comparison', 'slug' => 'shine-vs-qonto', 'project' => $shine, 'competitor' => 'Qonto'],
            ['title' => 'Abby vs Pennylane', 'type' => 'comparison', 'slug' => 'abby-vs-pennylane', 'project' => $abby, 'competitor' => 'Pennylane'],

            // AVIS (tool_review)
            ['title' => 'Avis complet Indy', 'type' => 'tool_review', 'slug' => 'indy-avis', 'project' => $indy],
            ['title' => 'Avis complet Pennylane', 'type' => 'tool_review', 'slug' => 'pennylane-avis', 'project' => $pennylane],
            ['title' => 'Avis complet Dougs', 'type' => 'tool_review', 'slug' => 'dougs-avis', 'project' => $dougs],
            ['title' => 'Avis complet Abby', 'type' => 'tool_review', 'slug' => 'abby-avis', 'project' => $abby],
            ['title' => 'Avis complet Shine', 'type' => 'tool_review', 'slug' => 'shine-avis', 'project' => $shine],

            // TARIFS (pricing)
            ['title' => 'Tarifs et prix Indy', 'type' => 'pricing', 'slug' => 'indy-tarif', 'project' => $indy],
            ['title' => 'Tarifs et prix Pennylane', 'type' => 'pricing', 'slug' => 'pennylane-tarif', 'project' => $pennylane],
            ['title' => 'Tarifs et prix Dougs', 'type' => 'pricing', 'slug' => 'dougs-tarif', 'project' => $dougs],
            ['title' => 'Tarifs et prix Abby', 'type' => 'pricing', 'slug' => 'abby-tarif', 'project' => $abby],
            ['title' => 'Tarifs et prix Shine', 'type' => 'pricing', 'slug' => 'shine-tarif', 'project' => $shine],

            // GUIDES (informational)
            ['title' => 'Guide Facturation Électronique 2026', 'type' => 'informational', 'slug' => 'facturation-electronique', 'project' => $indy],
            ['title' => 'Guide TVA Micro-entreprise', 'type' => 'informational', 'slug' => 'tva-micro-entreprise', 'project' => $indy],
            ['title' => 'Guide Domiciliation Micro-entreprise', 'type' => 'informational', 'slug' => 'domiciliation-micro-entreprise', 'project' => $indy],
            ['title' => 'Guide Cumul ARE et Micro-entreprise', 'type' => 'informational', 'slug' => 'micro-entreprise-et-chomage', 'project' => $indy],
        ];

        $generated = 0;

        foreach ($catalog as $item) {
            $project = $item['project'];

            $exists = Article::where('slug', $item['slug'])->exists();
            if ($exists) {
                $this->line("Déjà en ligne : {$item['title']} ({$item['slug']})");
                continue;
            }

            $this->info("Génération de : {$item['title']}...");

            try {
                $plan = EditorialPlan::create([
                    'seo_project_id' => $project->id,
                    'name' => 'Catalogue ' . $item['title'],
                    'status' => 'generating',
                    'requested_count' => 1,
                ]);

                $ideaData = [
                    'editorial_plan_id' => $plan->id,
                    'title' => $item['title'],
                    'thumbnail_title' => $item['title'],
                    'primary_keyword' => mb_strtolower($item['title']),
                    'entity_key' => isset($item['competitor']) ? $project->name . '/' . $item['competitor'] : $project->name,
                    'topic_key' => $item['type'],
                    'intent' => 'Commercial',
                    'angle' => "Analyse et guide complet : {$item['title']}",
                    'content_type' => $item['type'],
                    'status' => 'accepted',
                    'position' => 1,
                    'seo_score' => 90,
                    'audience' => 'Indépendants/TPE',
                    'problem' => "Découvrir {$item['title']}",
                    'expected_outcome' => "Guide complet {$item['title']}",
                    'unique_promise' => "L'analyse complète {$item['title']}",
                    'funnel_stage' => 'decision',
                    'excluded_topics' => [],
                    'outline' => ["Verdict", "Analyse", "Tarifs", "FAQ"],
                    'fingerprint' => mb_strtolower($item['title'] . '|' . $item['type']),
                ];

                $idea = EditorialIdea::create($ideaData);

                $article = $generator->generateFromIdea($project, $idea);
                $article->update([
                    'slug' => $item['slug'],
                    'status' => 'published',
                    'published_at' => now(),
                ]);

                $generated++;
                $this->info("✓ Publié : {$item['title']} (/blog/{$item['slug']})");
            } catch (\Exception $e) {
                $this->error("X Échec pour {$item['title']} : " . $e->getMessage());
            }
        }

        $this->info("Terminé ! {$generated} article(s) générés et publiés.");
    }
}
