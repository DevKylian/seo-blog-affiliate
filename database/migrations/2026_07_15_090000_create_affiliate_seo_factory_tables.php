<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->string('intent_type', 30)->default('information')->after('intent')->index();
            $table->decimal('affiliate_priority', 5, 2)->default(0)->after('opportunity_score')->index();
            $table->string('affiliate_cluster', 80)->nullable()->after('cluster')->index();
            $table->string('user_moment', 80)->nullable()->after('affiliate_cluster');
            $table->string('problem_label')->nullable()->after('user_moment');
            $table->string('solution_label')->nullable()->after('problem_label');
        });

        Schema::table('content_clusters', function (Blueprint $table) {
            $table->string('intent_type', 30)->default('information')->after('intent')->index();
            $table->decimal('affiliate_priority', 5, 2)->default(0)->after('opportunity_score')->index();
            $table->string('affiliate_cluster', 80)->nullable()->after('intent_type')->index();
        });

        Schema::table('content_briefs', function (Blueprint $table) {
            $table->string('intent_type', 30)->default('information')->after('type')->index();
            $table->decimal('affiliate_priority', 5, 2)->default(0)->after('intent_type');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('intent_type', 30)->default('information')->after('type')->index();
            $table->decimal('affiliate_priority', 5, 2)->default(0)->after('intent_type');
        });

        Schema::create('keyword_seeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('seed');
            $table->string('affiliate_cluster', 80)->index();
            $table->string('intent_type', 30)->default('information')->index();
            $table->unsignedTinyInteger('indy_fit')->default(3);
            $table->json('variations')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['seo_project_id', 'seed']);
        });

        Schema::create('affiliate_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 40)->default('cta')->index();
            $table->string('affiliate_cluster', 80)->nullable()->index();
            $table->string('intent_type', 30)->nullable()->index();
            $table->string('position', 40)->default('after_intro')->index();
            $table->string('title');
            $table->text('description');
            $table->string('cta');
            $table->string('style', 40)->default('standard');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('affiliate_block_id')->nullable()->constrained()->nullOnDelete();
            $table->text('page_url')->nullable();
            $table->text('target_url');
            $table->string('affiliate_cluster', 80)->nullable()->index();
            $table->string('intent_type', 30)->nullable()->index();
            $table->string('position', 40)->nullable()->index();
            $table->string('device', 30)->nullable()->index();
            $table->text('referrer')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('clicked_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliate_blocks');
        Schema::dropIfExists('keyword_seeds');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['intent_type', 'affiliate_priority']);
        });

        Schema::table('content_briefs', function (Blueprint $table) {
            $table->dropColumn(['intent_type', 'affiliate_priority']);
        });

        Schema::table('content_clusters', function (Blueprint $table) {
            $table->dropColumn(['intent_type', 'affiliate_priority', 'affiliate_cluster']);
        });

        Schema::table('keywords', function (Blueprint $table) {
            $table->dropColumn([
                'intent_type',
                'affiliate_priority',
                'affiliate_cluster',
                'user_moment',
                'problem_label',
                'solution_label',
            ]);
        });
    }
};
