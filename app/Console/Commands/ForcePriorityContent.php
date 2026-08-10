<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SeoProject;
use App\Models\EditorialPlan;
use App\Models\ContentRun;

class ForcePriorityContent extends Command
{
    protected $signature = 'blog:force-priority-content';
    protected $description = 'Force la génération des contenus prioritaires P1 à P5 (Sage, EBP, Qonto, agricole, lettrage, profils)';

    public function handle()
    {
        $project = SeoProject::first();
        if (!$project) {
            $this->error('Aucun projet SEO trouvé.');
            return;
        }

        $userId = \App\Models\User::first()?->id ?? 1;

        $plan = EditorialPlan::create([
            'seo_project_id' => $project->id,
            'user_id' => $userId,
            'name' => 'Plan Priorités P1-P5 - ' . now()->format('d/m/Y H:i'),
            'requested_count' => 7,
            'status' => 'locked',
            'locked_at' => now(),
            'instructions' => 'Génération forcée des priorités',
            'accepted_count' => 7,
        ]);

        $ideas = [
            // P1 : Sage et EBP
            [
                'title' => 'Indy vs Sage : Quel logiciel de comptabilité choisir ?',
                'primary_keyword' => 'indy vs sage',
                'content_type' => 'comparison',
                'roadmap_level' => 'Level 5 - Comparatifs',
                'entity_key' => 'Sage',
                'topic_key' => 'comptabilité',
                'intent' => 'commercial',
                'angle' => 'comparaison',
                'audience' => 'indépendants',
                'problem' => 'Choisir entre Indy et Sage',
                'expected_outcome' => 'Faire le bon choix de logiciel',
                'unique_promise' => 'Le comparatif objectif entre Indy et Sage',
                'outline' => ['Présentation de Sage', 'Présentation d\'Indy', 'Comparaison des fonctionnalités', 'Comparaison des prix', 'Verdict'],
            ],
            [
                'title' => 'Indy vs EBP : Le comparatif complet des logiciels',
                'primary_keyword' => 'indy vs ebp',
                'content_type' => 'comparison',
                'roadmap_level' => 'Level 5 - Comparatifs',
                'entity_key' => 'EBP',
                'topic_key' => 'comptabilité',
                'intent' => 'commercial',
                'angle' => 'comparaison',
                'audience' => 'indépendants',
                'problem' => 'Choisir entre Indy et EBP',
                'expected_outcome' => 'Faire le bon choix de logiciel',
                'unique_promise' => 'Le comparatif objectif entre Indy et EBP',
                'outline' => ['Présentation d\'EBP', 'Présentation d\'Indy', 'Comparaison des fonctionnalités', 'Comparaison des prix', 'Verdict'],
            ],
            // P2 : Qonto
            [
                'title' => 'Avis complet Qonto : Le compte pro idéal ?',
                'primary_keyword' => 'avis qonto',
                'content_type' => 'tool_review',
                'roadmap_level' => 'Level 2 - Commercial',
                'entity_key' => 'Qonto',
                'topic_key' => 'banque pro',
                'intent' => 'commercial',
                'angle' => 'avis',
                'audience' => 'indépendants',
                'problem' => 'Savoir si Qonto est une bonne banque',
                'expected_outcome' => 'Comprendre les avantages de Qonto',
                'unique_promise' => 'Notre avis complet et transparent sur Qonto',
                'outline' => ['Présentation de Qonto', 'Fonctionnalités', 'Tarifs', 'Avis clients', 'Notre avis final'],
            ],
            // P3 : Agricole
            [
                'title' => 'Logiciel comptabilité agricole : Le guide complet',
                'primary_keyword' => 'logiciel comptabilité agricole',
                'content_type' => 'informational',
                'roadmap_level' => 'Level 1 - Pillar',
                'entity_key' => 'comptabilité agricole',
                'topic_key' => 'comptabilité',
                'intent' => 'informational',
                'angle' => 'guide-complet',
                'audience' => 'agriculteurs',
                'problem' => 'Trouver un logiciel adapté à l\'agriculture',
                'expected_outcome' => 'Maîtriser la comptabilité agricole',
                'unique_promise' => 'Tout savoir sur les logiciels de comptabilité agricole',
                'outline' => ['Spécificités de la compta agricole', 'Critères de choix d\'un logiciel', 'Les meilleures solutions', 'Comment s\'organiser', 'Pièges à éviter'],
            ],
            // P4 : Lettrage
            [
                'title' => 'Comment faire le lettrage comptable avec un logiciel ?',
                'primary_keyword' => 'lettrage comptable logiciel',
                'content_type' => 'question',
                'roadmap_level' => 'Level 7 - Tutoriels',
                'entity_key' => 'lettrage comptable',
                'topic_key' => 'comptabilité',
                'intent' => 'informational',
                'angle' => 'tutoriel',
                'audience' => 'indépendants',
                'problem' => 'Faire son lettrage comptable',
                'expected_outcome' => 'Savoir lettrer ses comptes',
                'unique_promise' => 'La méthode pas à pas pour le lettrage',
                'outline' => ['Qu\'est-ce que le lettrage', 'Pourquoi c\'est important', 'Les étapes pas à pas', 'L\'avantage d\'un logiciel', 'Erreurs fréquentes'],
            ],
            // P5 : Profils spécifiques
            [
                'title' => 'Logiciel comptable freelance : Lequel choisir en 2026 ?',
                'primary_keyword' => 'logiciel comptable freelance',
                'content_type' => 'best_tools',
                'roadmap_level' => 'Level 2 - Commercial',
                'entity_key' => 'freelance',
                'topic_key' => 'comptabilité',
                'intent' => 'commercial',
                'angle' => 'sélection',
                'audience' => 'freelances',
                'problem' => 'Choisir son logiciel comptable',
                'expected_outcome' => 'Trouver le bon outil',
                'unique_promise' => 'La sélection des meilleurs outils pour freelances',
                'outline' => ['Besoins spécifiques du freelance', 'Critères de choix', 'Top logiciels', 'L\'option gratuite', 'Notre recommandation'],
            ],
            [
                'title' => 'Logiciel comptable micro-entreprise : Notre sélection',
                'primary_keyword' => 'logiciel comptable micro entreprise',
                'content_type' => 'best_tools',
                'roadmap_level' => 'Level 2 - Commercial',
                'entity_key' => 'micro-entreprise',
                'topic_key' => 'comptabilité',
                'intent' => 'commercial',
                'angle' => 'sélection',
                'audience' => 'micro-entrepreneurs',
                'problem' => 'Trouver un logiciel pour auto-entrepreneur',
                'expected_outcome' => 'Faciliter sa compta simplifiée',
                'unique_promise' => 'Les meilleurs logiciels pour la micro',
                'outline' => ['Obligations de la micro-entreprise', 'Faut-il un logiciel ?', 'Les solutions du marché', 'Outils gratuits vs payants', 'Comparatif'],
            ],
        ];

        $run = ContentRun::create([
            'seo_project_id' => $project->id,
            'user_id' => $userId,
            'editorial_plan_id' => $plan->id,
            'name' => 'Campagne Priorités Forcées - ' . now()->format('d/m/Y H:i'),
            'requested_count' => count($ideas),
            'status' => 'pending',
            'instructions' => 'Génération forcée',
        ]);

        $pos = 1;
        foreach ($ideas as &$ideaData) {
            $ideaData['status'] = 'accepted';
            $ideaData['position'] = $pos++;
            $ideaData['fingerprint'] = md5($ideaData['primary_keyword']);
            $ideaData['excluded_topics'] = [];
            $ideaData['funnel_stage'] = 'consideration';
            $ideaData['conversion_goal'] = 'general';
        }
        unset($ideaData);

        foreach ($ideas as $ideaData) {
            $idea = $plan->ideas()->create($ideaData);

            $run->items()->create([
                'editorial_idea_id' => $idea->id,
                'content_type' => $idea->content_type,
                'status' => 'pending',
            ]);
        }

        $this->info("Plan éditorial et campagne créés.");

        if (class_exists(\App\Services\ContentRunWorkerLauncher::class)) {
            app(\App\Services\ContentRunWorkerLauncher::class)->launch($run->id);
            $this->info("Génération lancée en arrière-plan (Run ID: {$run->id}).");
        } else {
            $this->info("Le worker launcher n'est pas disponible, veuillez lancer le worker manuellement.");
        }
    }
}
