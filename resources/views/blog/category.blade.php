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
            <a href="{{ $article->public_url }}" class="hp-article-card" style="padding: 0; display: flex; flex-direction: column; overflow: hidden; border-radius: 16px; background: white; border: 1px solid #e2e8f0; text-decoration: none;">
                <div style="width: 100%; aspect-ratio: 1200/630; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; overflow: hidden;">
                    <img src="{{ route('og-image', $article->id) }}" alt="{{ $article->title }}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 16px 16px 0 0;" loading="lazy">
                </div>
                <div style="padding: 24px; display: flex; flex-direction: column; flex-grow: 1;">
                    <div class="hp-card-header">
                        <span class="hp-card-category">{{ $article->categories->first()->name ?? str_replace('_', ' ', $article->type) }}</span>
                        <span class="hp-card-date">{{ $article->published_at?->translatedFormat('d M Y') ?: $article->updated_at->format('M Y') }}</span>
                    </div>
                    <div class="hp-card-title">{{ $article->title }}</div>
                    <div class="hp-card-desc">{{ Str::limit($article->excerpt ?: ($article->meta_description ?? 'Découvrez notre analyse détaillée et nos conseils d\'experts.'), 110) }}</div>
                    <div class="hp-card-footer" style="margin-top: auto;">
                        Lire l'article <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </div>
                </div>
            </a>
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
