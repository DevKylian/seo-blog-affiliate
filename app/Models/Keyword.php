<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    protected $guarded = [];

    protected $casts = [
        'search_volume' => 'integer',
        'keyword_difficulty' => 'float',
        'cpc' => 'float',
        'current_position' => 'float',
        'opportunity_score' => 'float',
        'affiliate_priority' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function contentCluster(): BelongsTo
    {
        return $this->belongsTo(ContentCluster::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function editorialIdeas(): HasMany
    {
        return $this->hasMany(EditorialIdea::class);
    }

    public function scheduledTasks(): HasMany
    {
        return $this->hasMany(ScheduledContentTask::class);
    }

    public function runItems(): HasMany
    {
        return $this->hasMany(ContentRunItem::class);
    }

    public function strategyTier(): string
    {
        $keyword = mb_strtolower($this->keyword);
        $specialized = preg_match('/\b(?:btp|bâtiment|batiment|artisan|paysagiste|auto[- ]?entrepreneur|tpe|pme|mac|secteur|métier)\b/iu', $keyword) === 1;
        $brandedLongTail = preg_match('/\b(?:temps de prise en main|génération automatique|mode d[’\']emploi|instant|rapide|smart|suite)\b/iu', $keyword) === 1;
        $words = preg_split('/\s+/u', trim($keyword)) ?: [];

        if ($this->search_volume >= 1000 && $this->keyword_difficulty >= 35 && count($words) <= 6 && ! $specialized && ! $brandedLongTail) {
            return 'pillar';
        }
        if ($this->keyword_difficulty <= 30 && $this->search_volume >= 50) {
            return 'quick_win';
        }
        if ($specialized) {
            return 'niche';
        }

        return 'supporting';
    }

    public function getStrategyTierAttribute(): string
    {
        return $this->strategyTier();
    }

    public function hasMeasuredDifficulty(): bool
    {
        // Un KD nul reste une vraie mesure lorsque le mot-clé possède d'autres
        // statistiques. Sans volume, CPC ni KD, l'interface affiche « — ».
        return (float) $this->keyword_difficulty > 0
            || (int) $this->search_volume > 0
            || $this->cpc !== null;
    }

    public function isUnplanned(): bool
    {
        $articlesCount = array_key_exists('articles_count', $this->attributes)
            ? (int) $this->attributes['articles_count']
            : $this->articles()->count();
        $ideasCount = array_key_exists('editorial_ideas_count', $this->attributes)
            ? (int) $this->attributes['editorial_ideas_count']
            : $this->editorialIdeas()->count();

        return $articlesCount === 0 && $ideasCount === 0;
    }
}
