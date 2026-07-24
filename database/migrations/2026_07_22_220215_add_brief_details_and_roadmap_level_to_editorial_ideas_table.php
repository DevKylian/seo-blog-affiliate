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
            $table->string('roadmap_level')->nullable()->after('status');
            $table->json('brief_details')->nullable()->after('roadmap_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->dropColumn(['roadmap_level', 'brief_details']);
        });
    }
};
