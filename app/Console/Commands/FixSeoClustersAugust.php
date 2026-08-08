<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Redirect;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixSeoClustersAugust extends Command
{
    protected $signature = 'fix:seo-clusters-august';
    protected $description = 'Corrige les derniers clusters SEO (Comptes pro indépendants) et inverse la fusion SCI';

    public function handle(): int
    {
        $this->info('Début des corrections SEO (Août)...');

        $this->mergeIndependentProAccounts();
        $this->fixSciAccountingBug();

        $this->info('Corrections terminées avec succès.');
        return self::SUCCESS;
    }

    private function mergeIndependentProAccounts(): void
    {
        $this->info('Fusion du cluster "ouvrir un compte bancaire professionnel indépendant" (générique)');
        $pillarSlug = 'ouvrir-compte-pro-ligne-essentiel';
        $duplicateSlugs = [
            'ouvrir-compte-bancaire-professionnel-independants',
            'compte-bancaire-professionnel-independants-tpe',
            'ouvrir-compte-bancaire-simplicite-rapidite',
        ];

        $pillar = Article::where('slug', $pillarSlug)->first();
        if (!$pillar) {
            $this->warn("- Pilier non trouvé : /{$pillarSlug}");
            return;
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
                    'status' => 'archived',
                    'canonical_article_id' => $pillar->id,
                    'duplicate_status' => 'merged',
                ]);
                // Removed $duplicate->delete() to avoid hard deleting the article from the DB
                // since the Article model does not use SoftDeletes.

                $this->line("  -> Fusionné et redirigé (301) : /{$slug} vers /{$pillarSlug}");
            } else {
                $this->warn("  -> Doublon non trouvé : /{$slug} (déjà supprimé ou archivé)");
            }
        }
        $this->line("Attention : 'ouvrir-compte-bancaire-professionnel-ligne' (spécial SASU) n'a pas été touché, comme demandé.");
    }

    private function fixSciAccountingBug(): void
    {
        $this->info('Correction de la fusion inversée SCI');

        $wrongPillarSlug = 'gerer-comptabilite-dune-sci-faire';
        $correctPillarSlug = 'blog-guides-generaux-comptabilite-sci';

        $wrongPillar = Article::where('slug', $wrongPillarSlug)->first();
        $correctPillar = Article::where('slug', $correctPillarSlug)->first();

        if (!$wrongPillar && !$correctPillar) {
            $this->warn("- Ni le mauvais pilier ni le bon pilier ne sont trouvés.");
            return;
        }

        if ($wrongPillar && !$correctPillar) {
            $this->info("- Le bon pilier a été hard-delete. Recréation à partir du contenu actuel...");
            $correctPillar = $wrongPillar->replicate();
            $correctPillar->slug = $correctPillarSlug;
            $correctPillar->canonical_article_id = null;
            $correctPillar->duplicate_status = null;
            $correctPillar->status = 'published';
            $correctPillar->save();
        } elseif ($wrongPillar && $correctPillar) {
            // S'il existe déjà, on le met juste à jour
            $correctPillar->update([
                'title' => $wrongPillar->title ?? $correctPillar->title,
                'body' => $wrongPillar->body ?? $correctPillar->body,
                'content_blocks' => $wrongPillar->content_blocks,
                'meta_description' => $wrongPillar->meta_description,
                'status' => 'published',
                'canonical_article_id' => null,
                'duplicate_status' => null,
            ]);
        }

        if ($correctPillar) {
            // Check if there was a redirect from correctPillar to wrongPillar and remove it
            $fromPathCorrect = parse_url($correctPillar->public_path, PHP_URL_PATH);
            Redirect::where('from_path', $fromPathCorrect)->delete();

            if ($wrongPillar) {
                // Create redirect from wrongPillar to correctPillar
                $fromPathWrong = parse_url($wrongPillar->public_path, PHP_URL_PATH);
                $toPathCorrect = parse_url($correctPillar->public_path, PHP_URL_PATH);
                
                Redirect::updateOrCreate(
                    ['from_path' => $fromPathWrong],
                    ['to_path' => $toPathCorrect, 'status_code' => 301, 'active' => true]
                );

                // Archive the wrong pillar without hard deleting it
                $wrongPillar->update([
                    'status' => 'archived',
                    'canonical_article_id' => $correctPillar->id,
                    'duplicate_status' => 'merged',
                ]);

                $this->line("  -> Fusion inversée : /{$wrongPillarSlug} redirige vers /{$correctPillarSlug}");
            }

            // Update the previous duplicate redirect if it exists
            // Since it was hard deleted, it won't be in the DB, but we can check if a Redirect exists for it
            // The old duplicate was 'gerer-comptabilite-dune-sci-logiciel'
            // We just create/update the redirect to point to the new correct pillar
            // Fake an article to get its expected path:
            $dummyArticle = new Article(['type' => 'blog', 'slug' => 'gerer-comptabilite-dune-sci-logiciel']);
            $fromPathOldDuplicate = parse_url($dummyArticle->public_path, PHP_URL_PATH);
            
            Redirect::updateOrCreate(
                ['from_path' => $fromPathOldDuplicate],
                ['to_path' => parse_url($correctPillar->public_path, PHP_URL_PATH), 'status_code' => 301, 'active' => true]
            );
            $this->line("  -> Redirection de l'ancien doublon (gerer-comptabilite-dune-sci-logiciel) mise à jour.");
        }
    }
}
