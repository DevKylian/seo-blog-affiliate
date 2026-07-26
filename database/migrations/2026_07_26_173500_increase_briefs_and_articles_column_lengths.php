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
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('articles', function (Blueprint $table) {
                $table->unsignedInteger('views')->default(0)->after('status');
            });
            return;
        }

        Schema::table('content_briefs', function (Blueprint $table) {
            $table->string('entity_key', 500)->change();
            $table->string('topic_key', 500)->change();
            $table->string('content_angle', 1000)->change();
            $table->string('angle', 1000)->change();
            $table->string('audience', 1000)->change();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('entity_key', 500)->change();
            $table->string('topic_key', 500)->change();
            $table->string('content_angle', 1000)->change();
            $table->string('editorial_audience', 1000)->change();
            $table->unsignedInteger('views')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropColumn('views');
            });
            return;
        }

        Schema::table('content_briefs', function (Blueprint $table) {
            $table->string('entity_key', 150)->change();
            $table->string('topic_key', 150)->change();
            $table->string('content_angle', 150)->change();
            $table->string('angle', 255)->change();
            $table->string('audience', 255)->change();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('entity_key', 150)->change();
            $table->string('topic_key', 150)->change();
            $table->string('content_angle', 150)->change();
            $table->string('editorial_audience', 150)->change();
            $table->dropColumn('views');
        });
    }
};
