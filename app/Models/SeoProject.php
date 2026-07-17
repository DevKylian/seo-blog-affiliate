<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoProject extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'last_crawled_at' => 'datetime',
        'features' => 'array',
        'strengths' => 'array',
        'limitations' => 'array',
        'best_for' => 'array',
        'competitors' => 'array',
        'competitor_pricing_urls' => 'array',
    ];

    public function sourcePages(): HasMany
    {
        return $this->hasMany(SourcePage::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class)->whereNull('competitor_name');
    }

    public function pricingPlans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function competitorPlans(): HasMany
    {
        return $this->hasMany(Plan::class)->whereNotNull('competitor_name');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function contentClusters(): HasMany
    {
        return $this->hasMany(ContentCluster::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function contentRuns(): HasMany
    {
        return $this->hasMany(ContentRun::class);
    }

    public function editorialPlans(): HasMany
    {
        return $this->hasMany(EditorialPlan::class);
    }

    public function contentSchedules(): HasMany
    {
        return $this->hasMany(ContentSchedule::class);
    }

    public function keywordSeeds(): HasMany
    {
        return $this->hasMany(KeywordSeed::class);
    }

    public function affiliateBlocks(): HasMany
    {
        return $this->hasMany(AffiliateBlock::class);
    }

    public function affiliateClicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function scheduledContentTasks(): HasMany
    {
        return $this->hasMany(ScheduledContentTask::class);
    }

    public function contentClaims(): HasMany
    {
        return $this->hasMany(ContentClaim::class);
    }

    public function refreshTasks(): HasMany
    {
        return $this->hasMany(ContentRefreshTask::class);
    }

    public function searchPerformanceSnapshots(): HasMany
    {
        return $this->hasMany(SearchPerformanceSnapshot::class);
    }

    public function seoActionItems(): HasMany
    {
        return $this->hasMany(SeoActionItem::class);
    }

    public function differentiationBriefs(): HasMany
    {
        return $this->hasMany(SerpDifferentiationBrief::class);
    }
}
