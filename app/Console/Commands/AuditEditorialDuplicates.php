<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\EditorialConsolidationService;
use App\Services\EditorialDuplicateDetector;
use Illuminate\Console\Command;

class AuditEditorialDuplicates extends Command
{
    protected $signature = 'content:deduplicate {--merge : Fusionner et archiver les empreintes strictement identiques}';

    protected $description = 'Calcule les empreintes éditoriales et consolide les doublons stricts';

    public function handle(EditorialDuplicateDetector $detector, EditorialConsolidationService $consolidation): int
    {
        Article::query()->whereIn('status', ['draft', 'review', 'scheduled', 'published'])->each(
            fn (Article $article) => $detector->hydrateArticleFingerprint($article)
        );

        $groups = Article::query()
            ->whereIn('status', ['draft', 'review', 'scheduled', 'published'])
            ->whereNotNull('topic_fingerprint')
            ->get()
            ->groupBy('topic_fingerprint')
            ->filter(fn ($articles) => $articles->count() > 1);

        foreach ($groups as $fingerprint => $articles) {
            $canonical = $articles->sortBy(fn (Article $article) => (preg_match('/-\d+$/', $article->slug) === 1 ? 10_000 : 0) + mb_strlen($article->slug)
            )->first();
            $this->warn("{$articles->count()} doublons — {$fingerprint} — canonique #{$canonical->id}");

            if ($this->option('merge')) {
                foreach ($articles->where('id', '!=', $canonical->id) as $duplicate) {
                    $canonical = $consolidation->merge($duplicate, $canonical, 100);
                    $this->line("  fusion #{$duplicate->id} → #{$canonical->id}");
                }
            } else {
                foreach ($articles->where('id', '!=', $canonical->id) as $duplicate) {
                    $duplicate->update([
                        'canonical_article_id' => $canonical->id,
                        'duplicate_score' => 100,
                        'duplicate_status' => 'potential',
                    ]);
                }
            }
        }

        $this->info($groups->count().' groupe(s) de doublons détecté(s).');

        return self::SUCCESS;
    }
}
