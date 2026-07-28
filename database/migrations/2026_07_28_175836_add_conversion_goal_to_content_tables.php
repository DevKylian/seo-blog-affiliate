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
        Schema::table('articles', function (Blueprint $table) {
            $table->string('conversion_goal')->nullable()->after('affiliate_priority');
        });

        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->string('conversion_goal')->nullable()->after('funnel_stage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('conversion_goal');
        });

        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->dropColumn('conversion_goal');
        });
    }
};
