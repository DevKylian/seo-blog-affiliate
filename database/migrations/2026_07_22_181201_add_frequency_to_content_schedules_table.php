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
        Schema::table('content_schedules', function (Blueprint $table) {
            $table->string('frequency_type')->default('daily')->after('is_active');
            $table->integer('posts_per_interval')->default(1)->after('frequency_type');
            $table->dateTime('start_date')->nullable()->after('posts_per_interval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_schedules', function (Blueprint $table) {
            $table->dropColumn(['frequency_type', 'posts_per_interval', 'start_date']);
        });
    }
};
