@extends('layouts.blog')

@section('title', 'Boîte à outils gratuite pour Indépendants')
@section('description', 'Calculateurs, simulateurs et générateurs 100% gratuits pour faciliter la vie des freelances et auto-entrepreneurs.')

@section('content')
<main class="home-container" style="padding-bottom: 80px;">
    
    <header class="free-tools-header">
        <h1 style="font-size: clamp(32px, 5vw, 56px); font-weight: 900; color: var(--home-primary); margin-bottom: 24px; letter-spacing: -0.02em; line-height: 1.1;">
            La boîte à outils <span style="color: var(--home-accent);">100% gratuite</span>
        </h1>
        <p style="font-size: 20px; color: var(--home-muted); line-height: 1.6; margin-bottom: 32px;">
            Des outils conçus spécifiquement pour les indépendants français. Sans inscription. Résultats instantanés.
        </p>
        <ul style="list-style:none; padding:0; margin:0; display:flex; justify-content:center; gap:24px; color:var(--home-primary); font-size:14px; flex-wrap:wrap; font-weight:700;">
            <li style="display:flex; align-items:center; gap:6px;"><svg style="color:#10b981; width:18px; height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> 100% gratuits</li>
            <li style="display:flex; align-items:center; gap:6px;"><svg style="color:#10b981; width:18px; height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Sans inscription</li>
            <li style="display:flex; align-items:center; gap:6px;"><svg style="color:#10b981; width:18px; height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Anonymes</li>
            <li style="display:flex; align-items:center; gap:6px;"><svg style="color:#10b981; width:18px; height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Taux 2026 inclus</li>
        </ul>
    </header>

    <div class="free-tools-grid">
        
        @foreach($tools as $index => $tool)
        @php
            $icons = ['🧮', '💰', '📉', '⚖️', '⏱️', '🏢'];
            $icon = $icons[$index % count($icons)];
        @endphp
        <a href="{{ route('free-tools.show', $tool['slug']) }}" class="free-tool-card">
            <div class="free-tool-card-icon">{{ $icon }}</div>
            <h3 class="free-tool-card-title">{{ $tool['title'] }}</h3>
            <p class="free-tool-card-desc">{{ $tool['description'] }}</p>
            <div class="free-tool-card-cta">
                {{ $tool['cta'] }}
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </div>
        </a>
        @endforeach

    </div>

</main>
@endsection
