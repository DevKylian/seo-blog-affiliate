<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_projects', function (Blueprint $table): void {
            $table->json('competitor_pricing_urls')->nullable()->after('competitors');
        });

        Schema::table('source_pages', function (Blueprint $table): void {
            $table->string('competitor_name')->nullable()->after('type')->index();
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->string('competitor_name')->nullable()->after('source_page_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('competitor_name');
        });

        Schema::table('source_pages', function (Blueprint $table): void {
            $table->dropColumn('competitor_name');
        });

        Schema::table('seo_projects', function (Blueprint $table): void {
            $table->dropColumn('competitor_pricing_urls');
        });
    }
};
