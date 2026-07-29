<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\SeoProject;
use App\Models\Plan;

return new class extends Migration
{
    public function up(): void
    {
        // Fix Abby
        $abby = SeoProject::where('name', 'like', 'Abby%')->first();
        if ($abby) {
            $abby->plans()->update(['free_trial_days' => 14]);
            
            $abby->plans()->where('name', 'like', '%Start%')->update(['monthly_price' => 9.0]);
            $abby->plans()->where('name', 'like', '%Pro%')->update(['monthly_price' => 15.0]);
            $abby->plans()->where('name', 'like', '%Business%')->update(['monthly_price' => 33.0]);
        }

        // Fix Freebe
        $freebe = SeoProject::where('name', 'like', 'Freebe%')->first();
        if ($freebe) {
            $freebe->plans()->update(['free_trial_days' => 30]);
        }

        // Fix Pennylane
        $pennylane = SeoProject::where('name', 'like', 'Pennylane%')->first();
        if ($pennylane) {
            $pennylane->plans()->update(['free_trial_days' => 15]);
        }
    }

    public function down(): void
    {
        // No down needed
    }
};
