<?php

namespace App\Http\Controllers;

use App\Services\SearchEngineIndexingService;
use Illuminate\Http\Response;

class IndexNowKeyController extends Controller
{
    public function __invoke(string $key, SearchEngineIndexingService $indexing): Response
    {
        abort_unless($indexing->indexNowKeyIsValid($key), 404);

        return response($key, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
