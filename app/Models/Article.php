<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
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
            default => route('blog.show', $this->slug),
        };
    }
}
