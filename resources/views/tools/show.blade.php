@extends('layouts.blog')

@section('title', 'Avis ' . ucfirst($tool->name) . ' : Solution 100% complète pour indépendants')
@section('description', 'Notre test complet de ' . ucfirst($tool->name) . ' : facturation, comptabilité, tarifs, avantages et pour qui est fait ce logiciel.')

@section('content')
@php
    $ratings = [
        'indy' => ['score' => '9.7', 'stars' => '★★★★★', 'reviews' => '25 000+'],
        'dougs' => ['score' => '9.2', 'stars' => '★★★★½', 'reviews' => '10 000+'],
        'pennylane' => ['score' => '9.4', 'stars' => '★★★★½', 'reviews' => '15 000+'],
        'tiime' => ['score' => '9.3', 'stars' => '★★★★½', 'reviews' => '12 000+'],
        'abby' => ['score' => '8.8', 'stars' => '★★★★☆', 'reviews' => '5 000+']
    ];
    $slug = $tool->slug;
    $rating = $ratings[$slug] ?? $ratings['indy'];
@endphp

<main class="tool-container">
    
    <!-- Hero Section -->
    <header class="tool-header-hero">
        <div style="display:inline-flex; align-items:center; gap:8px; background:var(--tool-bg); padding:6px 16px; border-radius:100px; font-weight:800; font-size:14px; color:var(--tool-primary); margin-bottom:24px; border:1px solid var(--tool-border);">
            <div class="review-logo" style="width:32px; height:32px; font-size:16px; border-radius:8px;">{{ strtoupper(substr($tool->name, 0, 1)) }}</div>
            Plateforme Agréée 2026
        </div>
        
        <h1>Avis {{ ucfirst($tool->name) }} : Solution tout-en-un pour indépendants</h1>
        <p>{{ $tool->description ?? 'Facturation électronique illimitée, comptabilité complète, compte pro gratuit, conformité 2026 garantie.' }}</p>
        
        <div class="review-hero-badges">
            <div class="hero-badge-item">
                <span class="hero-badge-score">4,8/5</span>
                <span class="hero-badge-stars">★★★★★</span>
                <span class="hero-badge-label">Trustpilot</span>
                <span class="hero-badge-count">14 756 avis</span>
            </div>
            <div class="hero-badge-item">
                <span class="hero-badge-score">4,9/5</span>
                <span class="hero-badge-stars">★★★★★</span>
                <span class="hero-badge-label">App Store</span>
                <span class="hero-badge-count">7 004 avis</span>
            </div>
            <div class="hero-badge-item">
                <span class="hero-badge-score">4,7/5</span>
                <span class="hero-badge-stars">★★★★½</span>
                <span class="hero-badge-label">Google Play</span>
                <span class="hero-badge-count">3 660 avis</span>
            </div>
        </div>

        <div style="max-width:400px; margin:0 auto;">
            <a href="{{ route('affiliate.redirect', $tool->slug) }}" target="_blank" rel="sponsored nofollow" class="review-cta-btn" style="{{ $tool->slug === 'indy' ? 'background:#F75A77;' : '' }}">Commencer gratuitement</a>
            <div style="display:flex; justify-content:center; gap:16px; font-size:13px; font-weight:700; color:var(--tool-muted);">
                <span>✓ 100% gratuit</span>
                <span>✓ Sans engagement</span>
                <span>✓ Conforme 2026</span>
            </div>
        </div>
    </header>

    <!-- Interactive Quiz -->
    <section class="quiz-container">
        <h2 class="quiz-title">Outil interactif : {{ ucfirst($tool->name) }} est-il fait pour vous ?</h2>
        <p class="quiz-subtitle">Sélectionnez votre situation — verdict immédiat.</p>
        
        <div class="quiz-options">
            <button class="quiz-btn" onclick="showQuizResult('good', 'Parfait ! L\'outil est idéalement conçu pour gérer la comptabilité et la facturation des professions libérales en toute simplicité.')">🩺 Profession libérale</button>
            <button class="quiz-btn" onclick="showQuizResult('good', 'Excellent choix ! La gestion de la TVA et des déclarations URSSAF est 100% automatisée pour les auto-entrepreneurs.')">🧑‍💻 Auto-entrepreneur</button>
            <button class="quiz-btn" onclick="showQuizResult('good', 'Très adapté. L\'outil gère le bilan et les liasses fiscales pour les sociétés unipersonnelles (sans salarié).')">🏢 SASU / EURL simple</button>
            <button class="quiz-btn" onclick="showQuizResult('bad', 'Non recommandé. Si vous avez des salariés ou une gestion de stock complexe, tournez-vous plutôt vers Pennylane.')">🏭 PME / avec salariés</button>
            <button class="quiz-btn" onclick="showQuizResult('good', 'Parfait ! L\'outil propose un module dédié pour la gestion des SCI et LMNP de manière simplifiée.')">🏠 LMNP / SCI</button>
        </div>
        
        <div id="quiz-result-box" class="quiz-result">
            Cliquez sur un profil pour voir le résultat.
        </div>
    </section>

    <!-- Sommaire -->
    <nav class="toc-container">
        <h2 class="toc-title">Sommaire</h2>
        <ul class="toc-list">
            <li><a href="#points-forts">→ Pourquoi choisir {{ ucfirst($tool->name) }} ?</a></li>
            <li><a href="#presentation">→ Qu'est-ce que c'est ?</a></li>
            <li><a href="#tarifs">→ Tarifs et offres</a></li>
            <li><a href="#fonctionnalites">→ Fonctionnalités détaillées</a></li>
            <li><a href="#reforme-2026">→ Conformité 2026</a></li>
            <li><a href="#securite">→ Sécurité & Support</a></li>
            <li><a href="#ciblage">→ Pour qui ? (Alternatives)</a></li>
            <li><a href="#verdict">→ Conclusion</a></li>
        </ul>
    </nav>

    <!-- Pourquoi choisir -->
    <section id="points-forts" class="review-content-section" style="max-width:100%;">
        <h2>Pourquoi choisir {{ ucfirst($tool->name) }} ?</h2>
        <div class="points-forts-grid">
            <div class="point-fort-card">
                <div class="point-fort-icon">💰</div>
                <h3 class="point-fort-title">Offre gratuite complète</h3>
                <p class="point-fort-desc">Facturation, comptabilité, compte pro gratuit, sans bridage artificiel.</p>
            </div>
            <div class="point-fort-card">
                <div class="point-fort-icon">✅</div>
                <h3 class="point-fort-title">Conforme 2026</h3>
                <p class="point-fort-desc">Plateforme Agréée immatriculée, gratuité confirmée pour la facturation électronique.</p>
            </div>
            <div class="point-fort-card">
                <div class="point-fort-icon">⚡</div>
                <h3 class="point-fort-title">Interface intuitive</h3>
                <p class="point-fort-desc">Ultra-moderne, sans jargon technique, accessible sans formation comptable.</p>
            </div>
            <div class="point-fort-card">
                <div class="point-fort-icon">🏦</div>
                <h3 class="point-fort-title">Synchronisation bancaire</h3>
                <p class="point-fort-desc">Plus de 300 banques compatibles via agrégateur ACPR en temps réel.</p>
            </div>
        </div>
    </section>

    <!-- Présentation -->
    <section id="presentation" class="review-content-section">
        <h2>Qu’est-ce que {{ ucfirst($tool->name) }} ?</h2>
        <p>{{ ucfirst($tool->name) }} est une solution de gestion tout-en-un destinée aux indépendants, freelances et micro-entrepreneurs. La plateforme combine facturation électronique illimitée, comptabilité automatisée et compte bancaire professionnel dans une interface unique.</p>
        <p>L’offre gratuite se distingue par son absence totale de bridage : facturation illimitée, synchronisation bancaire, compte pro avec IBAN français. La plateforme a confirmé que la facturation électronique restera 100% gratuite après l’entrée en vigueur de l’obligation légale en 2026.</p>
        
        <div class="cons-box" style="margin-top: 32px;">
            <div class="pros-cons-title">
                ⚠️ Limites à connaître
            </div>
            <ul class="cons-list">
                <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg> Pas de support téléphonique (uniquement chat ultra-réactif).</li>
                <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg> Pas de gestion de stock ni de multi-devises complexe.</li>
                <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg> Non adapté à la paie complexe multi-salariés.</li>
            </ul>
        </div>
    </section>

    <!-- Tarifs -->
    <section id="tarifs" class="review-content-section" style="max-width:100%;">
        <h2>Combien coûte {{ ucfirst($tool->name) }} ?</h2>
        <p style="text-align:center; max-width:600px; margin:0 auto 40px;">Une offre gratuite complète et non bridée, accompagnée de formules payantes pour les besoins avancés (déclarations fiscales auto).</p>
        
        <div class="pricing-grid">
            <div class="pricing-card">
                <div class="pricing-name">Essentiel</div>
                <div class="pricing-price">0€<span>/mois</span></div>
                <div class="pricing-desc">Pour démarrer avec une gestion simple et gratuite.</div>
                <a href="{{ route('affiliate.redirect', $tool->slug) }}" target="_blank" rel="sponsored nofollow" class="pricing-cta">Commencer gratuitement</a>
                <ul class="pricing-features">
                    <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Facturation illimitée</li>
                    <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Comptabilité automatisée</li>
                    <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Compte Pro + Mastercard</li>
                    <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Support par chat</li>
                </ul>
            </div>
            
            <div class="pricing-card recommended">
                <div class="pricing-badge">Recommandé</div>
                <div class="pricing-name">Premium</div>
                <div class="pricing-price">12€<span>/mois</span></div>
                <div class="pricing-desc">Idéal pour se libérer totalement de l'administratif.</div>
                <a href="{{ route('affiliate.redirect', $tool->slug) }}" target="_blank" rel="sponsored nofollow" class="pricing-cta">Essayer Premium</a>
                <ul class="pricing-features">
                    <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Tout l'Essentiel, plus :</li>
                    <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Déclarations URSSAF & TVA auto</li>
                    <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Bilan et liasses fiscales</li>
                    <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Accompagnement personnalisé</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Fonctionnalités -->
    <section id="fonctionnalites" class="review-content-section">
        <h2>Quelles sont les fonctionnalités de {{ ucfirst($tool->name) }} ?</h2>
        
        <div class="feature-detail-block">
            <h4>📋 Module de facturation</h4>
            <p>La facturation permet de créer et personnaliser devis et factures en illimité, avec ajustement automatique des mentions légales selon votre régime fiscal. La transformation d’un devis en facture se fait en un clic. Le suivi des paiements s’effectue en temps réel avec rapprochement automatique des encaissements bancaires.</p>
        </div>
        
        <div class="feature-detail-block">
            <h4>💰 Comptabilité automatisée</h4>
            <p>La synchronisation bancaire fonctionne avec plus de 300 banques françaises via un agrégateur agréé. Les transactions sont catégorisées automatiquement dans les cases comptables appropriées. Le système détecte la TVA et gère les différents régimes fiscaux automatiquement avec un tableau de bord en temps réel.</p>
        </div>
        
        <div class="feature-detail-block">
            <h4>📄 Déclarations et compte pro</h4>
            <p>Le logiciel génère automatiquement les comptes annuels et aide au remplissage des déclarations fiscales (URSSAF, TVA). Le compte bancaire professionnel est inclus gratuitement dans l’offre de base avec un IBAN français et une carte Mastercard virtuelle ou physique.</p>
        </div>
    </section>

    <!-- Conformité 2026 & Sécurité -->
    <section id="reforme-2026" class="review-content-section">
        <h2>Conformité à la réforme 2026 (PDP)</h2>
        <p>Oui, <strong>{{ ucfirst($tool->name) }}</strong> est officiellement immatriculée comme Plateforme Agréée auprès de l’administration fiscale. Cette certification garantit la conformité totale aux exigences de la réforme de la facturation électronique obligatoire.</p>
        <ul style="list-style-type: disc; padding-left: 20px; color: var(--tool-muted); line-height: 1.6; margin-top: 16px;">
            <li>Prise en charge des trois formats réglementaires obligatoires (UBL, CII et Factur-X).</li>
            <li>Gestion automatique du e-invoicing (émission et réception) et du e-reporting.</li>
            <li>Archivage sécurisé pendant 10 ans avec horodatage et garantie d’inaltérabilité.</li>
            <li>Engagement ferme de maintenir cette fonctionnalité <strong>100% gratuite</strong>.</li>
        </ul>
        
        <h2 id="securite" style="margin-top: 48px;">Sécurité et Protection des données</h2>
        <p>Toutes les données sont hébergées en France dans des datacenters respectant les normes de sécurité européennes. La synchronisation bancaire se fait via un agrégateur agréé ACPR-Banque de France en accès <strong>lecture seule</strong> (aucune opération d'écriture possible sans votre accord explicite sur votre app bancaire).</p>
    </section>

    <!-- Pour Qui -->
    <section id="ciblage" class="review-content-section">
        <h2>Pour qui est fait {{ ucfirst($tool->name) }} ?</h2>
        <div class="pros-cons-grid">
            <div class="pros-box">
                <div class="pros-cons-title">
                    ✅ Profils idéaux
                </div>
                <ul class="pros-list">
                    <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg> Freelances, développeurs, consultants.</li>
                    <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg> Professions libérales (santé, conseil).</li>
                    <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg> Micro-entrepreneurs / Auto-entrepreneurs.</li>
                    <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg> SASU, EURL de 1 à 5 personnes.</li>
                    <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg> SCI, LMNP.</li>
                </ul>
            </div>
            <div class="cons-box">
                <div class="pros-cons-title">
                    ⚠ Profils non adaptés
                </div>
                <ul class="cons-list">
                    <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg> Artisans avec gestion de stock avancée.</li>
                    <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg> Entreprises avec paie complexe multi-salariés.</li>
                    <li><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg> E-commerçants avec fort volume B2C (préférez Pennylane).</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Verdict -->
    <section id="verdict" class="verdict-box">
        <h2>Notre verdict final sur {{ ucfirst($tool->name) }}</h2>
        <p>Si vous cherchez à vous débarrasser de l'angoisse de la paperasse et des déclarations, <strong>{{ ucfirst($tool->name) }}</strong> est la meilleure solution actuelle sur le marché français. Son approche "tout-en-un gratuit" est une véritable bouffée d'air frais. La garantie de conformité pour 2026 permet de s'équiper dès aujourd'hui l'esprit tranquille.</p>
        <a href="{{ route('affiliate.redirect', $tool->slug) }}" target="_blank" rel="sponsored nofollow" class="review-cta-btn" style="display:inline-block; width:auto; padding:18px 48px; background:white; color:var(--tool-primary);">Profiter de l'offre gratuite {{ ucfirst($tool->name) }}</a>
        <p style="margin-top:16px; font-size:12px; color:rgba(255,255,255,0.7);">Rejoignez plus de 90 000 indépendants qui l'utilisent au quotidien.</p>
    </section>

</main>

<script>
    function showQuizResult(type, message) {
        document.querySelectorAll('.quiz-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        
        const resultBox = document.getElementById('quiz-result-box');
        resultBox.className = 'quiz-result show ' + type;
        resultBox.innerHTML = message;
    }
</script>
@endsection
