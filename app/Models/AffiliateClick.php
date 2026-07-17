<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateClick extends Model
{
    protected $guarded = [];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(AffiliateBlock::class, 'affiliate_block_id');
    }
}
