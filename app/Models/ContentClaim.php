<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentClaim extends Model
{
    protected $guarded = [];

    protected $casts = [
        'usable_for' => 'array',
        'metadata' => 'array',
        'confidence_score' => 'float',
        'verified_at' => 'datetime',
        'next_refresh_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function sourcePage(): BelongsTo
    {
        return $this->belongsTo(SourcePage::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function refreshTasks(): HasMany
    {
        return $this->hasMany(ContentRefreshTask::class);
    }
}
