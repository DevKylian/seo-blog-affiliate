<?php

namespace App\Http\Controllers;

use App\Models\AffiliateBlock;
use App\Models\AffiliateClick;
use App\Models\Article;
use App\Models\SeoProject;
use App\Services\AffiliateBlockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AffiliateRedirectController extends Controller
{
    public function __invoke(Request $request, SeoProject $project, AffiliateBlockService $blocks): RedirectResponse
    {
        $article = $request->integer('article')
            ? Article::query()->where('seo_project_id', $project->id)->find($request->integer('article'))
            : null;
        $block = $request->integer('block')
            ? AffiliateBlock::query()->find($request->integer('block'))
            : null;

        $target = $blocks->targetUrl($project, $article, $block);
        abort_unless($target, 404);



        AffiliateClick::query()->create([
            'seo_project_id' => $project->id,
            'article_id' => $article?->id,
            'keyword_id' => $article?->keyword_id,
            'affiliate_block_id' => $block?->id,
            'page_url' => $request->headers->get('referer') ?: ($article?->public_url),
            'target_url' => $target,
            'affiliate_cluster' => $article?->keyword?->affiliate_cluster ?: $block?->affiliate_cluster,
            'intent_type' => $article?->intent_type ?: $article?->keyword?->intent_type ?: $block?->intent_type,
            'position' => $request->query('position', $block?->position),
            'device' => $this->device((string) $request->userAgent()),
            'referrer' => $request->headers->get('referer'),
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip().config('app.key')) : null,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            'clicked_at' => now(),
        ]);

        return redirect()->away($target);
    }

    private function device(string $userAgent): string
    {
        return preg_match('/mobile|android|iphone|ipad/iu', $userAgent) === 1 ? 'mobile' : 'desktop';
    }
}
