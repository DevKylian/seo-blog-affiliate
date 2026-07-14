<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceChunk extends Model
{
    protected $guarded = [];

    protected $casts = ['verified_at' => 'datetime', 'confidence_score' => 'float'];

    public function sourcePage(): BelongsTo
    {
        return $this->belongsTo(SourcePage::class);
    }
}
