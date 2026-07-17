<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ContentCluster extends Model
{
    protected $guarded = [];

    protected $casts = [
        'keyword_count' => 'integer',
        'total_search_volume' => 'integer',
        'average_difficulty' => 'float',
        'max_difficulty' => 'float',
        'max_cpc' => 'float',
        'opportunity_score' => 'float',
        'affiliate_priority' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function canonicalKeyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'canonical_keyword_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function editorialIdeas(): HasMany
    {
        return $this->hasMany(EditorialIdea::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function scheduledTasks(): HasMany
    {
        return $this->hasMany(ScheduledContentTask::class);
    }

    public function isPillar(): bool
    {
        return $this->type === 'pillar';
    }
}
