<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\BlogThumbnailService;
use Illuminate\Http\Response;

class OgImageController extends Controller
{
    public function show(Article $article, BlogThumbnailService $service): Response
    {
        return $service->httpResponse(
            $article->slug,
            $article->title,
            'BUSINESSKIT',
            null,
            $article->updated_at
        );
    }
}
