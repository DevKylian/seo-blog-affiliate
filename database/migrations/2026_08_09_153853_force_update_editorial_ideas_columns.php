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
        if (\Illuminate\Support\Facades\Schema::getConnection()->getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE editorial_ideas MODIFY angle TEXT, MODIFY audience TEXT, MODIFY entity_key VARCHAR(500), MODIFY topic_key VARCHAR(500), MODIFY intent VARCHAR(255), MODIFY funnel_stage VARCHAR(255), MODIFY content_type VARCHAR(255);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
