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

        // 1. Clean any title that had ": Analyse X" appended previously
        $analyseArticles = Article::where('title', 'like', '%: Analyse %')->orWhere('title', 'like', '%: Grille%')->get();
        foreach ($analyseArticles as $art) {
            $parts = explode(' : ', $art->title);
            $cleanTitle = trim($parts[0]);
            
            $initialExists = Article::where('title', $cleanTitle)->where('id', '!=', $art->id)->exists();
            if ($initialExists) {
                $thumbnailService->forget($art->slug);
                $art->delete();
            } else {
                $art->update([
                    'title' => $cleanTitle,
                    'slug' => Str::slug($cleanTitle),
                    'thumbnail_title' => BlogThumbnailService::formatThumbnailTitle(null, $cleanTitle),
                ]);
            }
        }

        // 2. Fix flawed slugs containing 'prix-logiciel-crm' for non-CRM articles
        $crmSlugArticles = Article::where('slug', 'like', '%prix-logiciel-crm%')->get();
        foreach ($crmSlugArticles as $article) {
            $newSlug = Str::slug($article->title);
            if ($newSlug && $newSlug !== $article->slug) {
                $counter = 1;
                $originalSlug = $newSlug;
                while (Article::where('slug', $newSlug)->where('id', '!=', $article->id)->exists()) {
                    $newSlug = $originalSlug . '-' . $counter++;
                }
                $article->update(['slug' => $newSlug]);
                $thumbnailService->forget($article->slug);
            }
        }

        // 3. Group articles by (seo_project_id, normalized_title)
        $allArticles = Article::all();
        $grouped = $allArticles->groupBy(function ($article) {
            $normalizedTitle = mb_strtolower(preg_replace('/[^a-z0-9]/iu', '', $article->title));
            return ($article->seo_project_id ?? 0) . '_' . $normalizedTitle;
        });

        foreach ($grouped as $key => $group) {
            if ($group->count() <= 1) continue;

            // Sort: published first, then by id ascending (keep initial real one)
            $sorted = $group->sort(function ($a, $b) {
                if ($a->status === 'published' && $b->status !== 'published') return -1;
                if ($a->status !== 'published' && $b->status === 'published') return 1;
                return $a->id <=> $b->id;
            })->values();

            // Delete ALL secondary duplicates, keep only initial real article ($sorted->first())
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
