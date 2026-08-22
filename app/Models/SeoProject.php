<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SeoProject extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleting(function (SeoProject $project) {
            if ($project->screenshot_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($project->screenshot_path);
            }
        });
    }

    protected $casts = [
        'last_crawled_at' => 'datetime',
        'features' => 'array',
        'strengths' => 'array',
        'limitations' => 'array',
        'best_for' => 'array',
        'faq' => 'array',
        'competitors' => 'array',
        'competitor_pricing_urls' => 'array',
    ];

    public function getScreenshotUrlAttribute(): ?string
    {
        return $this->screenshot_path ? asset('storage/' . $this->screenshot_path) : null;
    }

    public function getLogoUrlAttribute(): string
    {
        if (!$this->website_url) {
            return '';
        }
        
        $url = $this->website_url;
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return '';
        }
        
        $host = preg_replace('/^www\./', '', $host);
        
        return "https://www.google.com/s2/favicons?domain={$host}&sz=128";
    }

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

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_tool')->withPivot('role');
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

    public function getBrandTextColorAttribute(): string
    {
        if (!$this->brand_color) {
            return '#ffffff';
        }

        $hex = str_replace('#', '', $this->brand_color);

        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex,0,1), 2) . str_repeat(substr($hex,1,1), 2) . str_repeat(substr($hex,2,1), 2);
        }

        if (strlen($hex) != 6) {
             return '#ffffff';
        }

        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));

        $yiq = (($r*299)+($g*587)+($b*114))/1000;

        return ($yiq >= 160) ? '#0f172a' : '#ffffff';
    }
}
