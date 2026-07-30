<?php

use App\Models\Article;
use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $compCategory = Category::firstOrCreate(
            ['slug' => 'comparatifs'],
            ['name' => 'Comparatifs', 'description' => 'Duels et comparatifs entre logiciels.']
        );

        $avisCategory = Category::firstOrCreate(
            ['slug' => 'avis'],
            ['name' => 'Avis & Tests Logiciels', 'description' => 'Nos avis objectifs sur les outils pros.']
        );

        $logicielsCategory = Category::firstOrCreate(
            ['slug' => 'logiciels'],
            ['name' => 'Les Meilleurs Logiciels', 'description' => 'Sélections et tops par métier.']
        );

        $guidesCategory = Category::firstOrCreate(
            ['slug' => 'guides'],
            ['name' => 'Guides Indépendants', 'description' => 'L\'administratif expliqué sans jargon.']
        );

        // 1. Sync comparison articles
        $comparisonArticles = Article::query()
            ->where('type', 'comparison')
            ->orWhere('type', 'tool_comparison')
            ->orWhere('slug', 'like', '%-vs-%')
            ->get();

        foreach ($comparisonArticles as $article) {
            $article->categories()->syncWithoutDetaching([$compCategory->id]);
        }

        // 2. Sync review articles
        $reviewArticles = Article::query()
            ->whereIn('type', ['review', 'tool_review'])
            ->get();

        foreach ($reviewArticles as $article) {
            $article->categories()->syncWithoutDetaching([$avisCategory->id]);
        }

        // 3. Sync best tools & alternatives articles
        $logicielsArticles = Article::query()
            ->whereIn('type', ['best_tools', 'alternatives'])
            ->get();

        foreach ($logicielsArticles as $article) {
            $article->categories()->syncWithoutDetaching([$logicielsCategory->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
