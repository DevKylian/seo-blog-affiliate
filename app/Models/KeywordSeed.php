<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordSeed extends Model
{
    protected $guarded = [];

    protected $casts = [
        'variations' => 'array',
        'is_active' => 'boolean',
        'indy_fit' => 'integer',
        'last_expanded_at' => 'datetime',
        'fetched_keywords_count' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }
}
