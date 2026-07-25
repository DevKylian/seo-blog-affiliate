<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\SeoProject;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class Phase1Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Catégories
        $catCompta = Category::firstOrCreate(['slug' => 'comptabilite'], ['name' => 'Comptabilité', 'description' => 'Guides et logiciels de comptabilité']);
        $catFactu = Category::firstOrCreate(['slug' => 'facturation'], ['name' => 'Facturation', 'description' => 'Outils et règles de facturation']);
        $catBanque = Category::firstOrCreate(['slug' => 'banque-pro'], ['name' => 'Banque Pro', 'description' => 'Comptes et banques pour indépendants']);

        // 2. Fiches Logiciels (SeoProject)
        $softwares = [
            ['name' => 'Indy', 'description' => 'L\'outil qui remplace l\'expert-comptable pour les freelances.'],
            ['name' => 'Pennylane', 'description' => 'La plateforme de gestion financière pour les PME.'],
            ['name' => 'Dougs', 'description' => 'L\'expert-comptable en ligne qui simplifie votre gestion.'],
            ['name' => 'Abby', 'description' => 'L\'outil tout-en-un pour les auto-entrepreneurs.'],
            ['name' => 'Tiime', 'description' => 'La solution complète de facturation et comptabilité.'],
        ];

        $projects = [];
        foreach ($softwares as $soft) {
            $projects[$soft['name']] = SeoProject::updateOrCreate(
                ['slug' => Str::slug($soft['name'])],
                [
                    'name' => $soft['name'],
                    'description' => $soft['description'],
                    'status' => 'active',
                    'website_url' => 'https://' . Str::slug($soft['name']) . '.com',
                ]
            );
        }

        // 3. Comparatifs (Articles type 'comparison')
        $comparisons = [
            ['title' => 'Indy vs Pennylane : Lequel choisir en 2026 ?', 'cat' => $catCompta],
            ['title' => 'Indy vs Dougs : Logiciel ou Expert-Comptable ?', 'cat' => $catCompta],
            ['title' => 'Indy vs Abby : Le duel pour les auto-entrepreneurs', 'cat' => $catFactu],
            ['title' => 'Dougs vs Pennylane : Quel outil pour votre SASU ?', 'cat' => $catCompta],
        ];

        foreach ($comparisons as $comp) {
            $article = Article::updateOrCreate(
                ['slug' => Str::slug($comp['title'])],
                [
                    'title' => $comp['title'],
                    'seo_project_id' => $projects['Indy']->id,
                    'meta_description' => 'Découvrez notre comparatif complet entre ' . str_replace([' vs ', ' : ', ' ?', '2026'], [' et ', ' ', '', ''], $comp['title']) . '.',
                    'body' => '<h2>Introduction</h2><p>Le choix entre ces deux solutions est crucial pour votre activité...</p>',
                    'status' => 'draft', // Laissé en brouillon pour la rédaction
                    'type' => 'comparison',
                    'published_at' => null,
                ]
            );
            $article->categories()->syncWithoutDetaching([$comp['cat']->id]);
        }

        // 4. Guides SEO (Articles type standard)
        $guides = [
            ['title' => 'Quel logiciel comptable choisir quand on est freelance ?', 'cat' => $catCompta],
            ['title' => 'Le meilleur logiciel comptable pour micro-entreprise', 'cat' => $catCompta],
            ['title' => 'Quelle est la meilleure banque pro pour un freelance ?', 'cat' => $catBanque],
            ['title' => 'La facture est-elle obligatoire en micro-entreprise ?', 'cat' => $catFactu],
            ['title' => 'TVA et micro-entrepreneur : Comment ça marche ?', 'cat' => $catCompta],
        ];

        foreach ($guides as $guide) {
            $article = Article::updateOrCreate(
                ['slug' => Str::slug($guide['title'])],
                [
                    'title' => $guide['title'],
                    'seo_project_id' => $projects['Indy']->id,
                    'meta_description' => 'Guide complet : ' . $guide['title'],
                    'body' => '<h2>Introduction</h2><p>Dans ce guide, nous allons aborder toutes les questions autour de ce sujet...</p>',
                    'status' => 'draft', // Laissé en brouillon pour la rédaction
                    'type' => 'guide', // ou null selon votre logique
                    'published_at' => null,
                ]
            );
            $article->categories()->syncWithoutDetaching([$guide['cat']->id]);
        }
    }
}
