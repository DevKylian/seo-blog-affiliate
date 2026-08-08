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
        Schema::table('editorial_plans', function (Blueprint $table) {
            $table->text('error_message')->nullable();
        });

        Schema::table('content_runs', function (Blueprint $table) {
            $table->text('error_message')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('editorial_plans', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });

        Schema::table('content_runs', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });
    }
};
