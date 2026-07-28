<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\BlogThumbnailService;
use Illuminate\Http\Response;

class OgImageController extends Controller
{
    public function show(Article $article, BlogThumbnailService $service): Response
    {
        if (request()->has('force')) {
            $service->forget($article->slug);
        }

        return $service->httpResponse(
            $article->slug,
            $article->title,
            'BUSINESSKIT',
            null,
            $article->updated_at
        );
    }
}
