<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('content_clusters')->nullOnDelete();
            $table->foreignId('canonical_keyword_id')->nullable()->constrained('keywords')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('normalized_key');
            $table->string('type', 40)->default('supporting')->index();
            $table->string('intent', 80)->nullable();
            $table->string('status', 40)->default('queued')->index();
            $table->unsignedInteger('keyword_count')->default(0);
            $table->unsignedInteger('total_search_volume')->default(0);
            $table->decimal('average_difficulty', 5, 2)->default(0);
            $table->decimal('max_difficulty', 5, 2)->default(0);
            $table->decimal('max_cpc', 10, 2)->nullable();
            $table->decimal('opportunity_score', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['seo_project_id', 'normalized_key']);
            $table->index(['seo_project_id', 'type', 'opportunity_score']);
            $table->index(['parent_id', 'type']);
        });

        Schema::table('keywords', function (Blueprint $table) {
            $table->foreignId('content_cluster_id')->nullable()->after('cluster')->constrained()->nullOnDelete();
        });

        Schema::table('editorial_plans', function (Blueprint $table) {
            $table->json('content_cluster_scope')->nullable()->after('keyword_scope');
        });

        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->foreignId('content_cluster_id')->nullable()->after('keyword_id')->constrained()->nullOnDelete();
        });

        Schema::table('content_briefs', function (Blueprint $table) {
            $table->foreignId('content_cluster_id')->nullable()->after('keyword_id')->constrained()->nullOnDelete();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('content_cluster_id')->nullable()->after('keyword_id')->constrained()->nullOnDelete();
        });

        Schema::table('scheduled_content_tasks', function (Blueprint $table) {
            $table->foreignId('content_cluster_id')->nullable()->after('keyword_id')->constrained()->nullOnDelete();
            $table->index(['content_schedule_id', 'content_cluster_id', 'status'], 'scheduled_cluster_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_content_tasks', function (Blueprint $table) {
            $table->dropIndex('scheduled_cluster_status_index');
            $table->dropConstrainedForeignId('content_cluster_id');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_cluster_id');
        });

        Schema::table('content_briefs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_cluster_id');
        });

        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_cluster_id');
        });

        Schema::table('editorial_plans', function (Blueprint $table) {
            $table->dropColumn('content_cluster_scope');
        });

        Schema::table('keywords', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_cluster_id');
        });

        Schema::dropIfExists('content_clusters');
    }
};
