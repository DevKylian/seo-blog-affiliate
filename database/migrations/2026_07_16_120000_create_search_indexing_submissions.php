<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_indexing_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40)->index();
            $table->string('type', 40)->index();
            $table->text('url');
            $table->string('status', 40)->default('pending')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->json('response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamps();

            $table->index(['provider', 'status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_indexing_submissions');
    }
};
