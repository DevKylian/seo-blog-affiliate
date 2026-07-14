<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleVersion extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['content_blocks' => 'array', 'created_at' => 'datetime'];
}
