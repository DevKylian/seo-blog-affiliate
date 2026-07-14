<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentRun extends Model
{
    protected $guarded = [];

    protected $casts = ['started_at' => 'datetime', 'completed_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function editorialPlan(): BelongsTo
    {
        return $this->belongsTo(EditorialPlan::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContentRunItem::class);
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->requested_count === 0) {
            return 0;
        }

        // Le pourcentage représente les contenus réellement livrés. Ajouter les
        // échecs faisait afficher 100 % avec zéro article créé.
        return min(100, (int) round(($this->completed_count / $this->requested_count) * 100));
    }
}
