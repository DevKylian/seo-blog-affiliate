@extends('layouts.blog')

@section('title', $article->meta_title ?: $article->title)
@section('description', $article->meta_description ?: $article->excerpt ?: 'Analyse logicielle vérifiée par BusinessKit.')
@section('canonical', $article->canonical_url ?: $article->public_url)
@section('og_image', route('og-image', $article->id))

@push('head')
<script type="application/ld+json">{!! json_encode(app(\App\Services\StructuredDataService::class)->article($article), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
@php
    $articleBlocks = collect($article->content_blocks ?: [['type' => 'markdown', 'content' => $article->body]]);

        $articleBlocks = $articleBlocks->reject(function ($block) {
            return ($block['type'] ?? '') === 'affiliate_cta' && ($block['position'] ?? '') === 'final';
        })->values();



        $markdownIndex = $articleBlocks->search(fn($b) => ($b['type'] ?? '') === 'markdown');
        if ($markdownIndex !== false) {
            $mdContent = $articleBlocks[$markdownIndex]['content'] ?? '';
            $parts = preg_split('/(?=^##\s+)/m', $mdContent);
            if (count($parts) > 2) {
                // If there are at least 3 parts (intro + 2 H2s), put it in the middle
                $middleIndex = (int) ceil(count($parts) / 2);
                $part1 = implode('', array_slice($parts, 0, $middleIndex));
                $part2 = implode('', array_slice($parts, $middleIndex));
                
                // Ensure there is some trailing whitespace so the CTA doesn't stick to the previous text
                $part1 = rtrim($part1) . "\n\n";

                $articleBlocks->splice($markdownIndex, 1, [
                    ['type' => 'markdown', 'content' => $part1],
                    ['type' => 'affiliate_cta', 'position' => 'middle'],
                    ['type' => 'markdown', 'content' => $part2],
                ]);
            }
        }

    $footerBlockTypes = ['affiliate_disclosure', 'last_verified'];
    $mainBlocks = $articleBlocks
        ->reject(fn ($block) => in_array($block['type'] ?? '', $footerBlockTypes, true))
        ->values()
        ->all();
    $footerBlocks = $articleBlocks
        ->filter(fn ($block) => in_array($block['type'] ?? '', $footerBlockTypes, true))
        ->values()
        ->all();
@endphp

<div class="reading-progress-container">
    <div class="reading-progress-bar" id="readingProgressBar"></div>
</div>

<div class="article-hero">
    <div class="article-hero-inner">
        <div class="article-breadcrumbs">
            <a href="{{ route('blog.index') }}">Tous les guides</a>
            <span>›</span>
            <span>{{ str_replace('_', ' ', $article->type) }}</span>
        </div>
        <h1>{{ $article->title }}</h1>
        <p class="article-lead">{{ $article->meta_description ?: $article->excerpt }}</p>
        <div class="article-meta-strip">
            <div class="meta-item">
                <span class="meta-icon">⏱</span>
                <div>
                    <strong>Temps de lecture</strong>
                    <span>{{ $article->reading_time }} minutes</span>
                </div>
            </div>
            <div class="meta-item">
                <span class="meta-icon">📅</span>
                <div>
                    <strong>Mise à jour</strong>
                    <span>{{ $article->verified_at?->translatedFormat('d F Y') ?? $article->updated_at->translatedFormat('d F Y') }}</span>
                </div>
            </div>
            <div class="meta-item">
                <span class="meta-icon">👨‍💻</span>
                <div>
                    <strong>Auteur</strong>
                    <span>Kylian Dev.</span>
                </div>
            </div>
            
        </div>
    </div>
</div>

<article class="public-article">

    <div class="article-layout">
        <div class="article-main">

            @include('blog.partials.quiz_wizard', ['isArticle' => true])
            @include('blog.partials.ai_summary_buttons')

            @include('blog.partials.blocks', ['blocks' => $mainBlocks])

            @if($article->internalLinks->count() > 0)
                <div class="article-internal-links" style="margin-top: 60px; padding: 32px; background: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <h3 style="font: 800 24px/1.2 'Manrope', sans-serif; color: #0f172a; margin: 0 0 20px;">Dans ce guide</h3>
                    <ul style="list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                        @foreach($article->internalLinks as $link)
                            @if($link->target && !str_contains($article->body, $link->target->public_url) && !str_contains($article->body, $link->target->public_path))
                                <li><a href="{{ $link->target->public_url }}" style="color: #2563eb; text-decoration: none; font-weight: 600;">→ {{ $link->anchor_text ?: $link->target->title }}</a></li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>
    </div>

    
    <!-- Encart Auteur (SEO EEAT) -->
    <section class="hp-author-block" style="margin-top: 60px; margin-bottom: 20px;">
        <div class="hp-author-avatar" style="font-size: 48px; padding: 0; overflow: hidden; background: none; border: none; box-shadow: none;"><img src="/images/author-kylian.png" alt="Kylian" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; box-shadow: 0 4px 6px rgba(0,0,0,0.1);" /></div>
        <div class="hp-author-info">
            <div style="font-size:12px; font-weight:700; color:var(--home-muted); margin-bottom:4px; text-transform:uppercase; letter-spacing:1px;">L'Expert derrière BusinessKit</div>
            <h3>Kylian Dev.</h3>
            <p style="margin: 8px 0; line-height: 1.6; color: #475569;">Créateur de BusinessKit, développeur Laravel et indépendant. Je teste, compare et analyse les logiciels destinés aux freelances et petites entreprises afin de proposer des recommandations transparentes et régulièrement mises à jour.</p>
            <div class="hp-author-tags">
                <span class="hp-author-tag">Basé en France</span>
                <span class="hp-author-tag">Spécialiste outils SaaS</span>
                <a href="{{ route('author.show') }}" style="font-size:13px; font-weight:700; color:var(--home-accent); text-decoration:none; margin-left:8px; align-self:center;">Voir la méthodologie complète ↗</a>
            </div>
        </div>
    </section>

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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const progressBar = document.getElementById('readingProgressBar');
        const article = document.querySelector('.public-article');
        
        if (progressBar && article) {
            window.addEventListener('scroll', () => {
                const articleRect = article.getBoundingClientRect();
                const totalScroll = articleRect.height - window.innerHeight;
                let scrollProgress = (Math.abs(articleRect.top) / totalScroll) * 100;
                
                if (articleRect.top > 0) scrollProgress = 0;
                if (scrollProgress > 100) scrollProgress = 100;
                
                progressBar.style.width = scrollProgress + '%';
            }, { passive: true });
        }
    });
</script>
<script type="module">
    import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs';
    mermaid.initialize({ startOnLoad: true, theme: 'base', themeVariables: { primaryColor: '#f1f5f9', primaryTextColor: '#0f172a', primaryBorderColor: '#cbd5e1', lineColor: '#3b82f6', fontFamily: 'Manrope, sans-serif' } });
</script>
@endpush
@endsection
