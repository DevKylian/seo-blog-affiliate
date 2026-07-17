<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleAudit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'score' => 'float',
        'category_scores' => 'array',
        'checks' => 'array',
        'blocking_reasons' => 'array',
        'recommendations' => 'array',
        'audited_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
