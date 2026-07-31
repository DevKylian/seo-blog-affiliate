<?php

use App\Models\Article;
use App\Services\BlogThumbnailService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $thumbnailService = app(BlogThumbnailService::class);

        // 1. Force delete any article containing ': Analyse' or ': Grille' or ': Coût' in title
        $analyseArticles = Article::where('title', 'like', '%: Analyse%')
            ->orWhere('title', 'like', '%: Grille%')
            ->orWhere('title', 'like', '%: Coût%')
            ->get();

        foreach ($analyseArticles as $art) {
            $thumbnailService->forget($art->slug);
            $art->delete();
        }

        // 2. Force delete any duplicate slug ending with '-1', '-2', '-3', etc. if base slug exists
        $all = Article::all();
        foreach ($all as $numArt) {
            if (preg_match('/-[0-9]+$/', $numArt->slug)) {
                $baseSlug = preg_replace('/-[0-9]+$/', '', $numArt->slug);
                if (Article::where('slug', $baseSlug)->where('id', '!=', $numArt->id)->exists()) {
                    $thumbnailService->forget($numArt->slug);
                    $numArt->delete();
                }
            }
        }

        // 3. Group remaining articles by project and base cleaned title
        $allArticles = Article::all();
        $grouped = $allArticles->groupBy(function ($article) {
            $rawTitle = preg_replace('/:\s*Analyse.*$/iu', '', $article->title);
            $normalized = mb_strtolower(preg_replace('/[^a-z0-9]/iu', '', $rawTitle));
            return ($article->seo_project_id ?? 0) . '_' . $normalized;
        });

        foreach ($grouped as $key => $group) {
            if ($group->count() <= 1) continue;

            // Sort: published first, then lowest ID (the true initial real article)
            $sorted = $group->sort(function ($a, $b) {
                if ($a->status === 'published' && $b->status !== 'published') return -1;
                if ($a->status !== 'published' && $b->status === 'published') return 1;
                return $a->id <=> $b->id;
            })->values();

            $primary = $sorted->first();

            // Ensure primary has a clean title without suffixes
            $cleanPrimaryTitle = preg_replace('/:\s*Analyse.*$/iu', '', $primary->title);
            if ($primary->title !== $cleanPrimaryTitle) {
                $primary->update([
                    'title' => $cleanPrimaryTitle,
                    'slug' => Str::slug($cleanPrimaryTitle),
                    'thumbnail_title' => BlogThumbnailService::formatThumbnailTitle(null, $cleanPrimaryTitle),
                ]);
            }

            // Delete ALL secondary duplicates!
            for ($i = 1; $i < $sorted->count(); $i++) {
                $duplicate = $sorted->get($i);
                $thumbnailService->forget($duplicate->slug);
                $duplicate->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
