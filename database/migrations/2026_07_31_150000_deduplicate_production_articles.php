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

        // 1. Fix flawed slugs containing 'prix-logiciel-crm' for non-CRM articles
        $crmSlugArticles = Article::where('slug', 'like', '%prix-logiciel-crm%')->get();
        foreach ($crmSlugArticles as $article) {
            $newSlug = Str::slug($article->title);
            if ($newSlug && $newSlug !== $article->slug) {
                // Ensure slug uniqueness
                $counter = 1;
                $originalSlug = $newSlug;
                while (Article::where('slug', $newSlug)->where('id', '!=', $article->id)->exists()) {
                    $newSlug = $originalSlug . '-' . $counter++;
                }
                $article->update(['slug' => $newSlug]);
                $thumbnailService->forget($article->slug);
            }
        }

        // 2. Group articles by (seo_project_id, normalized_title)
        $allArticles = Article::all();
        $grouped = $allArticles->groupBy(function ($article) {
            $normalizedTitle = mb_strtolower(preg_replace('/[^a-z0-9]/iu', '', $article->title));
            return ($article->seo_project_id ?? 0) . '_' . $normalizedTitle;
        });

        foreach ($grouped as $key => $group) {
            if ($group->count() <= 1) continue;

            // Sort: published first, then by id ascending
            $sorted = $group->sort(function ($a, $b) {
                if ($a->status === 'published' && $b->status !== 'published') return -1;
                if ($a->status !== 'published' && $b->status === 'published') return 1;
                return $a->id <=> $b->id;
            })->values();

            $primary = $sorted->first();

            for ($i = 1; $i < $sorted->count(); $i++) {
                $duplicate = $sorted->get($i);

                if ($duplicate->status !== 'published') {
                    // Delete unpublished duplicate drafts/reviews
                    $thumbnailService->forget($duplicate->slug);
                    $duplicate->delete();
                } else {
                    // If published duplicate, differentiate title and slug using keyword / angle
                    $suffix = $duplicate->primary_keyword ? ucfirst($duplicate->primary_keyword) : "Analyse {$i}";
                    $newTitle = rtrim($primary->title, ' .') . ' : ' . $suffix;
                    $newSlug = Str::slug($newTitle);

                    $counter = 1;
                    $baseSlug = $newSlug;
                    while (Article::where('slug', $newSlug)->where('id', '!=', $duplicate->id)->exists()) {
                        $newSlug = $baseSlug . '-' . $counter++;
                    }

                    $duplicate->update([
                        'title' => $newTitle,
                        'slug' => $newSlug,
                        'meta_title' => mb_substr($newTitle . ' | BusinessKit', 0, 255),
                        'thumbnail_title' => BlogThumbnailService::formatThumbnailTitle(null, $newTitle),
                    ]);
                    $thumbnailService->forget($duplicate->slug);
                }
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
