<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EditorialPlan extends Model
{
    protected $guarded = [];

    protected $casts = ['locked_at' => 'datetime', 'keyword_scope' => 'array', 'content_cluster_scope' => 'array'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function contentSchedule(): BelongsTo
    {
        return $this->belongsTo(ContentSchedule::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ideas(): HasMany
    {
        return $this->hasMany(EditorialIdea::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ContentRun::class);
    }

    public function scheduledTasks(): HasMany
    {
        return $this->hasMany(ScheduledContentTask::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'locked' && $this->accepted_count === $this->requested_count;
    }
}
