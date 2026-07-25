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
            <a href="{{ route('blog.show', $category->slug) }}">{{ $category->name }} <span>{{ $category->articles_count }}</span></a>
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
            <article class="blog-card" style="transition: transform 0.3s ease; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; display: flex; flex-direction: column; background: white;">
                <div class="blog-card-top" style="margin-bottom: 16px;">
                    <span style="color: #2563eb; background: #eff6ff; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase;">{{ str_replace('_', ' ', $article->type) }}</span>
                    <time style="color: #94a3b8; font-size: 12px; font-weight: 600;">{{ $article->published_at?->translatedFormat('d M Y') }}</time>
                </div>
                <h3 style="font: 800 20px/1.4 'Manrope', sans-serif; margin: 0 0 12px;"><a href="{{ $article->public_url }}" style="color: #0f172a; text-decoration: none;">{{ $article->title }}</a></h3>
                <p style="color: #475569; font-size: 15px; line-height: 1.6; margin-bottom: 24px; flex-grow: 1;">{{ $article->meta_description ?: $article->excerpt }}</p>
                <footer style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                    <span style="color: #64748b; font-size: 12px; font-weight: 600;">{{ $article->project->name }}</span>
                    <a href="{{ $article->public_url }}" style="color: #2563eb; font-weight: 700; font-size: 13px; text-decoration: none;">Lire le dossier →</a>
                </footer>
            </article>
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
