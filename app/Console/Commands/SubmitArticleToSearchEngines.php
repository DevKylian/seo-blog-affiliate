<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\SearchEngineIndexingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class SubmitArticleToSearchEngines extends Command
{
    protected $signature = 'search:index-article {articleId : Identifiant de l article publie}';

    protected $description = 'Soumet une URL publiee aux integrations Search Console et IndexNow sans bloquer le dashboard';

    public function handle(SearchEngineIndexingService $indexing): int
    {
        set_time_limit(0);

        $articleId = (int) $this->argument('articleId');
        $article = Article::query()->with(['project', 'keyword'])->find($articleId);
        if (! $article || $article->status !== 'published') {
            $this->warn("Article {$articleId} introuvable ou non publie.");

            return self::SUCCESS;
        }

        $lock = Cache::lock("search-index-article:{$articleId}", 600);
        if (! $lock->get()) {
            $this->line("Soumission deja active pour l article {$articleId}.");

            return self::SUCCESS;
        }

        try {
            $results = $indexing->submitArticle($article, 'published');
            $this->info(count($results).' soumission(s) creee(s) pour '.$article->public_url);

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
