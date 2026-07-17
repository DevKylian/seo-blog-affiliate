<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchIndexingSubmission extends Model
{
    protected $guarded = [];

    protected $casts = [
        'response' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
