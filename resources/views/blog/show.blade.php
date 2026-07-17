@extends('layouts.blog')

@section('title', $article->meta_title ?: $article->title)
@section('description', $article->meta_description ?: $article->excerpt ?: 'Analyse logicielle vérifiée par BlogSEO.')
@section('canonical', $article->canonical_url ?: $article->public_url)

@push('head')
<script type="application/ld+json">{!! json_encode(app(\App\Services\StructuredDataService::class)->article($article), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
@php
    $articleBlocks = $article->content_blocks ?: [['type' => 'markdown', 'content' => $article->body]];
    $footerBlockTypes = ['affiliate_disclosure', 'last_verified'];
    $mainBlocks = collect($articleBlocks)
        ->reject(fn ($block) => in_array($block['type'] ?? '', $footerBlockTypes, true))
        ->values()
        ->all();
    $footerBlocks = collect($articleBlocks)
        ->filter(fn ($block) => in_array($block['type'] ?? '', $footerBlockTypes, true))
        ->values()
        ->all();
@endphp

<article class="public-article">
    <header>
        <a href="{{ route('blog.index') }}">Tous les contenus</a>
        <span class="article-category">{{ str_replace('_', ' ', $article->type) }}</span>
        <h1>{{ $article->title }}</h1>
        <p>{{ $article->meta_description ?: $article->excerpt }}</p>
        <div class="article-meta-strip">
            <span>Vérifié le {{ $article->verified_at?->translatedFormat('d F Y') ?? $article->updated_at->translatedFormat('d F Y') }}</span>
            <span>{{ $article->sources->count() }} sources documentées</span>
            <span>{{ $article->project->name }}</span>
        </div>
    </header>

    @include('blog.partials.blocks', ['blocks' => $mainBlocks])

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
