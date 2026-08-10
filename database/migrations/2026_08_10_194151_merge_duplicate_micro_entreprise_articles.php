<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $article1 = \App\Models\Article::where('slug', 'creer-micro-entreprise-lancer-2026')->first();
        $article2 = \App\Models\Article::where('slug', 'creation-micro-entreprise-lancer-2026')->first();

        if ($article1 && $article2) {
            $toKeep = $article1->views >= $article2->views ? $article1 : $article2;
            $toDelete = $article1->views >= $article2->views ? $article2 : $article1;

            \App\Models\Redirect::create([
                'from_path' => parse_url($toDelete->public_path, PHP_URL_PATH) ?? '/guides/' . $toDelete->slug,
                'to_path' => parse_url($toKeep->public_path, PHP_URL_PATH) ?? '/guides/' . $toKeep->slug,
                'status_code' => 301,
                'active' => true,
            ]);

            $toDelete->delete();
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
