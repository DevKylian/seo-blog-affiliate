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
        Schema::table('seo_projects', function (Blueprint $table) {
            $table->string('brand_color')->nullable()->after('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
        public function down(): void
    {
        Schema::table('seo_projects', function (Blueprint $table) {
            $table->dropColumn('brand_color');
        });
    }
};

