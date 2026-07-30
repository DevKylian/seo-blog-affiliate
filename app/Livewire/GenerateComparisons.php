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
    public string $activeCategory = 'all'; // 'all', 'comparison', 'review', 'pricing', 'guide'

    private function getCatalog(): array
    {
        $allCompetitorNames = ['Pennylane', 'Dougs', 'Abby', 'Shine', 'Qonto', 'Indy', 'Tiime', 'Freebe'];
        $softwares = [
            ['name' => 'Indy', 'slug' => 'indy', 'desc' => 'L\'outil tout-en-un de comptabilité et facturation pour indépendants.'],
            ['name' => 'Pennylane', 'slug' => 'pennylane', 'desc' => 'La plateforme de gestion financière et comptabilité pour TPE/PME.'],
            ['name' => 'Dougs', 'slug' => 'dougs', 'desc' => 'L\'expert-comptable en ligne qui simplifie votre gestion.'],
            ['name' => 'Shine', 'slug' => 'shine', 'desc' => 'Le compte pro avec facturation et comptabilité intégrées.'],
            ['name' => 'Abby', 'slug' => 'abby', 'desc' => 'L\'application de gestion pour les auto-entrepreneurs.'],
        ];

        $projects = [];
        foreach ($softwares as $soft) {
            $projects[$soft['slug']] = SeoProject::updateOrCreate(
                ['slug' => $soft['slug']],
                [
                    'name' => $soft['name'],
                    'description' => $soft['desc'],
                    'status' => 'active',
                    'website_url' => 'https://' . $soft['slug'] . '.fr',
                    'competitors' => array_values(array_filter($allCompetitorNames, fn($n) => strtolower($n) !== strtolower($soft['name']))),
                ]
            );
        }

        $indy = $projects['indy'];
        $pennylane = $projects['pennylane'];
        $dougs = $projects['dougs'];
        $shine = $projects['shine'];
        $abby = $projects['abby'];

        $items = [
            // --- 1. COMPARATIFS (DUELS) ---
            ['title' => 'Indy vs Pennylane', 'category' => 'comparison', 'type' => 'comparison', 'slug' => 'indy-vs-pennylane', 'project' => $indy, 'competitor' => 'Pennylane'],
            ['title' => 'Indy vs Dougs', 'category' => 'comparison', 'type' => 'comparison', 'slug' => 'indy-vs-dougs', 'project' => $indy, 'competitor' => 'Dougs'],
            ['title' => 'Indy vs Abby', 'category' => 'comparison', 'type' => 'comparison', 'slug' => 'indy-vs-abby', 'project' => $indy, 'competitor' => 'Abby'],
            ['title' => 'Indy vs Shine', 'category' => 'comparison', 'type' => 'comparison', 'slug' => 'indy-vs-shine', 'project' => $indy, 'competitor' => 'Shine'],
            ['title' => 'Pennylane vs Dougs', 'category' => 'comparison', 'type' => 'comparison', 'slug' => 'pennylane-vs-dougs', 'project' => $pennylane, 'competitor' => 'Dougs'],
            ['title' => 'Shine vs Qonto', 'category' => 'comparison', 'type' => 'comparison', 'slug' => 'shine-vs-qonto', 'project' => $shine, 'competitor' => 'Qonto'],
            ['title' => 'Abby vs Pennylane', 'category' => 'comparison', 'type' => 'comparison', 'slug' => 'abby-vs-pennylane', 'project' => $abby, 'competitor' => 'Pennylane'],

            // --- 2. AVIS & TESTS ---
            ['title' => 'Avis complet Indy', 'category' => 'review', 'type' => 'tool_review', 'slug' => 'indy-avis', 'project' => $indy],
            ['title' => 'Avis complet Pennylane', 'category' => 'review', 'type' => 'tool_review', 'slug' => 'pennylane-avis', 'project' => $pennylane],
            ['title' => 'Avis complet Dougs', 'category' => 'review', 'type' => 'tool_review', 'slug' => 'dougs-avis', 'project' => $dougs],
            ['title' => 'Avis complet Abby', 'category' => 'review', 'type' => 'tool_review', 'slug' => 'abby-avis', 'project' => $abby],
            ['title' => 'Avis complet Shine', 'category' => 'review', 'type' => 'tool_review', 'slug' => 'shine-avis', 'project' => $shine],

            // --- 3. TARIFS & PRIX ---
            ['title' => 'Tarifs et prix Indy', 'category' => 'pricing', 'type' => 'pricing', 'slug' => 'indy-tarif', 'project' => $indy],
            ['title' => 'Tarifs et prix Pennylane', 'category' => 'pricing', 'type' => 'pricing', 'slug' => 'pennylane-tarif', 'project' => $pennylane],
            ['title' => 'Tarifs et prix Dougs', 'category' => 'pricing', 'type' => 'pricing', 'slug' => 'dougs-tarif', 'project' => $dougs],
            ['title' => 'Tarifs et prix Abby', 'category' => 'pricing', 'type' => 'pricing', 'slug' => 'abby-tarif', 'project' => $abby],
            ['title' => 'Tarifs et prix Shine', 'category' => 'pricing', 'type' => 'pricing', 'slug' => 'shine-tarif', 'project' => $shine],

            // --- 4. GUIDES PILIERS ---
            ['title' => 'Guide Facturation Électronique 2026', 'category' => 'guide', 'type' => 'informational', 'slug' => 'facturation-electronique', 'project' => $indy],
            ['title' => 'Guide TVA Micro-entreprise', 'category' => 'guide', 'type' => 'informational', 'slug' => 'tva-micro-entreprise', 'project' => $indy],
            ['title' => 'Guide Domiciliation Micro-entreprise', 'category' => 'guide', 'type' => 'informational', 'slug' => 'domiciliation-micro-entreprise', 'project' => $indy],
            ['title' => 'Guide Cumul ARE et Micro-entreprise', 'category' => 'guide', 'type' => 'informational', 'slug' => 'micro-entreprise-et-chomage', 'project' => $indy],
        ];

        return array_filter($items, fn($item) => $item['project'] !== null);
    }

    public function generateAllMissing()
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $catalog = $this->getCatalog();
        $missingItems = [];

        foreach ($catalog as $item) {
            $article = Article::where('slug', $item['slug'])->first();
            if (!$article) {
                $missingItems[] = $item;
            }
        }

        if (empty($missingItems)) {
            $this->message = "Tous les contenus (comparatifs, avis, tarifs, guides) sont déjà générés et en ligne !";
            $this->messageType = 'info';
            return;
        }

        $generator = app(GeminiContentGenerator::class);
        $totalGenerated = 0;

        foreach ($missingItems as $item) {
            $project = $item['project'];
            if (!$project) continue;

            try {
                $plan = EditorialPlan::create([
                    'seo_project_id' => $project->id,
                    'name' => 'Génération ' . $item['title'],
                    'status' => 'generating',
                    'requested_count' => 1,
                ]);

                $idea = EditorialIdea::create([
                    'editorial_plan_id' => $plan->id,
                    'title' => $item['title'],
                    'thumbnail_title' => $item['title'],
                    'primary_keyword' => mb_strtolower($item['title']),
                    'entity_key' => $project->name,
                    'topic_key' => $item['type'],
                    'intent' => 'Commercial',
                    'angle' => "Analyse et guide : {$item['title']}",
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
                ]);

                $article = $generator->generateFromIdea($project, $idea);
                $article->update([
                    'slug' => $item['slug'],
                    'status' => 'published',
                    'published_at' => now(),
                ]);

                $totalGenerated++;
            } catch (\Exception $e) {
                logger()->error("Generation failed for {$item['title']}: " . $e->getMessage());
            }
        }

        $this->message = "✅ {$totalGenerated} article(s) sur " . count($missingItems) . " ont été générés et publiés immédiatement !";
        $this->messageType = 'success';
    }

    public function generateSingle(string $targetSlug)
    {
        $catalog = collect($this->getCatalog());
        $item = $catalog->firstWhere('slug', $targetSlug);

        if (!$item) {
            $this->message = "Item non trouvé.";
            $this->messageType = 'error';
            return;
        }

        $existingArticle = Article::where('slug', $targetSlug)->first();
        if ($existingArticle) {
            $this->message = "L'article {$item['title']} existe déjà.";
            $this->messageType = 'info';
            return;
        }

        $project = $item['project'];

        $plan = EditorialPlan::create([
            'seo_project_id' => $project->id,
            'name' => 'Génération ' . $item['title'],
            'status' => 'generating',
            'requested_count' => 1,
        ]);

        $idea = EditorialIdea::create([
            'editorial_plan_id' => $plan->id,
            'title' => $item['title'],
            'thumbnail_title' => $item['title'],
            'primary_keyword' => mb_strtolower($item['title']),
            'entity_key' => isset($item['competitor']) ? $project->name . '/' . $item['competitor'] : $project->name,
            'topic_key' => $item['type'],
            'intent' => 'Commercial',
            'angle' => "Analyse et guide : {$item['title']}",
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
        ]);

        try {
            $generator = app(GeminiContentGenerator::class);
            $article = $generator->generateFromIdea($project, $idea);
            $article->update(['slug' => $targetSlug, 'status' => 'published', 'published_at' => now()]);

            $this->message = "✅ L'article {$item['title']} a été généré et publié avec le slug {$targetSlug} !";
            $this->messageType = 'success';
        } catch (\Exception $e) {
            $this->message = "Erreur lors de la génération de {$item['title']} : " . $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function setCategory(string $category)
    {
        $this->activeCategory = $category;
    }

    public function render()
    {
        $catalog = $this->getCatalog();
        $items = [];
        $totalCount = count($catalog);
        $publishedCount = 0;
        $missingCount = 0;

        foreach ($catalog as $catItem) {
            $article = Article::where('slug', $catItem['slug'])->first();
            if ($article) {
                $publishedCount++;
                $status = 'published';
            } else {
                $missingCount++;
                $status = 'missing';
            }

            if ($this->activeCategory !== 'all' && $catItem['category'] !== $this->activeCategory) {
                continue;
            }

            $items[] = [
                'title' => $catItem['title'],
                'category' => $catItem['category'],
                'type' => $catItem['type'],
                'slug' => $catItem['slug'],
                'project' => $catItem['project'],
                'status' => $status,
                'article' => $article,
            ];
        }

        return view('livewire.generate-comparisons', [
            'items' => $items,
            'totalCount' => $totalCount,
            'publishedCount' => $publishedCount,
            'missingCount' => $missingCount,
        ])->layout('layouts.admin');
    }
}
