<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\EditorialIdea;
use App\Models\Keyword;
use App\Models\Redirect;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixProductionData extends Command
{
    protected $signature = 'fix:production-data';
    protected $description = 'Corrige les articles fictifs, supprime les mots-clés problématiques et fusionne les clusters de contenu.';

    public function handle(): int
    {
        $this->info('Début du nettoyage de production...');

        $this->deleteFakeProducts();
        $this->deleteProblematicKeywords();
        $this->mergeClusters();

        $this->info('Nettoyage terminé avec succès.');
        return self::SUCCESS;
    }

    private function deleteFakeProducts(): void
    {
        $this->info('Suppression des articles fictifs (QuickBill, AccountPro, Poste)');
        $slugsToDelete = [
            'choisir-utiliser-logiciel-quickbill-advanced',
            'accountpro-cloud-prix-fonctionnalites-et-alternatives-pour-independants',
            'compte-pro-poste-ouvrir-bancaire',
        ];

        foreach ($slugsToDelete as $slug) {
            $article = Article::where('slug', $slug)->first();
            if ($article) {
                // Créer une redirection 410 Gone (ou 404 si la table redirects ne gère pas 410, mais on met 410 pour le SEO)
                Redirect::updateOrCreate(
                    ['from_path' => parse_url($article->public_path, PHP_URL_PATH)],
                    ['to_path' => '/404', 'status_code' => 410, 'active' => true]
                );
                
                $article->delete();
                $this->line("- Supprimé et redirigé (410) : /{$slug}");
            } else {
                $this->warn("- Article non trouvé (déjà supprimé ?) : /{$slug}");
            }
        }
    }

    private function deleteProblematicKeywords(): void
    {
        $this->info('Nettoyage des mots-clés et idées fictives');
        $fictifs = ['QuickBill', 'AccountPro'];

        foreach ($fictifs as $fictif) {
            $keywords = Keyword::where('keyword', 'like', "%{$fictif}%")->get();
            foreach ($keywords as $kw) {
                // Supprimer les idées éditoriales associées
                EditorialIdea::where('keyword_id', $kw->id)->delete();
                $kw->delete();
                $this->line("- Mot-clé supprimé : {$kw->keyword}");
            }
        }
    }

    private function mergeClusters(): void
    {
        $this->info('Fusion des clusters de cannibalisation');

        // Cluster "Signature électronique"
        $this->mergeCluster(
            pillarSlug: 'signature-electronique-comprendre-son-fonctionnement', // le plus proche de "fonctionnement, valeur légale..."
            duplicateSlugs: [
                'signature-electronique-inpi-comprendre-son',
                'signature-electronique-professionnels',
                'signature-electronique-pdf-creer-utiliser',
            ],
            newPillarTitle: 'Signature électronique : fonctionnement, valeur légale et usage professionnel'
        );

        // Cluster "Devis/Facture gratuit auto-entrepreneur"
        // On prend un au hasard parmi les 5 pour servir de pilier.
        $this->mergeCluster(
            pillarSlug: 'logiciel-facturation-gratuit-auto-entrepreneur',
            duplicateSlugs: [
                'logiciel-devis-facture-meilleur-outil',
                'logiciel-facture-gratuit-meilleur-outil',
                'logiciel-facturation-auto-entrepreneur-2026',
                'logiciel-devis-facture-gratuit-auto',
            ],
            newPillarTitle: 'Logiciel de facturation gratuit pour auto-entrepreneur'
        );

        // Cluster "SASU — ouvrir un compte pro en ligne"
        $this->mergeCluster(
            pillarSlug: 'ouvrir-compte-bancaire-professionnel-ligne', // celui avec le titre "2024" à corriger
            duplicateSlugs: [
                'choisir-ouvrir-compte-bancaire-professionnel',
                'ouvrir-compte-pro-ligne-sasu',
            ],
            newPillarTitle: 'Ouvrir un compte pro en ligne pour SASU en 2026' // Correction de l'anomalie 2024
        );

        // Cluster "BNP Paribas Compte Pro"
        $this->mergeCluster(
            pillarSlug: 'bnp-paribas-compte-pro-avantages',
            duplicateSlugs: [
                'ouvrir-compte-bancaire-professionnel-bnp',
                'mon-compte-bnp-pro-ouvrir',
            ]
        );

        // Cluster "La Poste / Banque Postale Compte Pro"
        $this->mergeCluster(
            pillarSlug: 'poste-compte-pro-avantages-tarifs',
            duplicateSlugs: [
                'ouvrir-compte-bancaire-professionnel-banque',
                'compte-pro-banque-postale-independants',
            ]
        );

        // Cluster "Compte comptable logiciel"
        $this->mergeCluster(
            pillarSlug: 'compte-comptable-abonnement-logiciel-comprendre',
            duplicateSlugs: [
                'compte-comptable-logiciel-bases-gestion',
            ]
        );

        // Cluster "Logiciel comptable PME avec Indy"
        $this->mergeCluster(
            pillarSlug: 'logiciel-comptable-pme-optimisez-gestion',
            duplicateSlugs: [
                'choisir-utiliser-logiciel-comptabilite-pme',
            ]
        );

        // Cluster "Logiciel facturation Bâtiment"
        $this->mergeCluster(
            pillarSlug: 'logiciel-devis-facture-batiment-outils',
            duplicateSlugs: [
                'logiciel-facturation-batiment-outils-indispensables',
            ]
        );

        // Clusters manuels
        // "Notes de frais pour indépendants/TPE"
        $this->mergeCluster(
            pillarSlug: 'gestion-notes-frais-independants-tpe',
            duplicateSlugs: [
                'gestion-notes-frais-expliquer-collecter',
                'notes-frais-pratique-independants-tpe',
            ]
        );

        // "Comptabilité SCI"
        $this->mergeCluster(
            pillarSlug: 'gerer-comptabilite-dune-sci-faire',
            duplicateSlugs: [
                'blog-guides-generaux-comptabilite-sci',
                'gerer-comptabilite-dune-sci-logiciel',
            ]
        );

        // "Logiciel de comptabilité pour Mac"
        $this->mergeCluster(
            pillarSlug: 'logiciel-comptabilite-mac-independants-freelances',
            duplicateSlugs: [
                'utiliser-logiciel-comptabilite-mac-pratique',
                'logiciel-comptabilite-mac-entrepreneurs',
            ]
        );

        // "Comprendre la TVA pour les indépendants"
        $this->mergeCluster(
            pillarSlug: 'comprendre-tva-independants-calcul-declaration',
            duplicateSlugs: [
                'comprendre-tva-independants-tpe-regimes',
            ]
        );

        // "CSE / Comité d'Entreprise"
        $this->mergeCluster(
            pillarSlug: 'utiliser-logiciel-comptabilite-comite-dentreprise',
            duplicateSlugs: [
                'choisir-utiliser-logiciel-comptable-comite',
            ]
        );
    }

    private function mergeCluster(string $pillarSlug, array $duplicateSlugs, ?string $newPillarTitle = null): void
    {
        $pillar = Article::where('slug', $pillarSlug)->first();

        if (!$pillar) {
            $this->warn("- Pilier non trouvé : /{$pillarSlug}");
            return;
        }

        if ($newPillarTitle) {
            $pillar->update(['title' => $newPillarTitle]);
            $this->line("- Titre du pilier mis à jour : /{$pillarSlug} -> {$newPillarTitle}");
        }

        foreach ($duplicateSlugs as $slug) {
            $duplicate = Article::where('slug', $slug)->first();
            if ($duplicate) {
                $fromPath = parse_url($duplicate->public_path, PHP_URL_PATH);
                $toPath = parse_url($pillar->public_path, PHP_URL_PATH);
                
                Redirect::updateOrCreate(
                    ['from_path' => $fromPath],
                    ['to_path' => $toPath, 'status_code' => 301, 'active' => true]
                );
                
                $duplicate->update([
                    'status' => 'archived', // ou on le supprime soft-delete, au choix
                    'canonical_article_id' => $pillar->id,
                    'duplicate_status' => 'merged',
                ]);
                $duplicate->delete(); // Soft delete pour le retirer du site public, l'archive reste dans le CMS

                $this->line("  -> Fusionné et redirigé (301) : /{$slug} vers /{$pillarSlug}");
            } else {
                $this->warn("  -> Doublon non trouvé : /{$slug}");
            }
        }
    }
}
