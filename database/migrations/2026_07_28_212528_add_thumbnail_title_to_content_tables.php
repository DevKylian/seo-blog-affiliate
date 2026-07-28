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
        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->string('thumbnail_title')->nullable()->after('title');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('thumbnail_title')->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->dropColumn('thumbnail_title');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('thumbnail_title');
        });
    }
};
