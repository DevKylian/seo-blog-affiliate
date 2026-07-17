<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerpDifferentiationBrief extends Model
{
    protected $guarded = [];

    protected $casts = [
        'competing_articles' => 'array',
        'query_evidence' => 'array',
        'required_angles' => 'array',
        'missing_sections' => 'array',
        'generated_at' => 'datetime',
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
}
