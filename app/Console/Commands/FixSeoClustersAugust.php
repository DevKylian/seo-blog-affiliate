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
                $duplicate->delete(); // Soft delete

                $this->line("  -> Fusionné et redirigé (301) : /{$slug} vers /{$pillarSlug}");
            } else {
                $this->warn("  -> Doublon non trouvé : /{$slug}");
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
        $correctPillar = Article::withTrashed()->where('slug', $correctPillarSlug)->first();

        if (!$correctPillar) {
            $this->warn("- Impossible de trouver le bon pilier SCI (même dans la corbeille) : /{$correctPillarSlug}");
            return;
        }

        if ($wrongPillar) {
            // Restore correct pillar
            if ($correctPillar->trashed()) {
                $correctPillar->restore();
            }

            // Update correct pillar with content from wrong pillar
            $correctPillar->update([
                'title' => $wrongPillar->title ?? $correctPillar->title,
                'body' => $wrongPillar->body ?? $correctPillar->body,
                'content_blocks' => $wrongPillar->content_blocks,
                'meta_description' => $wrongPillar->meta_description,
                'status' => 'published',
                'canonical_article_id' => null,
                'duplicate_status' => null,
            ]);

            // Check if there was a redirect from correctPillar to wrongPillar and remove it
            $fromPathCorrect = parse_url($correctPillar->public_path, PHP_URL_PATH);
            Redirect::where('from_path', $fromPathCorrect)->delete();

            // Create redirect from wrongPillar to correctPillar
            $fromPathWrong = parse_url($wrongPillar->public_path, PHP_URL_PATH);
            $toPathCorrect = parse_url($correctPillar->public_path, PHP_URL_PATH);
            
            Redirect::updateOrCreate(
                ['from_path' => $fromPathWrong],
                ['to_path' => $toPathCorrect, 'status_code' => 301, 'active' => true]
            );

            // Update the previous duplicate redirect if it exists
            $oldDuplicate = Article::withTrashed()->where('slug', 'gerer-comptabilite-dune-sci-logiciel')->first();
            if ($oldDuplicate) {
                $fromPathOldDuplicate = parse_url($oldDuplicate->public_path, PHP_URL_PATH);
                Redirect::updateOrCreate(
                    ['from_path' => $fromPathOldDuplicate],
                    ['to_path' => $toPathCorrect, 'status_code' => 301, 'active' => true]
                );
                $oldDuplicate->update(['canonical_article_id' => $correctPillar->id]);
            }

            // Archive and delete the wrong pillar
            $wrongPillar->update([
                'status' => 'archived',
                'canonical_article_id' => $correctPillar->id,
                'duplicate_status' => 'merged',
            ]);
            $wrongPillar->delete();

            $this->line("  -> Fusion inversée : /{$wrongPillarSlug} redirige vers /{$correctPillarSlug}");
        } else {
            $this->warn("- Le mauvais pilier /{$wrongPillarSlug} n'existe plus en base (déjà corrigé ?)");
        }
    }
}
