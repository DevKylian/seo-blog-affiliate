@extends('layouts.blog')

@section('title', $tool['title'] . ' - Outil gratuit BusinessKit')
@section('description', $tool['description'])

@section('content')
<main class="home-container" style="padding-bottom: 80px; max-width: 900px; margin: 0 auto;">
    
    <div class="no-print" style="margin-bottom: 32px; padding-top: 40px;">
        <a href="{{ route('free-tools.index') }}" class="free-tool-back-link">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Retour aux outils
        </a>
    </div>

    <header class="no-print" style="text-align:center; margin-bottom: 48px;">
        <h1 style="font-size: clamp(28px, 4vw, 48px); font-weight: 900; color: var(--home-primary); margin-bottom: 16px; letter-spacing: -0.02em; line-height: 1.1;">
            {{ $tool['title'] }}
        </h1>
        <p style="font-size: 18px; color: var(--home-muted); line-height: 1.6; max-width: 600px; margin: 0 auto; font-weight: 500;">
            {{ $tool['description'] }}
        </p>
    </header>

    <div class="tool-wrapper" style="background: white; border-radius: 24px; padding: 48px; box-shadow: 0 20px 50px -12px rgba(15, 23, 42, 0.06); border: 1px solid var(--home-border);">
        @include('tools.components.' . $tool['type'])
    </div>

    <aside class="tool-conversion no-print" style="margin-top: 60px; padding: 48px 32px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 24px; display: flex; flex-direction: column; gap: 24px; align-items: center; text-align: center; margin-bottom: 60px;">
        <div>
            <strong style="display: block; font: 900 28px 'Manrope', sans-serif; color: #0f172a; margin-bottom: 16px; letter-spacing:-0.02em; line-height: 1.3;">{{ $tool['conversion_title'] ?? 'Gagnez (encore) plus de temps' }}</strong>
            <p style="font-size: 16px; color: #475569; margin: 0 auto; line-height: 1.6; max-width: 600px; font-weight: 500;">{{ $tool['conversion_text'] ?? 'Découvrez comment les meilleurs logiciels pour indépendants automatisent vos devis, factures, et calculs de cotisations en un clic.' }}</p>
        </div>
        <a href="{{ $tool['conversion_link'] ?? route('home') . '#wizard' }}" style="display: inline-flex; align-items: center; gap: 8px; background: {{ $tool['conversion_color'] ?? '#2563eb' }}; color: white; padding: 16px 32px; border-radius: 12px; font-weight: 800; font-size: 16px; text-decoration: none; box-shadow: 0 4px 12px color-mix(in srgb, {{ $tool['conversion_color'] ?? '#2563eb' }} 40%, transparent); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 16px color-mix(in srgb, {{ $tool['conversion_color'] ?? '#2563eb' }} 60%, transparent)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 12px color-mix(in srgb, {{ $tool['conversion_color'] ?? '#2563eb' }} 40%, transparent)';">
            {{ $tool['conversion_cta'] ?? 'Trouver mon logiciel idéal' }}
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
        </a>
    </aside>

</main>
@endsection
