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
            $table->text('angle')->nullable()->change();
            $table->text('audience')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('editorial_ideas', function (Blueprint $table) {
            $table->string('angle', 1000)->nullable()->change();
            $table->string('audience', 1000)->nullable()->change();
        });
    }
};
