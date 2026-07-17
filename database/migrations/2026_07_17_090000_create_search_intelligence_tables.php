<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_performance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40)->index();
            $table->string('site_url')->nullable();
            $table->text('page_url')->nullable();
            $table->string('url_hash', 64)->index();
            $table->text('query')->nullable();
            $table->string('query_hash', 64)->index();
            $table->string('country', 8)->nullable();
            $table->string('device', 40)->nullable();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 5)->default(0);
            $table->decimal('position', 8, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->unique(
                ['provider', 'site_url', 'url_hash', 'query_hash', 'country', 'device', 'date_from', 'date_to'],
                'search_perf_unique_row',
            );
            $table->index(['provider', 'date_to', 'impressions']);
            $table->index(['article_id', 'date_to']);
        });

        Schema::create('seo_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 40)->default('search_intelligence');
            $table->string('type', 80)->index();
            $table->unsignedTinyInteger('priority')->default(50)->index();
            $table->string('status', 40)->default('queued')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'due_at']);
        });

        Schema::create('serp_differentiation_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->string('primary_keyword')->nullable();
            $table->string('status', 40)->default('ready')->index();
            $table->json('competing_articles')->nullable();
            $table->json('query_evidence')->nullable();
            $table->json('required_angles')->nullable();
            $table->json('missing_sections')->nullable();
            $table->text('prompt_directive')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->index(['seo_project_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serp_differentiation_briefs');
        Schema::dropIfExists('seo_action_items');
        Schema::dropIfExists('search_performance_snapshots');
    }
};
