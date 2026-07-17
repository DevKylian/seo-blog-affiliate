<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ContentSchedule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'auto_publish' => 'boolean',
        'is_active' => 'boolean',
        'last_dispatched_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ScheduledContentTask::class);
    }

    public function editorialPlans(): HasMany
    {
        return $this->hasMany(EditorialPlan::class);
    }

    public function contentClusters(): HasMany
    {
        return $this->hasMany(ContentCluster::class, 'seo_project_id', 'seo_project_id');
    }
}
