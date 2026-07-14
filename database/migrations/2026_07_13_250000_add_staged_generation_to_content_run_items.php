<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_run_items', function (Blueprint $table) {
            $table->json('generation_parts')->nullable()->after('error_message');
            $table->unsignedTinyInteger('generation_step')->default(0)->after('generation_parts');
            $table->unsignedTinyInteger('api_attempts')->default(0)->after('generation_step');
        });
    }

    public function down(): void
    {
        Schema::table('content_run_items', function (Blueprint $table) {
            $table->dropColumn(['generation_parts', 'generation_step', 'api_attempts']);
        });
    }
};
