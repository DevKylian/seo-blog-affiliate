<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\GeminiContentGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class RunArticleRegenerationWorker extends Command
{
    protected $signature = 'article:regenerate-worker {articleId : Identifiant de l article a regenerer}';

    protected $description = 'Regénère un article en arrière-plan sans bloquer Livewire/Nginx';

    public function handle(GeminiContentGenerator $generator): int
    {
        set_time_limit(0);

        $articleId = (int) $this->argument('articleId');
        $article = Article::query()->with(['project', 'keyword', 'brief'])->find($articleId);
        if (! $article) {
            $this->error("Article {$articleId} introuvable.");

            return self::FAILURE;
        }

        $lock = Cache::lock("article-regeneration-worker:{$articleId}", 7200);
        if (! $lock->get()) {
            $this->line("Une régénération traite déjà l'article {$articleId}.");

            return self::SUCCESS;
        }

        try {
            $checks = $article->quality_checks ?? [];
            if (($checks['regeneration_status'] ?? null) === 'cancelled') {
                $this->line("Régénération annulée pour l'article {$articleId}.");

                return self::SUCCESS;
            }

            if (! empty($checks['regeneration_user_id'])) {
                Auth::onceUsingId((int) $checks['regeneration_user_id']);
            }

            $this->mark($article, [
                'regeneration_status' => 'processing',
                'regeneration_started_at' => now()->toDateTimeString(),
                'regeneration_error' => null,
            ]);

            $updated = $generator->regenerateArticle($article, 'Regeneration manuelle depuis la bibliotheque articles.');
            $this->mark($updated, [
                'regeneration_status' => 'completed',
                'regeneration_finished_at' => now()->toDateTimeString(),
                'regeneration_error' => null,
            ]);

            $this->info("Article {$articleId} régénéré.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->mark($article->fresh() ?: $article, [
                'regeneration_status' => 'failed',
                'regeneration_finished_at' => now()->toDateTimeString(),
                'regeneration_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function mark(Article $article, array $state): void
    {
        $article->forceFill([
            'quality_checks' => array_merge($article->quality_checks ?? [], $state),
        ])->save();
    }
}
