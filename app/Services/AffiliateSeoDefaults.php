<?php

namespace App\Services;

use App\Models\AffiliateBlock;
use App\Models\KeywordSeed;
use App\Models\SeoProject;

final class AffiliateSeoDefaults
{
    public function __construct(private readonly AffiliateIntentClassifier $classifier) {}

    public function ensureForProject(SeoProject $project): void
    {
        foreach ($this->classifier->defaultSeeds() as $seed) {
            KeywordSeed::query()->firstOrCreate(
                ['seo_project_id' => $project->id, 'seed' => $seed['seed']],
                [
                    'affiliate_cluster' => $seed['affiliate_cluster'],
                    'intent_type' => $seed['intent_type'],
                    'indy_fit' => $seed['indy_fit'],
                    'variations' => $seed['variations'] ?? null,
                    'is_active' => true,
                ],
            );
        }

        foreach ($this->defaultBlocks($project) as $block) {
            AffiliateBlock::query()->firstOrCreate(
                [
                    'seo_project_id' => $project->id,
                    'affiliate_cluster' => $block['affiliate_cluster'],
                    'intent_type' => $block['intent_type'],
                    'position' => $block['position'],
                    'title' => $block['title'],
                ],
                $block + ['type' => 'cta', 'is_active' => true],
            );
        }
    }

    /** @return array<int, array<string, string|null>> */
    private function defaultBlocks(SeoProject $project): array
    {
        $name = $project->name;

        return [
            [
                'affiliate_cluster' => 'facturation',
                'intent_type' => 'information',
                'position' => 'after_intro',
                'title' => 'Créez vos factures freelance sans Excel',
                'description' => "{$name} peut centraliser vos factures, le suivi administratif et les documents utiles quand la gestion manuelle devient chronophage.",
                'cta' => 'Tester '.$name.' gratuitement',
                'style' => 'soft',
            ],
            [
                'affiliate_cluster' => 'facturation',
                'intent_type' => 'solution',
                'position' => 'after_intro',
                'title' => 'Automatiser devis et factures avec '.$name,
                'description' => "{$name} aide les indépendants à créer leurs devis, envoyer leurs factures et suivre leur administratif sans empiler les fichiers.",
                'cta' => 'Tester '.$name.' gratuitement',
                'style' => 'product',
            ],
            [
                'affiliate_cluster' => 'comptabilite',
                'intent_type' => 'information',
                'position' => 'after_intro',
                'title' => 'Gérez votre comptabilité freelance simplement',
                'description' => "{$name} aide les indépendants à suivre recettes, dépenses et obligations dans un seul espace.",
                'cta' => 'Découvrir '.$name,
                'style' => 'soft',
            ],
            [
                'affiliate_cluster' => 'tva',
                'intent_type' => 'information',
                'position' => 'after_intro',
                'title' => 'Surveillez vos seuils et vos obligations',
                'description' => "Quand la TVA devient un sujet, {$name} permet de garder un suivi clair de l'activité et des justificatifs.",
                'cta' => 'Simplifier ma gestion',
                'style' => 'soft',
            ],
            [
                'affiliate_cluster' => 'outils',
                'intent_type' => 'solution',
                'position' => 'after_intro',
                'title' => 'Notre recommandation pour les indépendants',
                'description' => "{$name} réunit facturation, suivi des transactions et comptabilité simplifiée pour éviter de multiplier les outils.",
                'cta' => 'Voir '.$name.' gratuitement',
                'style' => 'product',
            ],
            [
                'affiliate_cluster' => null,
                'intent_type' => 'solution',
                'position' => 'final',
                'title' => "Quand l'administratif prend trop de place",
                'description' => "Si le guide vous a aidé à cadrer le problème, l'étape suivante consiste à centraliser factures, dépenses et rappels dans un outil vérifié.",
                'cta' => "Voir l'outil recommandé",
                'style' => 'strong',
            ],
            [
                'affiliate_cluster' => null,
                'intent_type' => 'money',
                'position' => 'after_intro',
                'title' => 'Prêt à essayer '.$name.' ?',
                'description' => "Comparez les informations ci-dessous, puis vérifiez l'offre actuelle directement sur le site officiel.",
                'cta' => 'Créer mon compte '.$name,
                'style' => 'strong',
            ],
            [
                'affiliate_cluster' => null,
                'intent_type' => 'money',
                'position' => 'final',
                'title' => 'Vérifier si '.$name.' convient à votre profil',
                'description' => "Le meilleur choix dépend de votre statut, de votre volume de factures et de vos obligations. Le site officiel permet de confirmer les conditions actuelles.",
                'cta' => 'Tester '.$name.' gratuitement',
                'style' => 'strong',
            ],
        ];
    }
}
