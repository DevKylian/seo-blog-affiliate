<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ConfigSyncService
{
    /**
     * Les tables structurelles (configuration), à l'exclusion des contenus (articles, briefs, plans)
     * et à l'exclusion des données système (users, logs, settings, migrations).
     */
    private array $configTables = [
        'seo_projects',
        'affiliate_tools',
        'affiliate_offers',
        'competitor_tools',
        'competitor_offers',
        'content_clusters',
        'keywords',
        'source_pages'
    ];

    public function export(): string
    {
        $data = [];

        foreach ($this->configTables as $table) {
            if (Schema::hasTable($table)) {
                $data[$table] = DB::table($table)->get()->toArray();
            }
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function import(string $jsonContent): void
    {
        $data = json_decode($jsonContent, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Fichier de synchronisation invalide ou corrompu.");
        }

        DB::transaction(function () use ($data) {
            Schema::disableForeignKeyConstraints();

            // 1. Delete old data for structural tables only
            foreach ($this->configTables as $table) {
                if (isset($data[$table]) && Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            // 2. Insert new data safely
            foreach ($this->configTables as $table) {
                if (isset($data[$table]) && count($data[$table]) > 0 && Schema::hasTable($table)) {
                    $chunks = array_chunk($data[$table], 500);
                    foreach ($chunks as $chunk) {
                        // Use insertOrIgnore to bypass case/accent sensitivity unique collisions (SQLite vs MySQL)
                        DB::table($table)->insertOrIgnore($chunk);
                    }
                }
            }

            Schema::enableForeignKeyConstraints();
        });
    }
}
