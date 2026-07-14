@extends('layouts.blog')
@section('title','Outils logiciels analysés')
@section('description','Découvrez nos fiches logiciels, leurs tarifs vérifiés, fonctionnalités et limites documentées.')
@section('content')
<section class="blog-hero tools-hero"><span class="eyebrow">Base de connaissances</span><h1>Les outils, sans le discours commercial.</h1><p>Prix, fonctionnalités et limites conservés avec leur source et leur date de vérification.</p></section>
<section class="blog-listing"><div class="tool-public-grid">@forelse($tools as $tool)<article><span class="project-logo">{{ strtoupper(substr($tool->name,0,1)) }}</span><div><h2><a href="{{ route('tools.show',$tool->slug) }}">{{ $tool->name }}</a></h2><p>{{ $tool->description ?: $tool->positioning ?: 'Fiche en cours de documentation.' }}</p><footer><span>{{ $tool->plans_count }} tarifs suivis</span><span>{{ $tool->articles_count }} contenus</span></footer></div><a href="{{ route('tools.show',$tool->slug) }}">Voir la fiche →</a></article>@empty<div class="blog-empty"><span>▦</span><h2>Aucun outil publié</h2></div>@endforelse</div></section>
@endsection
