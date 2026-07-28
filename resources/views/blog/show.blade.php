@extends('layouts.blog')

@section('title', $article->meta_title ?: $article->title)
@section('description', $article->meta_description ?: $article->excerpt ?: 'Analyse logicielle vérifiée par BusinessKit.')
@section('canonical', $article->canonical_url ?: $article->public_url)
@section('og_image', route('og-image', $article->id))

@push('head')
<script type="application/ld+json">{!! json_encode(app(\App\Services\StructuredDataService::class)->article($article), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<div class="article-hero" style="margin-bottom: 2rem;">
    <img src="{{ route('og-image', $article->id) }}" alt="{{ $article->title }}" style="width: 100%; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: block;" loading="eager">
</div>
@php
    $articleBlocks = collect($article->content_blocks ?: [['type' => 'markdown', 'content' => $article->body]]);

    if ($article->project) {
        $articleBlocks = $articleBlocks->map(function ($block) {
            if (($block['type'] ?? '') === 'affiliate_cta' && ($block['position'] ?? '') === 'final') {
                $block['position'] = 'after_intro';
            }
            return $block;
        });

        if (! $articleBlocks->contains('type', 'affiliate_cta')) {
            $markdownIndex = $articleBlocks->search(fn($b) => ($b['type'] ?? '') === 'markdown');
            if ($markdownIndex !== false) {
                $articleBlocks->splice($markdownIndex, 0, [['type' => 'affiliate_cta', 'position' => 'after_intro']]);
            }
        }
        if ($articleBlocks->filter(fn($b) => ($b['type'] ?? '') === 'affiliate_cta')->count() < 2) {
            $pricingIndex = $articleBlocks->search(fn($b) => ($b['type'] ?? '') === 'pricing_table');
            $insertIndex = $pricingIndex !== false ? $pricingIndex + 1 : $articleBlocks->count() - 2;
            if ($insertIndex < 0) $insertIndex = $articleBlocks->count();
            $articleBlocks->splice($insertIndex, 0, [['type' => 'affiliate_cta', 'position' => 'after_intro']]);
        }
    }

    $footerBlockTypes = ['affiliate_disclosure', 'last_verified'];
    $mainBlocks = $articleBlocks
        ->reject(fn ($block) => in_array($block['type'] ?? '', $footerBlockTypes, true))
        ->values()
        ->all();
    $footerBlocks = $articleBlocks
        ->filter(fn ($block) => in_array($block['type'] ?? '', $footerBlockTypes, true))
        ->values()
        ->all();
@endphp

<div class="article-hero">
    <div class="article-hero-inner">
        <div class="article-breadcrumbs">
            <a href="{{ route('blog.index') }}">Tous les guides</a>
            <span>›</span>
            <span>{{ str_replace('_', ' ', $article->type) }}</span>
        </div>
        <h1>{{ $article->title }}</h1>
        <p class="article-lead">{{ $article->meta_description ?: $article->excerpt }}</p>
        <div class="article-meta-strip">
            <div class="meta-item">
                <span class="meta-icon">📅</span>
                <div>
                    <strong>Mise à jour</strong>
                    <span>{{ $article->verified_at?->translatedFormat('d F Y') ?? $article->updated_at->translatedFormat('d F Y') }}</span>
                </div>
            </div>
            <div class="meta-item">
                <span class="meta-icon">📚</span>
                <div>
                    <strong>Sources vérifiées</strong>
                    <span>{{ $article->sources->count() }} références</span>
                </div>
            </div>
            @if(!preg_match('/blog|guide/i', $article->project->name))
            <div class="meta-item">
                <span class="meta-icon">🎯</span>
                <div>
                    <strong>Logiciel analysé</strong>
                    <span>{{ $article->project->name }}</span>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<article class="public-article">

    <div class="article-layout">
        <div class="article-main">
            @include('blog.partials.blocks', ['blocks' => $mainBlocks])

            @if($article->internalLinks->count() > 0)
                <div class="article-internal-links" style="margin-top: 60px; padding: 32px; background: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <h3 style="font: 800 24px/1.2 'Manrope', sans-serif; color: #0f172a; margin: 0 0 20px;">Dans ce guide</h3>
                    <ul style="list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                        @foreach($article->internalLinks as $link)
                            @if($link->target)
                                <li><a href="{{ $link->target->public_url }}" style="color: #2563eb; text-decoration: none; font-weight: 600;">→ {{ $link->target->title }}</a></li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>
    </div>

    <section class="article-end-panel" aria-label="Informations editoriales">
        @if($footerBlocks)
            @include('blog.partials.blocks', ['blocks' => $footerBlocks])
        @endif

        <footer class="article-footer">
            <strong>Méthode de vérification</strong>
            <p>Les informations factuelles reposent sur les sources officielles collectées à la date indiquée. Les prix, limites et fonctionnalités peuvent évoluer : vérifiez toujours les conditions sur le site de l'éditeur avant de vous engager.</p>
        </footer>
    </section>
</article>
@endsection
