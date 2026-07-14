<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_briefs', function (Blueprint $table) {
            $table->string('entity_key', 150)->nullable()->after('type');
            $table->string('topic_key', 150)->nullable()->after('entity_key');
            $table->string('content_angle', 150)->nullable()->after('topic_key');
            $table->text('unique_promise')->nullable()->after('audience');
            $table->json('excluded_topics')->nullable()->after('unique_promise');
            $table->string('funnel_stage', 50)->nullable()->after('search_intent');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('entity_key', 150)->nullable()->after('search_intent')->index();
            $table->string('topic_key', 150)->nullable()->after('entity_key')->index();
            $table->string('content_angle', 150)->nullable()->after('topic_key');
            $table->string('editorial_audience', 150)->nullable()->after('content_angle');
            $table->string('funnel_stage', 50)->nullable()->after('editorial_audience');
            $table->string('topic_fingerprint', 500)->nullable()->after('funnel_stage')->index();
            $table->foreignId('canonical_article_id')->nullable()->after('topic_fingerprint')->constrained('articles')->nullOnDelete();
            $table->decimal('duplicate_score', 5, 2)->nullable()->after('canonical_article_id');
            $table->string('duplicate_status', 40)->nullable()->after('duplicate_score')->index();
            $table->text('unique_promise')->nullable()->after('duplicate_status');
            $table->json('excluded_topics')->nullable()->after('unique_promise');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['canonical_article_id']);
            $table->dropIndex(['entity_key']);
            $table->dropIndex(['topic_key']);
            $table->dropIndex(['topic_fingerprint']);
            $table->dropIndex(['duplicate_status']);
            $table->dropColumn([
                'entity_key', 'topic_key', 'content_angle', 'editorial_audience', 'funnel_stage',
                'topic_fingerprint', 'canonical_article_id', 'duplicate_score', 'duplicate_status',
                'unique_promise', 'excluded_topics',
            ]);
        });

        Schema::table('content_briefs', function (Blueprint $table) {
            $table->dropColumn(['entity_key', 'topic_key', 'content_angle', 'unique_promise', 'excluded_topics', 'funnel_stage']);
        });
    }
};
