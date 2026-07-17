<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchPerformanceSnapshot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'float',
        'position' => 'float',
        'metadata' => 'array',
        'imported_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
