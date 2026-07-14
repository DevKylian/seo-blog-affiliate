<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (['integration-comptable', 'crm-finance', 'facturation-crm', 'devis-factures', 'paiements-crm'] as $alias) {
            DB::table('topic_aliases')->updateOrInsert(
                ['alias' => $alias],
                ['canonical_topic' => 'crm-facturation', 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        DB::table('topic_aliases')->whereIn('alias', [
            'integration-comptable', 'crm-finance', 'facturation-crm', 'devis-factures', 'paiements-crm',
        ])->delete();
    }
};
