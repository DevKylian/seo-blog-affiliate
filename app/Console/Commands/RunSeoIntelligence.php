<?php

namespace App\Console\Commands;

use App\Services\SeoIntelligenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class RunSeoIntelligence extends Command
{
    protected $signature = 'seo:intelligence {runId? : Identifiant technique ignoré} {--days=28 : Fenêtre de performance à analyser}';

    protected $description = 'Importe les signaux de recherche et crée les actions SEO prioritaires';

    public function handle(SeoIntelligenceService $intelligence): int
    {
        set_time_limit(0);

        $days = max(7, min(180, (int) $this->option('days')));
        $lock = Cache::lock('seo-intelligence-loop', 1800);
        if (! $lock->get()) {
            $this->line('Boucle SEO déjà en cours.');

            return self::SUCCESS;
        }

        try {
            $result = $intelligence->run($days);
            $import = $result['import'];
            $this->info("Import : {$import['google']} ligne(s) Google, {$import['bing']} ligne(s) Bing.");
            $this->info("Analyse : {$result['actions']} action(s), {$result['briefs']} brief(s), {$result['refresh_tasks']} refresh(s).");

            foreach ($import['errors'] as $error) {
                $this->warn($error);
            }

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
