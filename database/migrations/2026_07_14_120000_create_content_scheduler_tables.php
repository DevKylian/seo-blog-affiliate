<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('articles_per_week')->default(5);
            $table->boolean('auto_publish')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('timezone')->default('Europe/Paris');
            $table->text('instructions')->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamps();
            $table->unique('seo_project_id');
        });

        Schema::create('scheduled_content_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('editorial_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('queued');
            $table->unsignedInteger('priority')->default(100);
            $table->timestamp('scheduled_for');
            $table->timestamp('retry_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['content_schedule_id', 'keyword_id']);
            $table->index(['status', 'scheduled_for', 'priority']);
            $table->index(['status', 'retry_at']);
        });

        Schema::table('editorial_plans', function (Blueprint $table) {
            $table->json('keyword_scope')->nullable()->after('instructions');
        });
    }

    public function down(): void
    {
        Schema::table('editorial_plans', function (Blueprint $table) {
            $table->dropColumn('keyword_scope');
        });
        Schema::dropIfExists('scheduled_content_tasks');
        Schema::dropIfExists('content_schedules');
    }
};
