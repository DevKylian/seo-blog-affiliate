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
        Schema::create('editorial_pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('theme');
            $table->string('status')->default('pending'); // pending, waiting_for_data, processing, completed, error, human_validation
            $table->string('current_agent')->nullable();
            $table->string('run_id')->unique();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editorial_pipelines');
    }
};
