<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ConfigSyncService
{
    /**
     * Tables structurelles (config) : supprimées puis réinsérées intégralement.
     */
    private array $configTables = [
        'seo_projects',
        'affiliate_tools',
        'affiliate_offers',
        'competitor_tools',
        'competitor_offers',
        'content_clusters',
        'keywords',
        'source_pages',
    ];

    /**
     * Tables de planification : ajoutées en "merge" (insertOrIgnore) sans jamais supprimer,
     * pour ne pas casser un run en cours en prod.
     */
    private array $planningTables = [
        'editorial_plans',
        'editorial_ideas',
    ];

    public function export(): string
    {
        $data = [];

        foreach ($this->configTables as $table) {
            if (Schema::hasTable($table)) {
                $data[$table] = DB::table($table)->get()->toArray();
            }
        }

        foreach ($this->planningTables as $table) {
            if (Schema::hasTable($table)) {
                $data[$table] = DB::table($table)->get()->toArray();
            }
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function import(string $jsonContent): void
    {
        $data = json_decode($jsonContent, true);
        if (! is_array($data)) {
            throw new \RuntimeException('Fichier de synchronisation invalide ou corrompu.');
        }

        DB::transaction(function () use ($data) {
            Schema::disableForeignKeyConstraints();

            // 1. Remplacer entièrement les tables de config
            foreach ($this->configTables as $table) {
                if (isset($data[$table]) && Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
            foreach ($this->configTables as $table) {
                if (isset($data[$table]) && count($data[$table]) > 0 && Schema::hasTable($table)) {
                    foreach (array_chunk($data[$table], 500) as $chunk) {
                        DB::table($table)->insertOrIgnore($chunk);
                    }
                }
            }

            // 2. Merger les tables de planification (sans supprimer)
            foreach ($this->planningTables as $table) {
                if (isset($data[$table]) && count($data[$table]) > 0 && Schema::hasTable($table)) {
                    foreach (array_chunk($data[$table], 500) as $chunk) {
                        DB::table($table)->insertOrIgnore($chunk);
                    }
                }
            }

            Schema::enableForeignKeyConstraints();
        });
    }
}
