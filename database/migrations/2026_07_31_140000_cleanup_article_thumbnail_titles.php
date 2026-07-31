<?php

use App\Models\Article;
use App\Models\EditorialIdea;
use App\Services\BlogThumbnailService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $service = app(BlogThumbnailService::class);

        Article::chunk(100, function ($articles) use ($service) {
            foreach ($articles as $article) {
                $cleaned = BlogThumbnailService::formatThumbnailTitle($article->thumbnail_title, $article->title);
                $article->update(['thumbnail_title' => $cleaned]);
                $service->forget($article->slug);
            }
        });

        EditorialIdea::chunk(100, function ($ideas) {
            foreach ($ideas as $idea) {
                $cleaned = BlogThumbnailService::formatThumbnailTitle($idea->thumbnail_title, $idea->title);
                $idea->update(['thumbnail_title' => $cleaned]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
