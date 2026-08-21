<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Article extends Model
{
    protected $guarded = [];

    

    protected $casts = [
        'source_ids' => 'array',
        'quality_checks' => 'array',
        'verified_at' => 'datetime',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'content_blocks' => 'array',
        'excluded_topics' => 'array',
        'duplicate_score' => 'float',
        'affiliate_priority' => 'float',
        'prepublish_score' => 'float',
        'prepublish_audited_at' => 'datetime',
        'title_embedding' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function contentCluster(): BelongsTo
    {
        return $this->belongsTo(ContentCluster::class);
    }

    public function brief(): BelongsTo
    {
        return $this->belongsTo(ContentBrief::class, 'content_brief_id');
    }

    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(SeoProject::class, 'article_tool')->withPivot('role');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(SourcePage::class, 'article_sources')->withPivot('citation_label')->withTimestamps();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ArticleVersion::class);
    }

    public function internalLinks(): HasMany
    {
        return $this->hasMany(InternalLink::class, 'source_article_id');
    }

    public function affiliateClicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ArticleAudit::class);
    }

    public function latestAudit(): HasOne
    {
        return $this->hasOne(ArticleAudit::class)->latestOfMany('audited_at');
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

    public function canonicalArticle(): BelongsTo
    {
        return $this->belongsTo(self::class, 'canonical_article_id');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'canonical_article_id');
    }

    public function getPublicUrlAttribute(): string
    {
        return match ($this->type) {
            'comparison' => route('comparisons.show', $this->slug),
            'alternatives' => route('alternatives.show', $this->slug),
            'best_tools' => route('best-tools.show', $this->slug),
            'review' => route('reviews.show', $this->slug),
            'guide' => route('guides.show', $this->slug),
            default => route('blog.show', $this->slug),
        };
    }

    public function getPublicPathAttribute(): string
    {
        return match ($this->type) {
            'comparison' => route('comparisons.show', $this->slug, false),
            'alternatives' => route('alternatives.show', $this->slug, false),
            'best_tools' => route('best-tools.show', $this->slug, false),
            'review' => route('reviews.show', $this->slug, false),
            'guide' => route('guides.show', $this->slug, false),
            default => route('blog.show', $this->slug, false),
        };
    }

    public function getReadingTimeAttribute(): int
    {
        $text = '';
        if (is_array($this->content_blocks)) {
            foreach ($this->content_blocks as $block) {
                if (isset($block['content'])) {
                    $text .= ' ' . $block['content'];
                }
            }
        } else {
            $text = $this->body ?? '';
        }
        
        $wordCount = str_word_count(strip_tags($text));
        $minutes = (int) ceil($wordCount / 250);
        return max(1, $minutes);
    }
}
