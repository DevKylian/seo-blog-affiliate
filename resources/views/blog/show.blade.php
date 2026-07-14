@extends('layouts.blog')
@section('title',$article->meta_title ?: $article->title)
@section('description',$article->meta_description ?: $article->excerpt ?: 'Analyse logicielle vérifiée par BlogSEO.')
@section('canonical',$article->canonical_url ?: $article->public_url)
@push('head')
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'Article','headline'=>$article->title,'datePublished'=>$article->published_at?->toAtomString(),'dateModified'=>$article->updated_at->toAtomString(),'author'=>['@type'=>'Organization','name'=>config('app.name')],'mainEntityOfPage'=>$article->public_url], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
@section('content')
<article class="public-article"><header><a href="{{ route('blog.index') }}">← Tous les contenus</a><span class="article-category">{{ str_replace('_',' ',$article->type) }}</span><h1>{{ $article->title }}</h1><p>{{ $article->meta_description }}</p><div><span>Vérifié le {{ $article->verified_at?->translatedFormat('d F Y') }}</span><span>{{ $article->sources->count() }} sources documentées</span><span>Par BlogSEO</span></div></header>@include('blog.partials.blocks',['blocks'=>$article->content_blocks ?: [['type'=>'markdown','content'=>$article->body]]])<footer class="article-footer"><strong>Méthodologie de vérification</strong><p>Les informations factuelles reposent sur les sources officielles collectées à la date indiquée. Les prix et fonctionnalités peuvent évoluer : vérifiez toujours les conditions sur le site de l’éditeur.</p></footer></article>
@endsection
