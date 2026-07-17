<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_seeds', function (Blueprint $table) {
            $table->timestamp('last_expanded_at')->nullable()->after('is_active')->index();
            $table->unsignedInteger('fetched_keywords_count')->default(0)->after('last_expanded_at');
            $table->text('last_error')->nullable()->after('fetched_keywords_count');
        });
    }

    public function down(): void
    {
        Schema::table('keyword_seeds', function (Blueprint $table) {
            $table->dropColumn(['last_expanded_at', 'fetched_keywords_count', 'last_error']);
        });
    }
};
