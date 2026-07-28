<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EditorialIdea extends Model
{
    protected $guarded = [];

    protected $casts = [
        'excluded_topics' => 'array',
        'outline' => 'array',
        'brief_details' => 'array',
        'seo_score' => 'float',
        'similarity_score' => 'float',
        'source_coverage' => 'float',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(EditorialPlan::class, 'editorial_plan_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function contentCluster(): BelongsTo
    {
        return $this->belongsTo(ContentCluster::class);
    }

    public function closestArticle(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'closest_article_id');
    }

    public function runItems(): HasMany
    {
        return $this->hasMany(ContentRunItem::class);
    }

    public function scheduledTasks(): HasMany
    {
        return $this->hasMany(ScheduledContentTask::class);
    }

    public function blueprint(): array
    {
        return [
            'entity' => $this->entity_key,
            'topic' => $this->topic_key,
            'intent' => $this->intent,
            'angle' => $this->angle,
            'audience' => $this->audience,
            'problem' => $this->problem,
            'expected_outcome' => $this->expected_outcome,
            'funnel_stage' => $this->funnel_stage,
            'primary_keyword' => $this->primary_keyword,
            'content_cluster_id' => $this->content_cluster_id,
            'cluster_type' => $this->contentCluster?->type,
            'unique_promise' => $this->unique_promise,
            'excluded_topics' => $this->excluded_topics ?? [],
            'outline' => $this->outline ?? [],
            'roadmap_level' => $this->roadmap_level,
            'brief_details' => $this->brief_details ?? [],
            'fingerprint' => $this->fingerprint,
            'conversion_goal' => $this->conversion_goal ?? 'general',
            'thumbnail_title' => $this->thumbnail_title,
        ];
    }
}
