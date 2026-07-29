<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->string('angle', 1000)->default('')->nullable()->change();
            $table->json('excluded_topics')->nullable()->change();
            $table->json('outline')->nullable()->change();
            $table->string('fingerprint', 700)->default('')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->string('angle', 1000)->change();
            $table->json('excluded_topics')->change();
            $table->json('outline')->change();
            $table->string('fingerprint', 700)->change();
        });
    }
};
