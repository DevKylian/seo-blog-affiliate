<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoProject extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'last_crawled_at' => 'datetime',
        'features' => 'array',
        'strengths' => 'array',
        'limitations' => 'array',
        'best_for' => 'array',
    ];

    public function sourcePages(): HasMany
    {
        return $this->hasMany(SourcePage::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function contentRuns(): HasMany
    {
        return $this->hasMany(ContentRun::class);
    }

    public function editorialPlans(): HasMany
    {
        return $this->hasMany(EditorialPlan::class);
    }

    public function contentSchedules(): HasMany
    {
        return $this->hasMany(ContentSchedule::class);
    }

    public function scheduledContentTasks(): HasMany
    {
        return $this->hasMany(ScheduledContentTask::class);
    }
}
