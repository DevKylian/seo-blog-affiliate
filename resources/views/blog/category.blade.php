@extends('layouts.blog')

@section('title', $category->name)
@section('description', $category->description ?: 'Articles et comparatifs de la catégorie '.$category->name)

@section('content')
<section class="blog-hero category-hero editorial-hero">
    <div>
        <span class="eyebrow">Categorie</span>
        <h1>{{ $category->name }}</h1>
        <p>{{ $category->description ?: 'Analyses vérifiées, guides pratiques et comparatifs reliés à cette thématique.' }}</p>
    </div>
</section>

<section class="blog-listing">
    <div class="blog-grid">
        @forelse($articles as $article)
            <article class="blog-card">
                <div class="blog-card-top">
                    <span>{{ str_replace('_', ' ', $article->type) }}</span>
                    <time>{{ $article->published_at?->translatedFormat('d M Y') }}</time>
                </div>
                <h3><a href="{{ $article->public_url }}">{{ $article->title }}</a></h3>
                <p>{{ $article->excerpt ?: $article->meta_description }}</p>
                <footer>
                    <span>{{ $article->project->name }}</span>
                    <a href="{{ $article->public_url }}">Lire le dossier</a>
                </footer>
            </article>
        @empty
            <div class="blog-empty">
                <span>+</span>
                <h2>Aucun dossier dans cette catégorie</h2>
            </div>
        @endforelse
    </div>

    <div class="pagination blog-pagination">{{ $articles->links() }}</div>
</section>
@endsection
