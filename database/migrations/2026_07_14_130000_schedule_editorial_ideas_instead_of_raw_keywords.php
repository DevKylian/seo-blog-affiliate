<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_plans', function (Blueprint $table) {
            $table->foreignId('content_schedule_id')->nullable()->after('seo_project_id')->constrained()->nullOnDelete();
        });

        Schema::table('scheduled_content_tasks', function (Blueprint $table) {
            // Drop foreign key that relies on the unique index first
            $table->dropForeign(['content_schedule_id']);
            $table->dropUnique(['content_schedule_id', 'keyword_id']);
            
            $table->foreignId('editorial_idea_id')->nullable()->after('keyword_id')->constrained()->nullOnDelete();
            $table->unique(['content_schedule_id', 'editorial_idea_id']);
            
            // Re-add the foreign key
            $table->foreign('content_schedule_id')->references('id')->on('content_schedules')->cascadeOnDelete();
        });

        // La première version programmait directement les requêtes Semrush.
        // Elles ne doivent jamais atteindre le rédacteur sans brief éditorial.
        DB::table('scheduled_content_tasks')
            ->whereNull('editorial_plan_id')
            ->whereNull('content_run_id')
            ->whereNull('article_id')
            ->whereIn('status', ['queued', 'retrying', 'planning'])
            ->delete();
    }

    public function down(): void
    {
        Schema::table('scheduled_content_tasks', function (Blueprint $table) {
            $table->dropUnique(['content_schedule_id', 'editorial_idea_id']);
            $table->dropConstrainedForeignId('editorial_idea_id');
            $table->unique(['content_schedule_id', 'keyword_id']);
        });
        Schema::table('editorial_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_schedule_id');
        });
    }
};
