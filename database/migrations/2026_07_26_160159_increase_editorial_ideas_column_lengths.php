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
        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->string('entity_key', 500)->change();
            $table->string('topic_key', 500)->change();
            $table->string('intent', 255)->change();
            $table->string('angle', 1000)->change();
            $table->string('audience', 1000)->change();
            $table->string('funnel_stage', 255)->change();
            $table->string('content_type', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->string('entity_key', 150)->change();
            $table->string('topic_key', 150)->change();
            $table->string('intent', 50)->change();
            $table->string('angle', 150)->change();
            $table->string('audience', 150)->change();
            $table->string('funnel_stage', 50)->change();
            $table->string('content_type', 50)->change();
        });
    }
};
