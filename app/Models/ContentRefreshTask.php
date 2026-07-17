<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentRefreshTask extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function sourcePage(): BelongsTo
    {
        return $this->belongsTo(SourcePage::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ContentClaim::class, 'content_claim_id');
    }
}
