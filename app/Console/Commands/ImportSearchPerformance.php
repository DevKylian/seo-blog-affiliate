<?php

namespace App\Console\Commands;

use App\Services\SearchPerformanceImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class ImportSearchPerformance extends Command
{
    protected $signature = 'seo:import-performance {runId? : Identifiant technique ignoré} {--days=28 : Fenêtre de données à importer}';

    protected $description = 'Importe les performances Search Console et Bing Webmaster';

    public function handle(SearchPerformanceImportService $importer): int
    {
        set_time_limit(0);

        $days = max(1, min(180, (int) $this->option('days')));
        $lock = Cache::lock('seo-import-performance', 900);
        if (! $lock->get()) {
            $this->line('Import SEO déjà en cours.');

            return self::SUCCESS;
        }

        try {
            $result = $importer->import(now()->subDays($days + 2), now()->subDays(2));
            $this->info("Import terminé : {$result['google']} ligne(s) Google, {$result['bing']} ligne(s) Bing.");

            foreach ($result['errors'] as $error) {
                $this->warn($error);
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
