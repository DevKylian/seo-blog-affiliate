<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['editorial_audience', 'content_angle', 'unique_promise', 'excluded_topics']);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->text('editorial_audience')->nullable();
            $table->text('content_angle')->nullable();
            $table->text('unique_promise')->nullable();
            $table->text('excluded_topics')->nullable();
        });
    }
};
