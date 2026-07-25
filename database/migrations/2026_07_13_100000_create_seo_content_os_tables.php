<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('website_url');
            $table->string('pricing_url')->nullable();
            $table->string('affiliate_url')->nullable();
            $table->string('country', 2)->default('FR');
            $table->string('currency', 3)->default('EUR');
            $table->string('status')->default('active');
            $table->string('crawl_status')->default('pending');
            $table->text('description')->nullable();
            $table->string('positioning')->nullable();
            $table->json('features')->nullable();
            $table->json('strengths')->nullable();
            $table->json('limitations')->nullable();
            $table->json('best_for')->nullable();
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('source_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('other');
            $table->string('url');
            $table->string('title')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('status')->default('pending');
            $table->string('extraction_method')->default('static_html');
            $table->decimal('confidence_score', 4, 2)->default(0.70);
            $table->timestamp('verified_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['seo_project_id', 'url']);
        });

        Schema::create('evidence_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_page_id')->constrained()->cascadeOnDelete();
            $table->string('category')->default('general');
            $table->text('value');
            $table->text('source_excerpt');
            $table->unsignedSmallInteger('position')->default(0);
            $table->decimal('confidence_score', 4, 2)->default(0.70);
            $table->timestamp('verified_at');
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('raw_price')->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->decimal('annual_total', 12, 2)->nullable();
            $table->decimal('annual_effective_monthly', 12, 2)->nullable();
            $table->string('billing_period')->nullable();
            $table->string('price_unit')->nullable();
            $table->unsignedInteger('minimum_seats')->default(1);
            $table->unsignedInteger('free_trial_days')->nullable();
            $table->decimal('setup_fee', 12, 2)->default(0);
            $table->boolean('tax_included')->default(false);
            $table->boolean('promotional_price')->default(false);
            $table->json('usage_limits')->nullable();
            $table->json('features')->nullable();
            $table->decimal('confidence_score', 4, 2)->default(0.50);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->decimal('annual_total', 12, 2)->nullable();
            $table->decimal('annual_effective_monthly', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('raw_price')->nullable();
            $table->timestamp('verified_at');
            $table->timestamps();
        });

        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->unsignedInteger('search_volume')->default(0);
            $table->decimal('keyword_difficulty', 5, 2)->default(0);
            $table->string('intent')->nullable();
            $table->decimal('cpc', 10, 2)->nullable();
            $table->string('trend')->nullable();
            $table->string('country', 2)->nullable();
            $table->text('serp_features')->nullable();
            $table->decimal('current_position', 7, 2)->nullable();
            $table->text('ranking_url')->nullable();
            $table->string('cluster')->nullable();
            $table->decimal('opportunity_score', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['seo_project_id', 'keyword']);
        });

        Schema::create('content_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->string('angle')->nullable();
            $table->string('audience')->nullable();
            $table->string('search_intent')->nullable();
            $table->json('outline');
            $table->json('source_ids')->nullable();
            $table->string('status')->default('ready');
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_brief_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('tool_review');
            $table->string('title');
            $table->string('slug');
            $table->string('status')->default('draft');
            $table->string('primary_keyword')->nullable();
            $table->string('search_intent')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('canonical_url')->nullable();
            $table->longText('body');
            $table->json('content_blocks')->nullable();
            $table->json('source_ids')->nullable();
            $table->json('quality_checks')->nullable();
            $table->string('generated_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
            $table->unique('slug');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('article_category', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'category_id']);
        });

        Schema::create('article_tool', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('featured');
            $table->primary(['article_id', 'seo_project_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('article_tag', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'tag_id']);
        });

        Schema::create('article_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_page_id')->constrained()->cascadeOnDelete();
            $table->string('citation_label')->nullable();
            $table->timestamps();
            $table->unique(['article_id', 'source_page_id']);
        });

        Schema::create('article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->longText('body');
            $table->json('content_blocks')->nullable();
            $table->string('change_note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('internal_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('target_article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('anchor_text');
            $table->boolean('automatic')->default(true);
            $table->timestamps();
            $table->unique(['source_article_id', 'target_article_id']);
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
        });

        Schema::create('admin_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);
            $table->text('path');
            $table->string('route_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_access_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('media');
        Schema::dropIfExists('internal_links');
        Schema::dropIfExists('article_versions');
        Schema::dropIfExists('article_sources');
        Schema::dropIfExists('article_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('article_tool');
        Schema::dropIfExists('article_category');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('content_briefs');
        Schema::dropIfExists('keywords');
        Schema::dropIfExists('price_snapshots');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('evidence_chunks');
        Schema::dropIfExists('source_pages');
        Schema::dropIfExists('seo_projects');
    }
};
