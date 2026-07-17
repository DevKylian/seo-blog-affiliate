<?php

namespace App\Services;

class SearchIndexingSubmissionLauncher
{
    public function __construct(private readonly BackgroundArtisanLauncher $launcher) {}

    public function launch(int $articleId): void
    {
        $articleId = max(1, $articleId);
        $this->launcher->launch('search:index-article', $articleId, "search-indexing-{$articleId}.log");
    }
}
