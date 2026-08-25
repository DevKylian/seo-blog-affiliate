@extends('layouts.blog')

@section('title', 'Avis ' . ucfirst($tool->name) . ' : Ce qu\'il faut savoir avant de choisir')
@section('description', 'Notre test complet de ' . ucfirst($tool->name) . ' : tarifs, avantages, inconvénients et pour qui est fait ce logiciel.')

@section('content')
@php
    $ratings = [
        'indy' => [
            'score' => '9.7',
            'stars' => '★★★★★',
            'simplicity' => '9.8',
            'price' => '9.5',
            'support' => '9.5',
            'automation' => '10',
            'reviews_count' => '124'
        ],
        'dougs' => [
            'score' => '9.2',
            'stars' => '★★★★½',
            'simplicity' => '9.0',
            'price' => '8.5',
            'support' => '9.5',
            'automation' => '9.0',
            'reviews_count' => '87'
        ],
        'pennylane' => [
            'score' => '9.4',
            'stars' => '★★★★½',
            'simplicity' => '9.2',
            'price' => '9.0',
            'support' => '9.0',
            'automation' => '9.5',
            'reviews_count' => '94'
        ],
        'tiime' => [
            'score' => '9.3',
            'stars' => '★★★★½',
            'simplicity' => '9.5',
            'price' => '9.2',
            'support' => '8.5',
            'automation' => '9.0',
            'reviews_count' => '76'
        ],
        'abby' => [
            'score' => '8.8',
            'stars' => '★★★★☆',
            'simplicity' => '9.0',
            'price' => '9.5',
            'support' => '8.0',
            'automation' => '8.0',
            'reviews_count' => '42'
        ],
        'shine' => [
            'score' => '9.3',
            'stars' => '⭐⭐⭐⭐⭐',
            'simplicity' => '9.5',
            'price' => '9.0',
            'support' => '9.0',
            'automation' => '8.5',
            'reviews_count' => '115'
        ]
    ];
    $slug = $tool->slug;
    $rating = $ratings[$slug] ?? $ratings['indy'];

    function getBarColor($score) {
        return floatval($score) > 8 ? 'background: #10b981;' : '';
    }
@endphp

<main class="tool-container">
    
    <!-- Header (Titre, Logo, Note, CTA principal) -->
    <header class="review-header">
        <div>
            <div class="review-title-block">
                <div class="review-logo" style="padding: 0; overflow: hidden; background: white; border: 1px solid var(--tool-border);">
                    <img src="{{ $tool->logo_url }}" alt="Logo {{ $tool->name }}" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.outerHTML='<span>{{ strtoupper(substr($tool->name, 0, 1)) }}</span>'">
                </div>
                <div>
                    <h1 class="review-title">Avis {{ ucfirst($tool->name) }}</h1>
                    <div class="review-stars">
                        <span class="review-stars-icons">{{ $rating['stars'] }}</span>
                        <span class="review-stars-score">{{ $rating['score'] }}<span style="font-size:16px; color:var(--tool-muted);">/10</span></span>
                        <a href="#avis" style="color:var(--tool-primary); font-size:14px; font-weight:700;">({{ $rating['reviews_count'] }} avis vérifiés)</a>
                    </div>
                </div>
            </div>
            <p style="font-size: 18px; line-height: 1.6; color: var(--tool-muted); margin-bottom: 24px;">
                {{ $tool->description ?? 'Découvrez notre analyse détaillée de ce logiciel pour gérer votre activité.' }}
            </p>
            @if($tool->screenshot_url)
                <div class="tool-screenshot" style="margin-top: 32px;">
                    <img src="{{ $tool->screenshot_url }}" alt="Interface de {{ $tool->name }}" style="width: 100%; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid var(--tool-border);" loading="eager">
                </div>
            @endif
        </div>
        
        <!-- CTA Card -->
        <div class="review-cta-card" style="align-self: start; height: fit-content;">
            <div style="font-size: 24px; font-weight: 800; color: var(--tool-primary); margin-bottom: 16px;">
                À partir de 0 € <span style="font-size: 14px; color: var(--tool-muted); font-weight: 400;">/mois</span>
            </div>
            <a href="{{ route('affiliate.redirect', $tool->slug) }}" target="_blank" rel="sponsored nofollow" class="review-cta-btn" style="{{ $tool->brand_color ? 'background:'.$tool->brand_color.'; color:'.$tool->brand_text_color.';' : '' }}">Profiter de l'offre gratuite {{ ucfirst($tool->name) }}</a>
            <div class="why-recommend">
                <strong style="display:block; margin-bottom:4px;">💡 Pourquoi nous recommandons {{ ucfirst($tool->name) }} :</strong>
                C'est tout simplement la solution la plus intuitive que nous ayons testée pour les indépendants. Vous gagnerez au minimum 2 heures par mois sur votre administratif.
            </div>
        </div>
    </header>
 
    <!-- Scores détaillés -->
    <div class="review-scores-grid">
        <div>
            <div class="score-item-header">
                <span>Simplicité</span>
                <span class="score-value">{{ $rating['simplicity'] }}/10</span>
            </div>
            <div class="score-bar-bg"><div class="score-bar-fill" style="width: {{ floatval($rating['simplicity']) * 10 }}%; {{ getBarColor($rating['simplicity']) }}"></div></div>
        </div>
        <div>
            <div class="score-item-header">
                <span>Prix</span>
                <span class="score-value">{{ $rating['price'] }}/10</span>
            </div>
            <div class="score-bar-bg"><div class="score-bar-fill" style="width: {{ floatval($rating['price']) * 10 }}%; {{ getBarColor($rating['price']) }}"></div></div>
        </div>
        <div>
            <div class="score-item-header">
                <span>Support Client</span>
                <span class="score-value">{{ $rating['support'] }}/10</span>
            </div>
            <div class="score-bar-bg"><div class="score-bar-fill" style="width: {{ floatval($rating['support']) * 10 }}%; {{ getBarColor($rating['support']) }}"></div></div>
        </div>
        <div>
            <div class="score-item-header">
                <span>Automatisation</span>
                <span class="score-value">{{ $rating['automation'] }}/10</span>
            </div>
            <div class="score-bar-bg"><div class="score-bar-fill" style="width: {{ floatval($rating['automation']) * 10 }}%; {{ getBarColor($rating['automation']) }}"></div></div>
        </div>
    </div>

    </div>

    <!-- Écosystème & Fonctionnalités (Le modèle Plateforme) -->
    <section class="tool-platform-hub" style="background: white; border: 1px solid var(--tool-border); border-radius: 16px; padding: 32px; margin-bottom: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
        
        <!-- Compatibilités & Badges -->
        <div style="margin-bottom: 32px;">
            <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 16px; display:flex; align-items:center; gap:8px;">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                Compatible avec
            </h3>
            <div style="display:flex; flex-wrap:wrap; gap:12px;">
                @php
                    $compatibilities = !empty($tool->features) && is_array($tool->features) ? $tool->features : [
                        'Auto-entrepreneur', 'SASU', 'TVA', 'BNC', 'BIC', 'SCI', 'LMNP', 
                        'Facture électronique', 'Banque', 'API', 'Stripe', 'Déclarations URSSAF', 'Application mobile'
                    ];
                @endphp
                @foreach($compatibilities as $feature)
                    <span style="display:inline-flex; align-items:center; gap:6px; background:#f8fafc; border:1px solid #e2e8f0; padding:8px 14px; border-radius:100px; font-size:13px; font-weight:700; color:var(--tool-text);">
                        <svg width="14" height="14" fill="#10b981" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                        {{ $feature }}
                    </span>
                @endforeach
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:24px; border-top: 1px solid var(--tool-border); padding-top: 24px;">
            
            <!-- Alternatives & Comparatifs -->
            <div>
                <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 12px; color:var(--tool-text);">Comparé avec</h3>
                <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px;">
                    @php
                        $competitors = [
                            'pennylane' => 'Pennylane',
                            'dougs' => 'Dougs',
                            'abby' => 'Abby',
                            'shine' => 'Shine',
                        ];
                        unset($competitors[$tool->slug]);
                    @endphp
                    @foreach($competitors as $compSlug => $compName)
                    <li><a href="{{ route('comparisons.show', $tool->slug . '-vs-' . $compSlug) }}" style="color:var(--tool-primary); text-decoration:none; font-weight:600; font-size:14px; display:inline-flex; align-items:center; gap:6px;">→ {{ ucfirst($tool->name) }} vs {{ $compName }}</a></li>
                    @endforeach
                </ul>
            </div>

            <!-- Articles & Ressources liés -->
            <div>
                <h3 style="font-size: 16px; font-weight: 800; margin-bottom: 12px; color:var(--tool-text);">Ressources & Articles liés</h3>
                @php
                    $reviewArticle = \App\Models\Article::where('slug', $tool->slug . '-avis')->where('status', 'published')->first();
                    $factuGuide = \App\Models\Article::where('slug', 'facturation-electronique')->where('status', 'published')->first();
                    $tvaGuide = \App\Models\Article::where('slug', 'tva-micro-entreprise')->where('status', 'published')->first();
                @endphp
                <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px;">
                    <li>
                        @if($reviewArticle)
                            <a href="{{ route('reviews.show', $tool->slug . '-avis') }}" style="color:var(--tool-text); text-decoration:none; font-size:14px; display:inline-flex; align-items:center; gap:6px;">📄 Avis complet {{ ucfirst($tool->name) }}</a>
                        @else
                            <span style="color:#94a3b8; font-size:14px; display:inline-flex; align-items:center; gap:6px;">📄 Avis complet {{ ucfirst($tool->name) }} <small style="font-size:11px; background:#f1f5f9; padding:2px 6px; border-radius:4px; color:#64748b;">(À venir)</small></span>
                        @endif
                    </li>
                    <li>
                        <a href="{{ route('tools.pricing', $tool->slug) }}" style="color:var(--tool-text); text-decoration:none; font-size:14px; display:inline-flex; align-items:center; gap:6px;">💸 Tarifs & Offres {{ ucfirst($tool->name) }}</a>
                    </li>
                    <li>
                        @if($factuGuide)
                            <a href="{{ route('guides.show', 'facturation-electronique') }}" style="color:var(--tool-text); text-decoration:none; font-size:14px; display:inline-flex; align-items:center; gap:6px;">📚 Guide Facturation Électronique</a>
                        @else
                            <span style="color:#94a3b8; font-size:14px; display:inline-flex; align-items:center; gap:6px;">📚 Guide Facturation Électronique <small style="font-size:11px; background:#f1f5f9; padding:2px 6px; border-radius:4px; color:#64748b;">(À venir)</small></span>
                        @endif
                    </li>
                    <li>
                        @if($tvaGuide)
                            <a href="{{ route('guides.show', 'tva-micro-entreprise') }}" style="color:var(--tool-text); text-decoration:none; font-size:14px; display:inline-flex; align-items:center; gap:6px;">📚 Guide TVA Micro-entreprise</a>
                        @else
                            <span style="color:#94a3b8; font-size:14px; display:inline-flex; align-items:center; gap:6px;">📚 Guide TVA Micro-entreprise <small style="font-size:11px; background:#f1f5f9; padding:2px 6px; border-radius:4px; color:#64748b;">(À venir)</small></span>
                        @endif
                    </li>
                </ul>
            </div>
            
        </div>
    </section>
    <!-- Avantages et Inconvénients -->
    <section class="pros-cons-grid">
        <div class="pros-box">
            <div class="pros-cons-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Ce qu'on a adoré
            </div>
                        <ul class="pros-list">
                @forelse(($tool->strengths ?? []) as $strength)
                    <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg> {{ $strength }}</li>
                @empty
                    <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg> Fonctionnalités de base incluses.</li>
                @endforelse
            </ul>
        </div>
        <div class="cons-box">
            <div class="pros-cons-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Ce qui peut bloquer
            </div>
                        <ul class="cons-list">
                @forelse(($tool->limitations ?? []) as $limitation)
                    <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg> {{ $limitation }}</li>
                @empty
                    <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg> Pas de limites majeures détectées.</li>
                @endforelse
            </ul>
        </div>
    </section>

    <!-- Pour qui ? -->
    <section class="target-audience">
        <h2 class="target-audience-title">🎯 Pour qui {{ ucfirst($tool->name) }} est-il fait ?</h2>
        <div class="target-grid">
                        <div class="target-card target-card-good">
                <h4>✅ Parfait si vous cherchez :</h4>
                <ul style="font-size:14px; color:var(--tool-muted); line-height:1.5; padding-left:20px;">
                    @forelse(array_slice($tool->strengths ?? [], 0, 3) as $strength)
                        <li>{{ explode(':', $strength)[0] }}</li>
                    @empty
                        <li>Une solution de gestion pour indépendant.</li>
                    @endforelse
                </ul>
            </div>
            <div class="target-card target-card-bad">
                <h4>❌ À éviter si vous avez besoin de :</h4>
                <ul style="font-size:14px; color:var(--tool-muted); line-height:1.5; padding-left:20px;">
                    @forelse(array_slice($tool->limitations ?? [], 0, 3) as $limitation)
                        <li>{{ explode(':', $limitation)[0] }}</li>
                    @empty
                        <li>Fonctionnalités avancées non listées.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </section>

    <!-- Contenu SEO / Fonctionnalités -->
    

    <!-- Verdict -->
    <section class="verdict-box">
        <h2>Notre verdict final sur {{ ucfirst($tool->name) }}</h2>
        <p>Si vous cherchez à vous débarrasser de l'angoisse de la paperasse et des déclarations URSSAF, {{ ucfirst($tool->name) }} est la meilleure solution actuelle sur le marché français. Son approche "sans jargon" est une bouffée d'air frais.</p>
        <a href="{{ route('affiliate.redirect', $tool->slug) }}" target="_blank" rel="sponsored nofollow" class="review-cta-btn" style="display:inline-block; width:auto; padding:18px 48px; background:white; color:{{ $tool->slug === 'indy' ? '#F75A77' : 'var(--tool-primary)' }};">Profiter de l'offre gratuite {{ ucfirst($tool->name) }}</a>
        <p style="margin-top:16px; font-size:12px; color:rgba(255,255,255,0.5);">Essai gratuit de 15 jours, sans engagement ni carte de crédit requise.</p>
    </section>

</main>
@endsection
