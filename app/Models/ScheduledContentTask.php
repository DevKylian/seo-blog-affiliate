<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScheduledContentTask extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'retry_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ContentSchedule::class, 'content_schedule_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function editorialIdea(): BelongsTo
    {
        return $this->belongsTo(EditorialIdea::class);
    }

    public function editorialPlan(): BelongsTo
    {
        return $this->belongsTo(EditorialPlan::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ContentRun::class, 'content_run_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
