<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('claim_hash');
            $table->string('claim_type');
            $table->string('subject')->nullable();
            $table->text('claim');
            $table->text('value')->nullable();
            $table->string('status')->default('verified');
            $table->decimal('confidence_score', 4, 2)->default(0.50);
            $table->json('usable_for')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('next_refresh_at')->nullable();
            $table->timestamps();
            $table->unique(['seo_project_id', 'claim_hash']);
            $table->index(['claim_type', 'status']);
            $table->index('next_refresh_at');
        });

        Schema::create('article_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0);
            $table->string('status')->default('needs_review');
            $table->json('category_scores')->nullable();
            $table->json('checks')->nullable();
            $table->json('blocking_reasons')->nullable();
            $table->json('recommendations')->nullable();
            $table->timestamp('audited_at');
            $table->timestamps();
            $table->index(['status', 'audited_at']);
        });

        Schema::create('content_refresh_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('source_page_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('content_claim_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->unsignedTinyInteger('priority')->default(50);
            $table->string('status')->default('queued');
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at', 'priority']);
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('prepublish_status')->default('not_audited')->after('quality_checks');
            $table->decimal('prepublish_score', 5, 2)->nullable()->after('prepublish_status');
            $table->timestamp('prepublish_audited_at')->nullable()->after('prepublish_score');
            $table->string('refresh_status')->nullable()->after('prepublish_audited_at');
            $table->text('refresh_reason')->nullable()->after('refresh_status');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn([
                'prepublish_status',
                'prepublish_score',
                'prepublish_audited_at',
                'refresh_status',
                'refresh_reason',
            ]);
        });

        Schema::dropIfExists('content_refresh_tasks');
        Schema::dropIfExists('article_audits');
        Schema::dropIfExists('content_claims');
    }
};
