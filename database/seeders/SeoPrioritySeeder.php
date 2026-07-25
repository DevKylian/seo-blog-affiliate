<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Keyword;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SeoPrioritySeeder extends Seeder
{
    public function run(): void
    {
        $priorityQueries = [
            // Prio 1 - Conversion (Avis & Comparatifs)
            ['title' => 'Indy avis : ce qu\'il faut savoir avant de choisir', 'query' => 'indy avis', 'type' => 'review'],
            ['title' => 'Indy tarif : quel abonnement choisir ?', 'query' => 'indy tarif', 'type' => 'review'],
            ['title' => 'Indy vs Pennylane : le duel des logiciels', 'query' => 'indy vs pennylane', 'type' => 'comparison'],
            ['title' => 'Indy vs Dougs : quel cabinet choisir ?', 'query' => 'indy vs dougs', 'type' => 'comparison'],
            ['title' => 'Pennylane avis complet : pour qui est fait ce logiciel ?', 'query' => 'pennylane avis', 'type' => 'review'],
            ['title' => 'Dougs avis : l\'expert-comptable en ligne par excellence', 'query' => 'dougs avis', 'type' => 'review'],
            ['title' => 'Abby avis : notre test de l\'outil pour freelances', 'query' => 'abby avis', 'type' => 'review'],
            ['title' => 'Qonto avis : la banque pro en ligne passée au crible', 'query' => 'qonto avis', 'type' => 'review'],
            ['title' => 'Shine avis : l\'application bancaire idéale ?', 'query' => 'shine avis', 'type' => 'review'],
            
            // Prio 2 - Business / Piliers (Best Tools)
            ['title' => 'Meilleur logiciel comptable pour micro-entreprise', 'query' => 'logiciel comptable micro entreprise', 'type' => 'best_tools'],
            ['title' => 'Logiciel comptable freelance : lequel choisir ?', 'query' => 'logiciel comptable freelance', 'type' => 'best_tools'],
            ['title' => 'Le meilleur logiciel de facturation pour micro-entreprise', 'query' => 'logiciel de facturation micro entreprise', 'type' => 'best_tools'],
            ['title' => 'Banque pro freelance : notre sélection', 'query' => 'banque pro freelance', 'type' => 'best_tools'],
            ['title' => 'Compte pro micro-entreprise : le classement', 'query' => 'compte pro micro entreprise', 'type' => 'best_tools'],
            
            // Prio 3 - Autorité / Informational (Guides)
            ['title' => 'Le guide complet de la micro-entreprise', 'query' => 'micro entreprise', 'type' => 'guide'],
            ['title' => 'Création micro-entreprise : les étapes pas-à-pas', 'query' => 'création micro entreprise', 'type' => 'guide'],
            ['title' => 'TVA micro-entreprise : comment ça marche ?', 'query' => 'tva micro entreprise', 'type' => 'guide'],
            ['title' => 'Comment faire une facture en micro-entreprise ?', 'query' => 'facture micro entreprise', 'type' => 'guide'],
            ['title' => 'Charges micro-entreprise : tout comprendre', 'query' => 'charges micro entreprise', 'type' => 'guide'],
            ['title' => 'Cumuler micro-entreprise et chômage (ARE)', 'query' => 'micro entreprise et chômage', 'type' => 'guide'],
            ['title' => 'Comptabilité micro-entreprise : obligations et astuces', 'query' => 'comptabilité micro entreprise', 'type' => 'guide'],
            ['title' => 'Domiciliation micro-entreprise : les options', 'query' => 'domiciliation micro entreprise', 'type' => 'guide'],
            ['title' => 'Comment créer une micro entreprise sans se tromper ?', 'query' => 'créer une micro entreprise', 'type' => 'guide'],
        ];

        // Création des catégories (silos) si elles n'existent pas
        $categories = [
            'avis' => Category::firstOrCreate(['slug' => 'avis'], ['name' => 'Avis & Tests Logiciels', 'description' => 'Nos avis objectifs sur les outils pros.']),
            'comparatifs' => Category::firstOrCreate(['slug' => 'comparatifs'], ['name' => 'Comparatifs', 'description' => 'Duels et comparatifs entre logiciels.']),
            'logiciels' => Category::firstOrCreate(['slug' => 'logiciels'], ['name' => 'Les Meilleurs Logiciels', 'description' => 'Sélections et tops par métier.']),
            'guides' => Category::firstOrCreate(['slug' => 'guides'], ['name' => 'Guides Indépendants', 'description' => 'L\'administratif expliqué sans jargon.']),
        ];

        $defaultProject = \App\Models\SeoProject::firstOrCreate(
            ['slug' => 'blog-general'],
            ['name' => 'Blog & Guides Généraux', 'status' => 'active', 'website_url' => 'https://businesskit.fr']
        );

        foreach ($priorityQueries as $pq) {
            $slug = Str::slug($pq['query']);

            $keyword = Keyword::firstOrCreate(
                ['keyword' => $pq['query']],
                ['search_volume' => 0, 'keyword_difficulty' => 0, 'seo_project_id' => $defaultProject->id]
            );

            $article = Article::firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $pq['title'],
                    'meta_title' => $pq['title'] . ' - BusinessKit',
                    'meta_description' => 'Découvrez notre analyse complète pour la requête : ' . $pq['query'],
                    'body' => '',
                    'type' => $pq['type'],
                    'status' => 'draft',
                    'keyword_id' => $keyword->id,
                    'seo_project_id' => $defaultProject->id,
                ]
            );

            // Attacher à la bonne catégorie selon le type
            $catSlug = match ($pq['type']) {
                'review' => 'avis',
                'comparison' => 'comparatifs',
                'best_tools' => 'logiciels',
                'guide' => 'guides',
                default => 'guides',
            };

            $article->categories()->syncWithoutDetaching([$categories[$catSlug]->id]);
        }
    }
}
