<?php

namespace App\Services;

class ArticleRegenerationWorkerLauncher
{
    public function __construct(private readonly BackgroundArtisanLauncher $launcher) {}

    public function launch(int $articleId): void
    {
        $articleId = max(1, $articleId);
        $this->launcher->launch('article:regenerate-worker', $articleId, "article-regeneration-{$articleId}.log");
    }
}
