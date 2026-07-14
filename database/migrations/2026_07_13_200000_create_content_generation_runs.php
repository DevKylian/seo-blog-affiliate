<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('requested_count');
            $table->unsignedSmallInteger('completed_count')->default(0);
            $table->unsignedSmallInteger('failed_count')->default(0);
            $table->string('status')->default('pending');
            $table->text('instructions')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('content_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('content_type');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['content_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_run_items');
        Schema::dropIfExists('content_runs');
    }
};
