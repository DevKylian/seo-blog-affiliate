<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourcePage extends Model
{
    protected $guarded = [];

    protected $casts = ['verified_at' => 'datetime', 'confidence_score' => 'float'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function evidenceChunks(): HasMany
    {
        return $this->hasMany(EvidenceChunk::class);
    }
}
