<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentRunItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'generation_parts' => 'array',
        'generation_step' => 'integer',
        'api_attempts' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ContentRun::class, 'content_run_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function editorialIdea(): BelongsTo
    {
        return $this->belongsTo(EditorialIdea::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
