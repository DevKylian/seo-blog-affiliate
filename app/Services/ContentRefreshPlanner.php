<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentClaim;
use App\Models\ContentRefreshTask;
use App\Models\SeoProject;
use App\Models\SourcePage;

final class ContentRefreshPlanner
{
    public function __construct(private readonly ContentClaimService $claims) {}

    /**
     * @return array{created:int, skipped:int}
     */
    public function plan(?SeoProject $project = null, int $limit = 100): array
    {
        $created = 0;
        $skipped = 0;
        $projects = $project
            ? collect([$project])
            : SeoProject::query()->where('status', 'active')->orderBy('id')->get();

        foreach ($projects as $seoProject) {
            if ($created >= $limit) {
                break;
            }

            $this->claims->syncProject($seoProject);
            $seoProject->loadMissing(['sourcePages', 'contentClaims', 'articles.latestAudit', 'affiliateClicks']);

            foreach ($seoProject->sourcePages->where('status', 'verified')->where('type', 'pricing') as $source) {
                if ($created >= $limit) {
                    break;
                }
                if ($source->verified_at && $source->verified_at->gte(now()->subDays(14))) {
                    continue;
                }
                $task = $this->queueTask(
                    $seoProject,
                    'pricing_source_stale',
                    15,
                    source: $source,
                    payload: ['url' => $source->url, 'verified_at' => $source->verified_at?->toDateString()],
                );
                $task ? $created++ : $skipped++;
            }

            $staleClaims = $seoProject->contentClaims
                ->filter(fn (ContentClaim $claim): bool => $claim->next_refresh_at !== null && $claim->next_refresh_at->isPast())
                ->sortBy(fn (ContentClaim $claim): int => $this->claimPriority($claim));
            foreach ($staleClaims as $claim) {
                if ($created >= $limit) {
                    break;
                }
                $task = $this->queueTask(
                    $seoProject,
                    'claim_stale',
                    $this->claimPriority($claim),
                    source: $claim->sourcePage,
                    claim: $claim,
                    payload: ['claim_type' => $claim->claim_type, 'subject' => $claim->subject],
                );
                $task ? $created++ : $skipped++;
            }

            foreach ($seoProject->articles->whereIn('status', ['review', 'scheduled', 'published']) as $article) {
                if ($created >= $limit) {
                    break;
                }

                $reason = $this->articleRefreshReason($article);
                if ($reason === null) {
                    continue;
                }

                $task = $this->queueTask(
                    $seoProject,
                    $reason,
                    $this->articlePriority($article, $reason),
                    article: $article,
                    payload: [
                        'article_status' => $article->status,
                        'last_audit_at' => $article->latestAudit?->audited_at?->toDateTimeString(),
                        'verified_at' => $article->verified_at?->toDateString(),
                    ],
                );
                $task ? $created++ : $skipped++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function articleRefreshReason(Article $article): ?string
    {
        if (! $article->latestAudit || $article->latestAudit->audited_at->lt($article->updated_at)) {
            return 'audit_outdated';
        }

        if ($article->status !== 'published') {
            return null;
        }

        $verifiedAt = $article->verified_at ?: $article->published_at ?: $article->updated_at;
        $highRisk = in_array($article->type, ['pricing', 'comparison', 'best_tools', 'alternatives'], true)
            || preg_match('/\b(?:tva|urssaf|imp[oô]t|fiscal|l[eé]gal|facturation [ée]lectronique|comptabilit[ée])\b/iu', $article->title.' '.$article->primary_keyword.' '.$article->body) === 1;

        if ($highRisk && $verifiedAt->lt(now()->subDays(30))) {
            return 'high_risk_article_refresh';
        }
        if (! $highRisk && $verifiedAt->lt(now()->subDays(90))) {
            return 'evergreen_article_refresh';
        }
        if ((float) ($article->affiliate_priority ?? 0) > 0 && $article->published_at?->lt(now()->subDays(30)) && $article->affiliateClicks()->count() === 0) {
            return 'conversion_review';
        }

        return null;
    }

    private function claimPriority(ContentClaim $claim): int
    {
        return match ($claim->claim_type) {
            'price', 'free_plan', 'trial', 'legal' => 10,
            'limitation' => 25,
            default => 45,
        };
    }

    private function articlePriority(Article $article, string $reason): int
    {
        return match ($reason) {
            'audit_outdated' => in_array($article->status, ['scheduled', 'published'], true) ? 12 : 35,
            'high_risk_article_refresh' => 18,
            'conversion_review' => 30,
            default => 55,
        };
    }

    private function queueTask(
        SeoProject $project,
        string $reason,
        int $priority,
        ?Article $article = null,
        ?SourcePage $source = null,
        ?ContentClaim $claim = null,
        array $payload = [],
    ): ?ContentRefreshTask {
        $existing = ContentRefreshTask::query()
            ->where('seo_project_id', $project->id)
            ->where('reason', $reason)
            ->whereIn('status', ['queued', 'processing'])
            ->when($article, fn ($query) => $query->where('article_id', $article->id), fn ($query) => $query->whereNull('article_id'))
            ->when($source, fn ($query) => $query->where('source_page_id', $source->id), fn ($query) => $query->whereNull('source_page_id'))
            ->when($claim, fn ($query) => $query->where('content_claim_id', $claim->id), fn ($query) => $query->whereNull('content_claim_id'))
            ->exists();

        if ($existing) {
            return null;
        }

        return ContentRefreshTask::query()->create([
            'seo_project_id' => $project->id,
            'article_id' => $article?->id,
            'source_page_id' => $source?->id,
            'content_claim_id' => $claim?->id,
            'reason' => $reason,
            'priority' => max(1, min(100, $priority)),
            'status' => 'queued',
            'payload' => $payload,
            'scheduled_at' => now(),
        ]);
    }
}
