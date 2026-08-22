@extends('layouts.blog')

@section('title', 'Guides freelance et comparatifs logiciels')
@section('description', 'Guides, avis et comparatifs vérifiés pour gérer une activité freelance en France.')

@section('content')
<section class="pro-hero" style="padding: 100px 20px; background: linear-gradient(135deg, #020617, #0f172a, #1e1b4b); color: white;">
    <div class="pro-hero-inner">
        <span class="pro-eyebrow">Bibliothèque BusinessKit</span>
        <h1 style="color: white; background: none; -webkit-text-fill-color: white;">Des dossiers utiles, pas du contenu de remplissage.</h1>
        <p>Chaque guide part d'une intention claire : comprendre une obligation, choisir un outil, comparer un prix ou avancer sur une démarche précise.</p>
    </div>
</section>

<section class="blog-listing">
    <div class="category-chips" aria-label="Categories">
        @foreach($categories as $category)
            @if($category->articles_count > 0)
                <a href="{{ route('blog.show', $category->slug) }}">{{ $category->name }} <span>{{ $category->articles_count }}</span></a>
            @endif
        @endforeach
    </div>

    <div class="blog-section-head" style="text-align: center; max-width: 650px; margin: 40px auto 50px;">
        <div>
            <span class="pro-eyebrow" style="background: #f1f5f9; color: #475569; border: none;">Dernières analyses</span>
            <h2 style="font: 800 32px/1.2 'Manrope', sans-serif; color: #0f172a; margin-top: 12px;">{{ $cluster ? 'Guides du cluster '.str_replace('_', ' ', $cluster) : 'Guides, avis et comparatifs' }}</h2>
        </div>
    </div>

    <div class="blog-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 32px; max-width: 1200px; margin: 0 auto; padding: 0 20px;">
        @forelse($articles as $article)
            <a href="{{ $article->public_url }}" class="hp-article-card" style="padding: 0; display: flex; flex-direction: column; overflow: hidden; border-radius: 16px; background: white; border: 1px solid #e2e8f0; text-decoration: none;">
                <div style="width: 100%; aspect-ratio: 1200/630; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; overflow: hidden;">
                    <img src="{{ route('og-image', $article->id) }}" alt="{{ $article->title }}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 16px 16px 0 0;" loading="lazy">
                </div>
                <div style="padding: 24px; display: flex; flex-direction: column; flex-grow: 1;">
                    <div class="hp-card-header" style="display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px;">
                        <span class="hp-card-category" style="font-weight: 600; font-size: 13px;">{{ $article->categories->first()->name ?? str_replace('_', ' ', $article->type) }}</span>
                        <span class="hp-card-date" style="color: #64748b; font-size: 12px;">{{ $article->published_at?->translatedFormat('d M Y') ?: $article->updated_at->format('M Y') }}</span>
                    </div>
                    <div class="hp-card-title">{{ $article->title }}</div>
                    <div class="hp-card-desc">{{ Str::limit($article->meta_description ?: ($article->excerpt ?? 'Découvrez notre analyse détaillée et nos conseils d\'experts.'), 110) }}</div>
                    <div class="hp-card-footer" style="margin-top: auto;">
                        Lire l'article <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </div>
                </div>
            </a>
        @empty
            <div class="blog-empty" style="grid-column: 1/-1; padding: 60px; text-align: center; background: #f8fafc; border-radius: 16px; border: 1px dashed #cbd5e1;">
                <span style="font-size: 40px; display: block; margin-bottom: 16px;">+</span>
                <h2 style="font: 800 24px 'Manrope', sans-serif; color: #0f172a; margin-bottom: 12px;">Les premiers dossiers arrivent</h2>
                <p style="color: #475569; font-size: 16px;">Les contenus validés depuis le Studio apparaîtront ici.</p>
            </div>
        @endforelse
    </div>

    <div class="pagination blog-pagination">{{ $articles->links() }}</div>
</section>
@endsection
