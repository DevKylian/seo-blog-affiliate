@extends('layouts.blog')

@section('title', 'Logiciels analysés')
@section('description', 'Fiches logiciels, tarifs vérifiés, fonctionnalités et limites documentées.')

@section('content')
<section class="blog-hero tools-hero editorial-hero">
    <div>
        <span class="eyebrow">Base logiciels</span>
        <h1>Les outils suivis, avec leurs prix et limites.</h1>
        <p>Une fiche par logiciel, alimentée par les pages officielles collectées : tarifs, fonctionnalités, signaux de confiance et sources.</p>
    </div>
</section>

<section class="blog-listing">
    <div class="tool-public-grid">
        @forelse($tools as $tool)
            <article>
                <span class="project-logo">{{ strtoupper(substr($tool->name, 0, 1)) }}</span>
                <div>
                    <h2><a href="{{ route('tools.show', $tool->slug) }}">{{ $tool->name }}</a></h2>
                    <p>{{ $tool->description ?: $tool->positioning ?: 'Fiche en cours de documentation.' }}</p>
                    <footer>
                        <span>{{ $tool->plans_count }} tarifs suivis</span>
                        <span>{{ $tool->articles_count }} contenus reliés</span>
                    </footer>
                </div>
                <a href="{{ route('tools.show', $tool->slug) }}">Ouvrir la fiche</a>
            </article>
        @empty
            <div class="blog-empty">
                <span>+</span>
                <h2>Aucun logiciel publié</h2>
            </div>
        @endforelse
    </div>
</section>
@endsection
