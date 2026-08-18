<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EditorialPipeline extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function seoProject()
    {
        return $this->belongsTo(SeoProject::class);
    }

    public function pipelineArtifacts()
    {
        return $this->hasMany(PipelineArtifact::class);
    }
}
