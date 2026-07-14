<?php

namespace App\Services;

final class SourceCrawlWorkerLauncher
{
    public function __construct(private readonly BackgroundArtisanLauncher $launcher) {}

    public function launch(int $sourceId): void
    {
        $sourceId = max(1, $sourceId);
        $this->launcher->launch('scraping:collect-source', $sourceId, "source-crawl-{$sourceId}.log");
    }
}
