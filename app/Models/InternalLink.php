<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalLink extends Model
{
    protected $guarded = [];

    protected $casts = ['automatic' => 'boolean'];

    public function target(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'target_article_id');
    }
}
