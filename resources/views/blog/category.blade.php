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
            <article class="blog-card" style="transition: transform 0.3s ease; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; background: white; overflow: hidden;">
                <a href="{{ $article->public_url }}" style="display: block; width: 100%; aspect-ratio: 1200/630; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; overflow: hidden;">
                    <img src="{{ route('og-image', $article->id) }}" alt="{{ $article->title }}" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;" loading="lazy">
                </a>
                <div style="padding: 24px; display: flex; flex-direction: column; flex-grow: 1;">
                    <div class="blog-card-top" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <span style="color: #2563eb; background: #eff6ff; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase;">{{ str_replace('_', ' ', $article->type) }}</span>
                        <time style="color: #94a3b8; font-size: 12px; font-weight: 600;">{{ $article->published_at?->translatedFormat('d M Y') }}</time>
                    </div>
                    <h3 style="font: 800 20px/1.4 'Manrope', sans-serif; margin: 0 0 12px;"><a href="{{ $article->public_url }}" style="color: #0f172a; text-decoration: none;">{{ $article->title }}</a></h3>
                    <p style="color: #475569; font-size: 15px; line-height: 1.6; margin-bottom: 24px; flex-grow: 1;">{{ $article->excerpt ?: $article->meta_description }}</p>
                    <footer style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                        <span style="color: #64748b; font-size: 12px; font-weight: 600;">{{ $article->project->name }}</span>
                        <a href="{{ $article->public_url }}" style="color: #2563eb; font-weight: 700; font-size: 13px; text-decoration: none;">Lire le dossier →</a>
                    </footer>
                </div>
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
