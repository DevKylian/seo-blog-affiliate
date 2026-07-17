@extends('layouts.blog')

@section('title', 'Guides freelance et comparatifs logiciels')
@section('description', 'Guides, avis et comparatifs vérifiés pour gérer une activité freelance en France.')

@section('content')
<section class="blog-hero editorial-hero">
    <div>
        <span class="eyebrow">Bibliothèque FreelanceOS</span>
        <h1>Des dossiers utiles, pas du contenu de remplissage.</h1>
        <p>Chaque guide part d'une intention claire : comprendre une obligation, choisir un outil, comparer un prix ou avancer sur une démarche précise.</p>
    </div>
</section>

<section class="blog-listing">
    <div class="category-chips" aria-label="Categories">
        @foreach($categories as $category)
            <a href="{{ route('blog.show', $category->slug) }}">{{ $category->name }} <span>{{ $category->articles_count }}</span></a>
        @endforeach
    </div>

    <div class="blog-section-head">
        <div>
            <span class="eyebrow dark">Dernières analyses</span>
            <h2>{{ $cluster ? 'Guides du cluster '.str_replace('_', ' ', $cluster) : 'Guides, avis et comparatifs' }}</h2>
        </div>
    </div>

    <div class="blog-grid">
        @forelse($articles as $article)
            <article class="blog-card">
                <div class="blog-card-top">
                    <span>{{ str_replace('_', ' ', $article->type) }}</span>
                    <time>{{ $article->published_at?->translatedFormat('d M Y') }}</time>
                </div>
                <h3><a href="{{ $article->public_url }}">{{ $article->title }}</a></h3>
                <p>{{ $article->meta_description ?: $article->excerpt }}</p>
                <footer>
                    <span>{{ $article->project->name }}</span>
                    <a href="{{ $article->public_url }}">Lire le dossier</a>
                </footer>
            </article>
        @empty
            <div class="blog-empty">
                <span>+</span>
                <h2>Les premiers dossiers arrivent</h2>
                <p>Les contenus validés depuis le Studio apparaîtront ici.</p>
            </div>
        @endforelse
    </div>

    <div class="pagination blog-pagination">{{ $articles->links() }}</div>
</section>
@endsection
