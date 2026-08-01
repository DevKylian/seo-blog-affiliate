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
        Schema::table('articles', function (Blueprint $table) {
            $table->json('title_embedding')->nullable();
        });

        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->json('title_embedding')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('title_embedding');
        });

        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->dropColumn('title_embedding');
        });
    }
};
