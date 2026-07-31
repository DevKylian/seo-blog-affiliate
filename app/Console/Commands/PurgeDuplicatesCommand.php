<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\BlogThumbnailService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PurgeDuplicatesCommand extends Command
{
    protected $signature = 'blog:purge-duplicates';
    protected $description = 'Supprime définitivement tous les articles doublons et nettoie la base de données';

    public function handle(BlogThumbnailService $thumbnailService)
    {
        $this->info("Début du nettoyage forcé des doublons...");

        $deletedCount = 0;

        // 1. Delete articles containing ': Analyse' in title
        $analyseArticles = Article::where('title', 'like', '%: Analyse%')
            ->orWhere('title', 'like', '%: Grille%')
            ->orWhere('title', 'like', '%: Coût%')
            ->get();

        foreach ($analyseArticles as $art) {
            $thumbnailService->forget($art->slug);
            $art->delete();
            $deletedCount++;
        }

        // 2. Delete duplicate slug ending with '-1', '-2', '-3', etc. if base slug exists
        $all = Article::all();
        foreach ($all as $numArt) {
            if (preg_match('/-[0-9]+$/', $numArt->slug)) {
                $baseSlug = preg_replace('/-[0-9]+$/', '', $numArt->slug);
                if (Article::where('slug', $baseSlug)->where('id', '!=', $numArt->id)->exists()) {
                    $thumbnailService->forget($numArt->slug);
                    $numArt->delete();
                    $deletedCount++;
                }
            }
        }

        // 3. Group remaining articles by project and base title
        $remaining = Article::all();
        $grouped = $remaining->groupBy(function ($article) {
            $rawTitle = preg_replace('/:\s*Analyse.*$/iu', '', $article->title);
            $normalized = mb_strtolower(preg_replace('/[^a-z0-9]/iu', '', $rawTitle));
            return ($article->seo_project_id ?? 0) . '_' . $normalized;
        });

        foreach ($grouped as $key => $group) {
            if ($group->count() <= 1) continue;

            $sorted = $group->sort(function ($a, $b) {
                if ($a->status === 'published' && $b->status !== 'published') return -1;
                if ($a->status !== 'published' && $b->status === 'published') return 1;
                return $a->id <=> $b->id;
            })->values();

            $primary = $sorted->first();

            $cleanPrimaryTitle = preg_replace('/:\s*Analyse.*$/iu', '', $primary->title);
            if ($primary->title !== $cleanPrimaryTitle) {
                $primary->update([
                    'title' => $cleanPrimaryTitle,
                    'slug' => Str::slug($cleanPrimaryTitle),
                    'thumbnail_title' => BlogThumbnailService::formatThumbnailTitle(null, $cleanPrimaryTitle),
                ]);
            }

            for ($i = 1; $i < $sorted->count(); $i++) {
                $duplicate = $sorted->get($i);
                $thumbnailService->forget($duplicate->slug);
                $duplicate->delete();
                $deletedCount++;
            }
        }

        $this->info("Nettoyage terminé ! {$deletedCount} article(s) doublons supprimé(s).");
    }
}
