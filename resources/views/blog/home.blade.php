@extends('layouts.blog')

@section('title', 'Les meilleurs logiciels pour freelances, auto-entrepreneurs et indépendants')
@section('description', 'Plus de 200 guides, comparatifs et outils gratuits pour choisir la meilleure solution selon votre activité.')

@section('content')

<main class="home-container">
    <!-- Hero Section -->
    <section class="hp-hero">
        <div class="hp-hero-inner">
            <div class="hp-hero-stats">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20" style="margin-right: 6px;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"/></svg>
                Comparatifs & Outils mis à jour en continu · 2026
            </div>

            <h1 style="font-size: clamp(32px, 5vw, 56px); line-height: 1.1; margin-bottom: 24px;">
                Trouvez le meilleur logiciel de <span class="highlight">comptabilité</span>, <span class="highlight">facturation</span> ou <span class="highlight">banque pro</span>
            </h1>

            <p class="hp-hero-subtitle">
                Comparez les meilleures solutions conçues spécifiquement pour les freelances, auto-entrepreneurs et indépendants.
            </p>

            <div class="hp-hero-trust">
                <span>★★★★★</span> +5900 indépendants nous font confiance
            </div>
        </div>
    </section>

    @include('blog.partials.quiz_wizard')

    <!-- Trust Banner (Honest claims) -->
    <div class="hp-trust-banner">
        <div class="hp-trust-banner-item">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
            Guides actualisés régulièrement
        </div>
        <div class="hp-trust-banner-item">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
            Grille d'évaluation publique
        </div>
        <div class="hp-trust-banner-item">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
            Sources vérifiées : URSSAF, impôts.gouv
        </div>
        <div class="hp-trust-banner-item">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
            Méthodologie 100% transparente
        </div>
    </div>



    <!-- Top Selection Section (avec Badges) -->
    <section class="home-section" id="selection">
        
        <h2 class="home-section-title">Notre recommandation selon votre situation</h2>
        
        <div class="home-softwares-list">
            @php
                $indyProject = $projects->firstWhere('slug', 'indy');
                $indyColor = $indyProject->brand_color ?? '#F75A77';
                $indyTextColor = $indyProject ? $indyProject->brand_text_color : '#ffffff';
                $indyTextOnWhite = ($indyTextColor === '#0f172a') ? '#0f172a' : $indyColor;
                
                $pennylaneProject = $projects->firstWhere('slug', 'pennylane');
                $pennylaneColor = $pennylaneProject->brand_color ?? '#3b82f6';
                $pennylaneTextColor = $pennylaneProject ? $pennylaneProject->brand_text_color : '#ffffff';
                $pennylaneTextOnWhite = ($pennylaneTextColor === '#0f172a') ? '#0f172a' : $pennylaneColor;
                
                $abbyProject = $projects->firstWhere('slug', 'abby');
                $abbyColor = $abbyProject->brand_color ?? '#a855f7';
                $abbyTextColor = $abbyProject ? $abbyProject->brand_text_color : '#ffffff';
                $abbyTextOnWhite = ($abbyTextColor === '#0f172a') ? '#0f172a' : $abbyColor;
                
                $shineProject = $projects->firstWhere('slug', 'shine');
                $shineColor = $shineProject->brand_color ?? '#f97316';
                $shineTextColor = $shineProject ? $shineProject->brand_text_color : '#ffffff';
                $shineTextOnWhite = ($shineTextColor === '#0f172a') ? '#0f172a' : $shineColor;
            @endphp
            
            <!-- Notre sélection 1: Indy -->
            <div class="hp-selection-card" style="--home-accent: {{ $indyColor }}; --home-accent-hover: {{ $indyColor }}; --home-primary: {{ $indyTextOnWhite }}; border-color: var(--home-accent);">
                <div class="hp-selection-badge" style="background: var(--home-accent); color: {{ $indyTextColor }};">🏆 Notre meilleure recommandation pour les indépendants</div>
                <div class="hp-selection-content" style="display:flex; flex-wrap:wrap; gap:32px; align-items:center;">
                    <div style="flex: 1 1 300px;">
                        <h3 class="hp-selection-title" style="margin-bottom:8px; display:flex; align-items:center; gap:8px;">Indy ⭐</h3>
                        <p style="color:var(--home-muted); font-size:15px; margin-bottom:24px;">Une alternative simple à la comptabilité traditionnelle pour freelances et petites entreprises.</p>
                        
                        <ul class="hp-clean-list" style="margin-bottom:16px;">
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Devis & Facturation illimités</li>
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Déclarations URSSAF & TVA automatiques</li>
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Bilan & Liasse Fiscale générés facilement</li>
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Compte Pro intégré sans frais cachés</li>
                        </ul>
                    </div>
                    <div class="home-software-action" style="flex: 0 0 220px; display:flex; flex-direction:column;">
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px; margin-bottom:16px;">
                            <div style="font-size:18px; font-weight:900; color:var(--home-primary); display:flex; align-items:center; gap:6px;">💰 Dès 0€/mois</div>
                            <div style="font-size:14px; font-weight:800; color:#eab308; display:flex; align-items:center; gap:4px;">⭐ Note : 9.7/10</div>
                        </div>
                        <a href="{{ route('tools.show', 'indy') }}" class="home-software-btn" style="margin-bottom:12px; width:100%; text-align:center; box-sizing:border-box;">Lire le test complet</a>
                        <a href="{{ route('affiliate.redirect', 'indy') }}" target="_blank" rel="sponsored nofollow" class="home-software-btn hp-btn-primary" style="width:100%; text-align:center; box-sizing:border-box; background: {{ $indyColor }}; color: {{ $indyTextColor }};">Profiter de l'offre gratuite</a>
                    </div>
                </div>
            </div>

            <!-- 2. Pennylane -->
            <div class="hp-selection-card" style="--home-accent: {{ $pennylaneColor }}; --home-accent-hover: {{ $pennylaneColor }}; --home-primary: {{ $pennylaneTextOnWhite }}; border-color: var(--home-accent); margin-top: 24px;">
                <div class="hp-selection-badge" style="background: var(--home-accent); color: {{ $pennylaneTextColor }};">🏢 Indispensable pour l'E-commerce, les TPE et la TVA sur marge</div>
                <div class="hp-selection-content" style="display:flex; flex-wrap:wrap; gap:32px; align-items:center;">
                    <div style="flex: 1 1 300px;">
                        <h3 class="hp-selection-title" style="margin-bottom:8px; display:flex; align-items:center; gap:8px;">Pennylane <span style="font-size:10px; padding:2px 8px; background:#f1f5f9; border-radius:4px; color:var(--home-muted); text-transform:uppercase; font-weight:700;">Compta + Banque</span></h3>
                        <p style="color:var(--home-muted); font-size:14px; margin-bottom:16px;">Une solution unifiée pour la gestion financière et comptable des PME.</p>
                        <ul class="hp-clean-list">
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Compte Pro intégré</li>
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Devis & Facturation</li>
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Partage Expert-Comptable</li>
                        </ul>
                    </div>
                    <div class="home-software-action" style="flex: 0 0 220px; display:flex; flex-direction:column;">
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px; margin-bottom:16px;">
                            <div style="font-size:18px; font-weight:900; color:var(--home-primary); display:flex; align-items:center; gap:6px;">💰 Dès 14€/mois</div>
                            <div style="font-size:14px; font-weight:800; color:#eab308; display:flex; align-items:center; gap:4px;">⭐ Note : 9.4/10</div>
                        </div>
                        <a href="{{ route('tools.show', 'pennylane') }}" class="home-software-btn" style="margin-bottom:12px; width:100%; text-align:center; box-sizing:border-box;">Lire le test complet</a>
                        <a href="{{ route('affiliate.redirect', 'pennylane') }}" target="_blank" rel="sponsored nofollow" class="home-software-btn hp-btn-primary" style="width:100%; text-align:center; box-sizing:border-box; background: {{ $pennylaneColor }}; color: {{ $pennylaneTextColor }};">Découvrir l'offre</a>
                    </div>
                </div>
            </div>

            <!-- 3. Abby -->
            <div class="hp-selection-card" style="--home-accent: {{ $abbyColor }}; --home-accent-hover: {{ $abbyColor }}; --home-primary: {{ $abbyTextOnWhite }}; border-color: var(--home-accent); margin-top: 24px;">
                <div class="hp-selection-badge" style="background: var(--home-accent); color: {{ $abbyTextColor }};">🚀 L'outil gratuit parfait pour les Micro-entrepreneurs purs</div>
                <div class="hp-selection-content" style="display:flex; flex-wrap:wrap; gap:32px; align-items:center;">
                    <div style="flex: 1 1 300px;">
                        <h3 class="hp-selection-title" style="margin-bottom:8px; display:flex; align-items:center; gap:8px;">Abby <span style="font-size:10px; padding:2px 8px; background:#f1f5f9; border-radius:4px; color:var(--home-muted); text-transform:uppercase; font-weight:700;">Pour micro-entrepreneurs</span></h3>
                        <p style="color:var(--home-muted); font-size:14px; margin-bottom:16px;">L'application tout-en-un conçue spécifiquement pour les auto-entrepreneurs.</p>
                        <ul class="hp-clean-list">
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Facturation gratuite</li>
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Déclaration URSSAF</li>
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Paiement en ligne</li>
                        </ul>
                    </div>
                    <div class="home-software-action" style="flex: 0 0 220px; display:flex; flex-direction:column;">
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px; margin-bottom:16px;">
                            <div style="font-size:18px; font-weight:900; color:var(--home-primary); display:flex; align-items:center; gap:6px;">💰 Dès 0€/mois</div>
                            <div style="font-size:14px; font-weight:800; color:#eab308; display:flex; align-items:center; gap:4px;">⭐ Note : 8.8/10</div>
                        </div>
                        <a href="{{ route('tools.show', 'abby') }}" class="home-software-btn" style="margin-bottom:12px; width:100%; text-align:center; box-sizing:border-box;">Lire le test complet</a>
                        <a href="{{ route('affiliate.redirect', 'abby') }}" target="_blank" rel="sponsored nofollow" class="home-software-btn hp-btn-primary" style="width:100%; text-align:center; box-sizing:border-box; background: {{ $abbyColor }}; color: {{ $abbyTextColor }};">Découvrir l'offre</a>
                    </div>
                </div>
            </div>

            <!-- 4. Shine -->
            <div class="hp-selection-card" style="--home-accent: {{ $shineColor }}; --home-accent-hover: {{ $shineColor }}; --home-primary: {{ $shineTextOnWhite }}; border-color: var(--home-accent); margin-top: 24px;">
                <div class="hp-selection-badge" style="background: var(--home-accent); color: {{ $shineTextColor }};">💳 La meilleure banque pro pour VTC, Livreurs et Freelances</div>
                <div class="hp-selection-content" style="display:flex; flex-wrap:wrap; gap:32px; align-items:center;">
                    <div style="flex: 1 1 300px;">
                        <h3 class="hp-selection-title" style="margin-bottom:8px; display:flex; align-items:center; gap:8px;">Shine <span style="font-size:10px; padding:2px 8px; background:#f1f5f9; border-radius:4px; color:var(--home-muted); text-transform:uppercase; font-weight:700;">Compte Pro & Facturation</span></h3>
                        <p style="color:var(--home-muted); font-size:14px; margin-bottom:16px;">Le compte pro qui offre un outil de facturation intégré et un support premium.</p>
                        <ul class="hp-clean-list">
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Carte Mastercard Business</li>
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Outil de devis et facturation inclus</li>
                            <li><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Assurances professionnelles</li>
                        </ul>
                    </div>
                    <div class="home-software-action" style="flex: 0 0 220px; display:flex; flex-direction:column;">
                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px; margin-bottom:16px;">
                            <div style="font-size:18px; font-weight:900; color:var(--home-primary); display:flex; align-items:center; gap:6px;">💰 Dès 0€/mois</div>
                            <div style="font-size:14px; font-weight:800; color:#eab308; display:flex; align-items:center; gap:4px;">⭐ Note : 9.3/10</div>
                        </div>
                        <a href="{{ route('tools.show', 'shine') }}" class="home-software-btn" style="margin-bottom:12px; width:100%; text-align:center; box-sizing:border-box;">Lire le test complet</a>
                        <a href="{{ route('affiliate.redirect', 'shine') }}" target="_blank" rel="sponsored nofollow" class="home-software-btn hp-btn-primary" style="width:100%; text-align:center; box-sizing:border-box; background: {{ $shineColor }}; color: {{ $shineTextColor }};">Découvrir l'offre</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sélecteur de Métiers Interactif (Alpine.js) -->
    <section class="home-section" id="metiers-selector" x-data="professionSelector()">
        <div style="text-align: center; margin-bottom: 40px;">
            <div style="font-size: 13px; font-weight: 800; color: var(--home-accent); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">🧭 Trouvez la solution sur-mesure pour votre métier</div>
            <h2 class="home-section-title" style="margin-bottom: 16px;">Choisissez parmi nos 78 professions analysées</h2>
            <p style="color: var(--home-muted); font-size: 18px; max-width: 600px; margin: 0 auto;">Obtenez votre comparatif personnalisé et découvrez les statuts et liasses adaptés à votre activité.</p>
        </div>

        <!-- Recherche et Catégories -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 32px;">
            <div style="position: relative; margin-bottom: 24px;">
                <div style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); font-size: 20px; color: #94a3b8;">🔍</div>
                <input x-model="search" type="text" placeholder="Rechercher un métier (ex: IDEL, Développeur, Ostéopathe, VTC...)" style="width: 100%; padding: 16px 16px 16px 48px; border-radius: 12px; border: 2px solid #e2e8f0; font-size: 16px; font-family: inherit; font-weight: 600; outline: none; transition: border-color 0.3s;" onfocus="this.style.borderColor='var(--home-accent)'" onblur="this.style.borderColor='#e2e8f0'">
            </div>
            
            <style>
.metier-tag {
    padding: 10px 20px;
    border-radius: 30px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #ffffff;
    color: #475569;
}
.metier-tag:hover {
    border-color: var(--home-accent);
    color: var(--home-accent);
    background: #f8fafc;
}
.metier-tag.active {
    background: var(--home-accent);
    color: #ffffff;
    border-color: var(--home-accent);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}
.metier-tag.active:hover {
    background: var(--home-accent);
    color: #ffffff;
}
</style>
<div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
                <template x-for="cat in categories" :key="cat">
                    <button @click="category = cat; showAll = true" 
                            :class="{'metier-tag': true, 'active': category === cat}"
                            x-text="cat === 'all' ? '🌍 Tous (' + metiers.length + ')' : cat">
                    </button>
                </template>
            </div>
        </div>

        <!-- Grille des Résultats -->
        <div class="hp-grid-3">
            <template x-for="metier in displayedMetiers" :key="metier.nom">
                <a :href="metier.url" class="hp-article-card" style="padding: 0; display: flex; flex-direction: column; overflow: hidden; border-radius: 16px; text-decoration: none; border: 1px solid #e2e8f0; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 10px 25px -5px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <div style="padding: 24px; display: flex; flex-direction: column; flex-grow: 1; background: #ffffff;">
                        <div style="font-size: 32px; margin-bottom: 12px;" x-text="metier.emoji"></div>
                        <div class="hp-card-title" style="font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 16px; line-height: 1.3;" x-text="metier.nom"></div>
                        
                        <div style="font-size: 13px; color: #475569; margin-bottom: 12px;">
                            <strong style="color: #0f172a;">Statuts :</strong> <span x-text="metier.statuts.join(', ')"></span>
                        </div>
                        
                        <div style="font-size: 13px; color: #475569; margin-bottom: 16px; flex-grow: 1;">
                            <strong style="color: #0f172a;">Outil recommandé :</strong> 
                            <span style="display: inline-block; padding: 2px 8px; background: #f1f5f9; border-radius: 4px; font-weight: 700; color: var(--home-accent);" x-text="'🏆 ' + metier.tool_name"></span>
                        </div>
                        
                        <div class="hp-card-footer" style="margin-top: auto; color: var(--home-accent); font-weight: 700; border-top: 1px solid #f1f5f9; padding-top: 16px; display: flex; align-items: center; justify-content: space-between;">
                            Voir le guide complet 
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </div>
                    </div>
                </a>
            </template>
            
            <!-- Carte 9 : Voir tout -->
            <div x-show="!showAll && hasMore" @click="showAll = true" style="padding: 32px 24px; display: flex; flex-direction: column; justify-content: center; align-items: center; cursor: pointer; border-radius: 16px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #e2e8f0; transition: all 0.3s ease; min-height: 250px; text-align: center;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 10px 25px -5px rgba(0, 0, 0, 0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: #ffffff; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0f172a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                </div>
                <div style="font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">Voir les <span x-text="totalFiltered"></span> métiers</div>
                <div style="color: #64748b; font-size: 14px; margin-bottom: 24px; padding: 0 16px;">Découvrez toutes nos analyses et trouvez l'outil idéal.</div>
                <div style="background: #0f172a; color: #ffffff; font-weight: 700; padding: 10px 24px; border-radius: 8px; font-size: 15px; box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2);">Afficher tout</div>
            </div>
        </div>
        
        <div x-show="filteredMetiers.length === 0" style="text-align: center; padding: 40px; background: #f8fafc; border-radius: 16px; color: #64748b; font-weight: 600; border: 1px dashed #cbd5e1; margin-top: 24px;">
            <div style="font-size: 32px; margin-bottom: 12px;">🔍</div>
            Aucun métier trouvé pour "<span x-text="search"></span>". <br>Essayez un autre terme ou explorez par catégorie.
        </div>
    </section>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('professionSelector', () => ({
            search: '',
            category: 'all',
            showAll: false,
            metiers: @json($metiers ?? []),
            
            init() {
                this.$watch('search', value => {
                    if (value.length > 0) this.showAll = true;
                });
            },
            
            get categories() {
                const cats = new Set(this.metiers.map(m => m.category).filter(c => c));
                return ['all', ...Array.from(cats)].sort();
            },
            
            get filteredMetiers() {
                return this.metiers.filter(m => {
                    const matchSearch = m.nom.toLowerCase().includes(this.search.toLowerCase()) || 
                                        m.statuts.some(s => s.toLowerCase().includes(this.search.toLowerCase())) ||
                                        m.tool_name.toLowerCase().includes(this.search.toLowerCase());
                    const matchCat = this.category === 'all' || m.category === this.category;
                    return matchSearch && matchCat;
                });
            },
            
            get displayedMetiers() {
                if (this.showAll) return this.filteredMetiers;
                
                if (this.search === '' && this.category === 'all') {
                    const featuredNames = [
                        'Médecin Généraliste', 
                        'Développeur Freelance', 
                        'Correcteur / Lecteur / Écrivain Public', 
                        'Friperie / Revendeur Occasion', 
                        'Chauffeur VTC', 
                        'Livreur Indépendant (Deliveroo/UberEats)', 
                        'Dropshipper / E-commerçant International', 
                        'Architecte'
                    ];
                    return this.metiers.filter(m => featuredNames.includes(m.nom));
                }
                
                return this.filteredMetiers.slice(0, 8);
            },
            
            get totalFiltered() {
                return this.filteredMetiers.length;
            },
            
            get hasMore() {
                return this.totalFiltered > 8;
            }
        }))
    })
    </script>

    <!-- Outils Gratuits SEO (V3 style) -->
    <section class="hp-tools-mega-block">
        <h2 class="hp-tools-mega-title">Les outils gratuits les plus utilisés</h2>
        <p class="hp-tools-mega-subtitle">Ne sortez plus votre calculatrice. Nos générateurs font le travail à votre place.</p>
        <ul style="list-style:none; padding:0; margin: 16px 0 32px 0; display:flex; justify-content:center; gap:24px; color:#cbd5e1; font-size:14px; flex-wrap:wrap; font-weight:700;">
            <li>✓ 100% gratuits</li>
            <li>✓ Sans inscription</li>
            <li>✓ Mis à jour automatiquement</li>
            <li>✓ Conformes à la réglementation française</li>
        </ul>
                <div class="hp-tools-mega-grid">
            @php 
                $toolIcons = ['calculateur-tva' => '🧮', 'calculateur-tjm' => '💰', 'simulateur-micro' => '📈'];
            @endphp
            @foreach(collect($freeTools)->take(3) as $tool)
            <a href="{{ route('free-tools.show', $tool['slug']) }}" class="hp-tool-mega-card" style="display:flex; flex-direction:column; justify-content:space-between; height:100%;">
                <div>
                    <div class="hp-tool-mega-card-icon" style="font-size:48px;">{{ $toolIcons[$tool['type']] ?? '🛠️' }}</div>
                    <div class="hp-tool-mega-card-title" style="font-size:20px;">{{ $tool['title'] }}</div>
                    <div style="font-size:14px; color:#cbd5e1; margin-top:12px; line-height:1.5;">{{ $tool['description'] }}</div>
                </div>
                <div style="margin-top:24px; padding:10px; text-align:center; background:rgba(255,255,255,0.1); border-radius:8px; font-weight:700;">Utiliser l'outil</div>
            </a>
            @endforeach
            <a href="{{ route('free-tools.index') }}" class="hp-tool-mega-card" style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100%; border: 2px dashed rgba(255,255,255,0.25); background: rgba(255,255,255,0.03); text-align: center; padding: 24px; transition: all 0.3s ease; border-radius: 16px; text-decoration: none;">
                <div style="font-size:32px; margin-bottom:12px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));">🛠️</div>
                <div style="font-weight:800; font-size: 18px; color: #ffffff; margin-bottom: 6px;">Voir les 6 outils gratuits</div>
                <div style="font-size: 13px; color: #cbd5e1; font-weight: 600;">Accès immédiat et sans inscription →</div>
            </a>
        </div>
    </section>

    {{-- <!-- Hubs (Recherches fréquentes) -->
    <section class="home-section">
        <h2 class="home-section-title">Les catégories les plus recherchées</h2>
        <div class="home-categories-grid">
            @php 
                $categoryIcons = [
                    'comptabilite' => '📊', 'facturation' => '📄', 'banque-pro' => '💳',
                    'compte-pro' => '💼', 'crm' => '🤝', 'signature-electronique' => '✍️',
                    'notes-de-frais' => '🧾', 'paiement-en-ligne' => '💰'
                ];
            @endphp
            @foreach($categories->take(8) as $cat)
            <a href="{{ route('blog.show', $cat->slug) }}" class="home-category-card" style="display:flex; flex-direction:column; align-items:center;">
                <div class="home-category-icon" style="background: rgba(37, 99, 235, 0.08); color: var(--home-accent);">{{ $categoryIcons[$cat->slug] ?? '📁' }}</div>
                <div style="font-weight: 800;">{{ $cat->name }}</div>
                <div style="font-size: 13px; color: var(--home-muted); margin-top: 4px; font-weight: 700;">→ {{ $cat->articles_count }} guides</div>
            </a>
            @endforeach
        </div>
    </section> --}}

    <!-- Encart Auteur (SEO EEAT) -->
    <section class="hp-author-block">
        <div class="hp-author-avatar" style="padding: 0; overflow: hidden; background: none; border: none; box-shadow: none;"><img src="/images/author-kylian.png" alt="Kylian" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; box-shadow: 0 4px 6px rgba(0,0,0,0.1);" /></div>
        <div class="hp-author-info">
            <div style="font-size:12px; font-weight:700; color:var(--home-muted); margin-bottom:4px; text-transform:uppercase; letter-spacing:1px;">L'Expert derrière BusinessKit</div>
            <h3>Kylian Dev.</h3>
            <p>Créateur de BusinessKit, développeur Laravel et indépendant. Je teste, compare et analyse les logiciels destinés aux freelances et petites entreprises afin de proposer des recommandations transparentes et régulièrement mises à jour.</p>
            <div class="hp-author-tags">
                <span class="hp-author-tag">Basé en France</span>
                <span class="hp-author-tag">Spécialiste outils SaaS</span>
                <a href="{{ route('author.show') }}" style="font-size:13px; font-weight:700; color:var(--home-accent); text-decoration:none; margin-left:8px; align-self:center;">Voir la méthodologie complète →</a>
            </div>
        </div>
    </section>

    <!-- DATA Section: Le marché en chiffres -->
    <section class="home-section" style="text-align: center;">
        
        <div style="position: relative; z-index: 10;">
            <div style="font-size: 14px; font-weight: 800; color: var(--home-accent); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 16px;">📈 Notre Base de Données</div>
            <h2 style="font-family: 'Manrope', sans-serif; font-size: 42px; font-weight: 900; margin-bottom: 24px; color: #0f172a; line-height: 1.2;">Le marché au peigne fin</h2>
            <p style="color: #475569; font-size: 20px; max-width: 650px; margin: 0 auto 56px; line-height: 1.6;">Nous analysons et actualisons les données du marché en continu pour vous fournir la comparaison la plus fiable et exhaustive de France.</p>
            
            <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 24px;">
                
                <!-- Stat 1 -->
                <div style="flex: 1 1 180px; max-width: 220px; background: #ffffff; padding: 32px 24px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;" onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 20px 25px -5px rgba(0, 0, 0, 0.1)'; this.style.borderColor='rgba(56, 189, 248, 0.5)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)'; this.style.borderColor='#e2e8f0';">
                    <div style="font-size: 32px; margin-bottom: 16px;">💻</div>
                    <div style="font-size: 48px; font-weight: 900; color: #0f172a; margin-bottom: 8px;">312</div>
                    <div style="font-size: 15px; color: #64748b; font-weight: 700; letter-spacing: 0.5px;">Logiciels</div>
                </div>
                
                <!-- Stat 2 -->
                <div style="flex: 1 1 180px; max-width: 220px; background: #ffffff; padding: 32px 24px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;" onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 20px 25px -5px rgba(0, 0, 0, 0.1)'; this.style.borderColor='rgba(167, 139, 250, 0.5)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)'; this.style.borderColor='#e2e8f0';">
                    <div style="font-size: 32px; margin-bottom: 16px;">📂</div>
                    <div style="font-size: 48px; font-weight: 900; color: #0f172a; margin-bottom: 8px;">18</div>
                    <div style="font-size: 15px; color: #64748b; font-weight: 700; letter-spacing: 0.5px;">Catégories</div>
                </div>
                
                <!-- Stat 3 -->
                <div style="flex: 1 1 180px; max-width: 220px; background: #ffffff; padding: 32px 24px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;" onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 20px 25px -5px rgba(0, 0, 0, 0.1)'; this.style.borderColor='rgba(52, 211, 153, 0.5)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)'; this.style.borderColor='#e2e8f0';">
                    <div style="font-size: 32px; margin-bottom: 16px;">💰</div>
                    <div style="font-size: 48px; font-weight: 900; color: #0f172a; margin-bottom: 8px;">4.2k</div>
                    <div style="font-size: 15px; color: #64748b; font-weight: 700; letter-spacing: 0.5px;">Tarifs analysés</div>
                </div>
                
                <!-- Stat 4 -->
                <div style="flex: 1 1 180px; max-width: 220px; background: #ffffff; padding: 32px 24px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;" onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 20px 25px -5px rgba(0, 0, 0, 0.1)'; this.style.borderColor='rgba(251, 191, 36, 0.5)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)'; this.style.borderColor='#e2e8f0';">
                    <div style="font-size: 32px; margin-bottom: 16px;">⚙️</div>
                    <div style="font-size: 48px; font-weight: 900; color: #0f172a; margin-bottom: 8px;">850</div>
                    <div style="font-size: 15px; color: #64748b; font-weight: 700; letter-spacing: 0.5px;">Fonctionnalités</div>
                </div>
                
                <!-- Stat 5 -->
                <div style="flex: 1 1 180px; max-width: 220px; background: #ffffff; padding: 32px 24px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;" onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 20px 25px -5px rgba(0, 0, 0, 0.1)'; this.style.borderColor='rgba(244, 63, 94, 0.5)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.05)'; this.style.borderColor='#e2e8f0';">
                    <div style="font-size: 32px; margin-bottom: 16px;">🔄</div>
                    <div style="font-size: 48px; font-weight: 900; color: #0f172a; margin-bottom: 8px;">12k+</div>
                    <div style="font-size: 15px; color: #64748b; font-weight: 700; letter-spacing: 0.5px;">Modifications</div>
                </div>

            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="hp-faq-block">
        <h2 class="hp-faq-title">Questions fréquentes</h2>
        
        <div class="hp-faq-item">
            <h3 class="hp-faq-question">Comment choisissez-vous les logiciels ?</h3>
            <div class="hp-faq-answer">Nous testons personnellement chaque outil selon une grille de 25 critères précis (Prix, ergonomie, connexion bancaire, support client). Seuls les meilleurs logiciels finissent dans nos classements.</div>
        </div>
        
        <div class="hp-faq-item">
            <h3 class="hp-faq-question">Les comparatifs sont-ils indépendants ?</h3>
            <div class="hp-faq-answer">Oui. Aucune marque ne peut payer pour être classée première. Nos notes reflètent notre avis objectif. Si un outil ne convient pas à un statut, nous l'indiquons clairement (ex: badge "Pas pour les Micro").</div>
        </div>

        <div class="hp-faq-item">
            <h3 class="hp-faq-question">Comment gagnez-vous de l'argent ?</h3>
            <div class="hp-faq-answer">BusinessKit se rémunère grâce à l'affiliation. Si vous créez un compte via l'un de nos liens, nous touchons une commission, sans que cela ne vous coûte un centime de plus. Cela nous permet de financer la création des outils gratuits.</div>
        </div>

        <div class="hp-faq-item">
            <h3 class="hp-faq-question">Les outils sont-ils vraiment gratuits ?</h3>
            <div class="hp-faq-answer">Absolument. Nos calculateurs de TVA, simulateurs URSSAF et générateurs de factures sont 100% gratuits, sans inscription requise.</div>
        </div>

        <div class="hp-faq-item">
            <h3 class="hp-faq-question">À qui s'adresse BusinessKit ?</h3>
            <div class="hp-faq-answer">À tous les indépendants français : micro-entrepreneurs, présidents de SASU, gérants d'EURL, professionnels libéraux, ou créateurs d'entreprise en devenir.</div>
        </div>
    </section>

    <!-- Newsletter Lead Magnet -->
    <section class="hp-newsletter" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: white; border-radius: 24px; padding: 40px; text-align: center; margin: 60px auto; max-width: 900px; box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.4);">
        <div style="font-size:40px; margin-bottom:12px;">🎁</div>
        <h2 style="font-size: 28px; margin-bottom: 12px; font-weight: 900; color: white; font-family: 'Manrope', sans-serif;">Téléchargez le Kit de Démarrage de l'Indépendant (100% Gratuit)</h2>
        <p style="font-size: 16px; color: #94a3b8; max-width: 700px; margin: 0 auto 24px; line-height: 1.6;">La checklist complète pour lancer votre activité sans faire d'erreurs avec l'URSSAF et les impôts. Inclus : le comparatif caché des comptes pro.</p>
        <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 24px; max-width: 600px; margin: 0 auto;">
            @livewire('frontend.newsletter-form')
            <p style="font-size: 13px; color: #64748b; margin-top: 12px; font-weight: 600;">Envoi immédiat par e-mail. 0 spam, promis.</p>
        </div>
    </section>

</main>

@endsection
