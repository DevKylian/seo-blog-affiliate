<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->decimal('monthly_price_max', 12, 2)->nullable()->after('monthly_price');
            $table->decimal('annual_total_max', 12, 2)->nullable()->after('annual_total');
            $table->decimal('annual_effective_monthly_max', 12, 2)->nullable()->after('annual_effective_monthly');
            $table->json('price_variants')->nullable()->after('features');
        });

        Schema::table('price_snapshots', function (Blueprint $table): void {
            $table->decimal('monthly_price_max', 12, 2)->nullable()->after('monthly_price');
            $table->decimal('annual_total_max', 12, 2)->nullable()->after('annual_total');
            $table->decimal('annual_effective_monthly_max', 12, 2)->nullable()->after('annual_effective_monthly');
            $table->json('price_variants')->nullable()->after('raw_price');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['monthly_price_max', 'annual_total_max', 'annual_effective_monthly_max', 'price_variants']);
        });

        Schema::table('price_snapshots', function (Blueprint $table): void {
            $table->dropColumn(['monthly_price_max', 'annual_total_max', 'annual_effective_monthly_max', 'price_variants']);
        });
    }
};
