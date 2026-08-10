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
        \App\Models\Article::query()->chunkById(100, function ($articles) {
            foreach ($articles as $article) {
                if (!is_array($article->content_blocks)) {
                    continue;
                }
                
                $blocks = $article->content_blocks;
                
                $afterIntroCount = 0;
                $updatedBlocks = [];
                
                foreach ($blocks as $block) {
                    if (($block['type'] ?? '') === 'affiliate_cta' && ($block['position'] ?? '') === 'after_intro') {
                        $afterIntroCount++;
                        if ($afterIntroCount > 1) {
                            continue;
                        }
                    }
                    $updatedBlocks[] = $block;
                }
                
                if (count($updatedBlocks) !== count($blocks)) {
                    $article->update(['content_blocks' => $updatedBlocks]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 
    }
};
