<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Plan;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Abby
        Plan::where('competitor_name', 'like', 'Abby%')
            ->orWhereHas('project', fn($q) => $q->where('name', 'like', 'Abby%'))
            ->update(['free_trial_days' => 14]);

        Plan::where(function($q) {
                $q->where('competitor_name', 'like', 'Abby%')
                  ->orWhereHas('project', fn($q2) => $q2->where('name', 'like', 'Abby%'));
            })
            ->where('name', 'like', '%Start%')
            ->update(['monthly_price' => 9.0]);

        Plan::where(function($q) {
                $q->where('competitor_name', 'like', 'Abby%')
                  ->orWhereHas('project', fn($q2) => $q2->where('name', 'like', 'Abby%'));
            })
            ->where('name', 'like', '%Pro%')
            ->update(['monthly_price' => 15.0]);

        Plan::where(function($q) {
                $q->where('competitor_name', 'like', 'Abby%')
                  ->orWhereHas('project', fn($q2) => $q2->where('name', 'like', 'Abby%'));
            })
            ->where('name', 'like', '%Business%')
            ->update(['monthly_price' => 33.0]);

        // 2. Freebe
        Plan::where('competitor_name', 'like', 'Freebe%')
            ->orWhereHas('project', fn($q) => $q->where('name', 'like', 'Freebe%'))
            ->update(['free_trial_days' => 30]);

        // 3. Pennylane
        Plan::where('competitor_name', 'like', 'Pennylane%')
            ->orWhereHas('project', fn($q) => $q->where('name', 'like', 'Pennylane%'))
            ->update(['free_trial_days' => 15]);
    }

    public function down(): void
    {
    }
};
