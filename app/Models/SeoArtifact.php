<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoArtifact extends Model
{
    use HasFactory;

    protected $fillable = [
        'seo_project_id',
        'type',
        'data',
        'version',
        'hash'
    ];

    protected $casts = [
        'data' => 'array',
        'version' => 'integer',
    ];
}
