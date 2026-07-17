<?php

namespace App\Console\Commands;

use App\Models\SourcePage;
use App\Services\Scraping\StaticSiteScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class RunSourceCrawlWorker extends Command
{
    protected $signature = 'scraping:collect-source {sourceId : Identifiant de la source à collecter}';

    protected $description = 'Collecte une source en arrière-plan, y compris avec Playwright';

    public function handle(StaticSiteScraper $scraper): int
    {
        set_time_limit(0);
        $sourceId = (int) $this->argument('sourceId');
        $source = SourcePage::query()->with('project')->find($sourceId);
        if (! $source || ! $source->project) {
            $this->error("Source {$sourceId} introuvable.");

            return self::FAILURE;
        }

        $lock = Cache::lock("source-crawl-worker:{$sourceId}", 900);
        if (! $lock->get()) {
            $this->line("La source {$sourceId} est déjà en cours de collecte.");

            return self::SUCCESS;
        }

        try {
            $this->info("Collecte autonome de {$source->url}");
            $scraper->scrape($source->project, $source->url, $source->type, $source->competitor_name);
            $this->info('Collecte terminée.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
