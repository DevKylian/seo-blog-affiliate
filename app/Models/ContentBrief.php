<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentBrief extends Model
{
    protected $guarded = [];

    protected $casts = [
        'outline' => 'array',
        'source_ids' => 'array',
        'excluded_topics' => 'array',
        'affiliate_priority' => 'float',
    ];
}
