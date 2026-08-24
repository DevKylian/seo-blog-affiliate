@extends('layouts.blog')

@section('title', $tool['title'] . ' - Outil gratuit BusinessKit')
@section('description', $tool['description'])

@section('content')
<main class="home-container" style="padding-bottom: 80px; max-width: 900px; margin: 0 auto;">
    
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebApplication",
      "name": "{{ addslashes($tool['title']) }}",
      "description": "{{ addslashes($tool['description']) }}",
      "applicationCategory": "BusinessApplication",
      "operatingSystem": "All",
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "EUR"
      }
    }
    </script>

    <div class="no-print" style="margin-bottom: 32px; padding-top: 40px;">
        <a href="{{ route('free-tools.index') }}" class="free-tool-back-link">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Retour aux outils
        </a>
    </div>

    <header class="no-print" style="text-align:center; margin-bottom: 32px;">
        <h1 style="font-size: clamp(28px, 4vw, 48px); font-weight: 900; color: var(--home-primary); margin-bottom: 16px; letter-spacing: -0.02em; line-height: 1.1;">
            {{ $tool['title'] }}
        </h1>
        <p style="font-size: 18px; color: var(--home-muted); line-height: 1.6; max-width: 600px; margin: 0 auto; font-weight: 500;">
            {{ $tool['description'] }}
        </p>
    </header>

    <div class="no-print" style="background: {{ $tool['conversion_color'] ?? '#2563eb' }}; border-radius: 16px; padding: 24px 32px; margin-bottom: 48px; display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap; box-shadow: 0 10px 30px -10px color-mix(in srgb, {{ $tool['conversion_color'] ?? '#2563eb' }} 60%, transparent);">
        <div style="display: flex; gap: 20px; align-items: center; flex: 1 1 300px;">
            <div style="display: grid; place-items: center; width: 56px; height: 56px; border-radius: 14px; background: rgba(255, 255, 255, 0.15); color: white; flex-shrink: 0;">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </div>
            <div>
                <strong style="display: block; font-size: 19px; color: white; margin-bottom: 4px; font-weight: 800;">Mieux que le calcul manuel...</strong>
                <p style="font-size: 15px; color: rgba(255, 255, 255, 0.9); margin: 0; line-height: 1.5;">{{ $tool['conversion_text'] ?? 'Automatisez ces calculs de manière 100% gratuite.' }}</p>
            </div>
        </div>
        <a href="{{ $tool['conversion_link'] ?? route('home') . '#wizard' }}" style="background: white; color: {{ $tool['conversion_color'] ?? '#2563eb' }}; padding: 14px 28px; border-radius: 10px; font-weight: 800; font-size: 15px; text-decoration: none; white-space: nowrap; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            {{ $tool['conversion_cta'] ?? 'Découvrir la solution' }}
        </a>
    </div>

    <div class="tool-wrapper" style="background: white; border-radius: 24px; padding: 48px; box-shadow: 0 20px 50px -12px rgba(15, 23, 42, 0.06); border: 1px solid var(--home-border);">
        @include('tools.components.' . $tool['type'])
    </div>

    <aside class="tool-conversion no-print" style="position: relative; overflow: hidden; margin-top: 60px; padding: 48px 40px; background: {{ $tool['conversion_color'] ?? '#2563eb' }}; border-radius: 24px; display: flex; flex-direction: column; gap: 24px; align-items: center; text-align: center; margin-bottom: 60px; box-shadow: 0 20px 40px -15px color-mix(in srgb, {{ $tool['conversion_color'] ?? '#2563eb' }} 60%, transparent);">
        <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: white; filter: blur(100px); opacity: 0.15; border-radius: 50%;"></div>
        <div style="position: absolute; bottom: -50px; left: -50px; width: 200px; height: 200px; background: white; filter: blur(100px); opacity: 0.15; border-radius: 50%;"></div>
        
        <div style="position: relative; z-index: 1;">
            <strong style="display: block; font: 900 32px 'Manrope', sans-serif; color: white; margin-bottom: 16px; letter-spacing:-0.02em; line-height: 1.2;">{{ $tool['conversion_title'] ?? 'Gagnez (encore) plus de temps' }}</strong>
            <p style="font-size: 17px; color: rgba(255, 255, 255, 0.9); margin: 0 auto; line-height: 1.6; max-width: 600px; font-weight: 500;">{{ $tool['conversion_text'] ?? 'Découvrez comment les meilleurs logiciels pour indépendants automatisent vos devis, factures, et calculs de cotisations en un clic.' }}</p>
        </div>
        <a href="{{ $tool['conversion_link'] ?? route('home') . '#wizard' }}" style="position: relative; z-index: 1; display: inline-flex; align-items: center; gap: 10px; background: white; color: {{ $tool['conversion_color'] ?? '#2563eb' }}; padding: 18px 36px; border-radius: 12px; font-weight: 800; font-size: 16px; text-decoration: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 15px 35px rgba(0, 0, 0, 0.15)';" onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
            {{ $tool['conversion_cta'] ?? 'Trouver mon logiciel idéal' }}
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
        </a>
    </aside>

    @if(isset($tool['faq']) && count($tool['faq']) > 0)
    <section class="no-print" style="margin-top: 80px; max-width: 800px; margin-left: auto; margin-right: auto; margin-bottom: 80px;">
        <h2 style="font-size: 32px; font-weight: 900; color: #0f172a; margin-bottom: 32px; text-align: center;">Questions fréquentes</h2>
        <div style="display: flex; flex-direction: column; gap: 16px;">
            @foreach($tool['faq'] as $faq)
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px;">
                <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">{{ $faq['question'] }}</h3>
                <p style="font-size: 16px; color: #475569; margin: 0; line-height: 1.6;">{{ $faq['answer'] }}</p>
            </div>
            @endforeach
        </div>
    </section>

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        @foreach($tool['faq'] as $faq)
        {
          "@type": "Question",
          "name": "{{ addslashes($faq['question']) }}",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "{{ addslashes($faq['answer']) }}"
          }
        }{{ !$loop->last ? ',' : '' }}
        @endforeach
      ]
    }
    </script>
    @endif

    @if(isset($latestArticles) && $latestArticles->count() > 0)
    <section class="no-print" style="margin-top: 60px;">
        <h2 style="font-size: 28px; font-weight: 900; color: #0f172a; margin-bottom: 32px;">Pour aller plus loin</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
            @foreach($latestArticles as $article)
                <a href="{{ $article->public_url }}" class="hp-article-card" style="padding: 0; display: flex; flex-direction: column; overflow: hidden; border-radius: 16px; background: white; border: 1px solid #e2e8f0; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 10px 25px -5px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <div style="width: 100%; aspect-ratio: 1200/630; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; overflow: hidden;">
                        <img src="{{ route('og-image', $article->id) }}" alt="{{ $article->title }}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 16px 16px 0 0;" loading="lazy">
                    </div>
                    <div style="padding: 24px; display: flex; flex-direction: column; flex-grow: 1;">
                        <div class="hp-card-header" style="margin-bottom: 12px;">
                            <span class="hp-card-date" style="color: #64748b; font-size: 12px;">{{ $article->published_at?->translatedFormat('d M Y') ?: $article->updated_at->format('M Y') }}</span>
                        </div>
                        <div class="hp-card-title">{{ $article->title }}</div>
                        <div class="hp-card-desc">{{ \Illuminate\Support\Str::limit($article->meta_description ?: ($article->excerpt ?? 'Découvrez notre analyse détaillée et nos conseils d\'experts.'), 110) }}</div>
                        <div class="hp-card-footer" style="margin-top: auto;">
                            Lire l'article <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
    @endif

</main>
@endsection
