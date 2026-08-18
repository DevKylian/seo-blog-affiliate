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
        Schema::create('pipeline_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('agent_name');
            $table->integer('output_version')->default(1);
            $table->string('status')->default('validated'); // validated, pending_human_validation, error
            $table->json('data')->nullable(); // Can be null if it's just raw markdown stored elsewhere, but let's keep everything structured
            $table->longText('markdown_content')->nullable(); // For long text content
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_artifacts');
    }
};
