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

    <!-- Super-Wizard (AlpineJS) V2 (Maintenant juste sous le Hero) -->
    <div class="hp-wizard" x-data="softwareWizardV2()" id="wizard">
        
        <!-- Écran d'accueil du comparateur -->
        <div x-show="step === 0" style="text-align:center; padding: 12px 0;">
            <div style="display:inline-flex; align-items:center; gap:8px; padding:6px 14px; background:rgba(37, 99, 235, 0.08); color:#2563eb; border-radius:100px; font-size:13px; font-weight:800; margin-bottom:20px; border:1px solid rgba(37, 99, 235, 0.2); box-shadow: 0 2px 8px rgba(37,99,235,0.06);">
                <span style="display:inline-block; width:8px; height:8px; background:#2563eb; border-radius:50%; box-shadow:0 0 8px #2563eb;"></span>
                SIMULATEUR INTERACTIF GRATUIT
            </div>

            <h3 style="font-size:clamp(26px, 4vw, 34px); font-weight:900; color:#0f172a; margin-bottom:14px; font-family:'Manrope', sans-serif; letter-spacing:-0.5px; line-height:1.25;">
                Quel logiciel est <span style="background: linear-gradient(135deg, #2563eb, #4f46e5); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">fait pour vous ?</span>
            </h3>
            
            <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-bottom: 24px; font-weight: 800; font-size: 14px; color: var(--home-primary);">
                <span style="background: #f1f5f9; padding: 8px 16px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">▶ Freelance</span>
                <span style="background: #f1f5f9; padding: 8px 16px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">▶ Auto-entrepreneur</span>
                <span style="background: #f1f5f9; padding: 8px 16px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">▶ SASU</span>
                <span style="background: #f1f5f9; padding: 8px 16px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">▶ EURL</span>
            </div>

            <p style="color:var(--home-muted); font-size:16px; margin:0 auto 28px; max-width:560px; line-height:1.6; font-weight:500;">
                Répondez à 4 questions rapides et obtenez un <strong>résultat 100% personnalisé</strong>.
            </p>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:32px; text-align:left;">
                <div style="padding:14px 16px; background:#f8fafc; border:1px solid #f1f5f9; border-radius:14px; display:flex; align-items:center; gap:12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                    <span style="font-size:22px; background:white; width:44px; height:44px; display:flex; align-items:center; justify-content:center; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.04); flex-shrink:0;">⚡</span>
                    <div>
                        <div style="font-size:13px; font-weight:800; color:#0f172a;">4 questions</div>
                        <div style="font-size:12px; color:#64748b;">Moins de 30 sec</div>
                    </div>
                </div>
                <div style="padding:14px 16px; background:#f8fafc; border:1px solid #f1f5f9; border-radius:14px; display:flex; align-items:center; gap:12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                    <span style="font-size:22px; background:white; width:44px; height:44px; display:flex; align-items:center; justify-content:center; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.04); flex-shrink:0;">🎯</span>
                    <div>
                        <div style="font-size:13px; font-weight:800; color:#0f172a;">Sur-mesure</div>
                        <div style="font-size:12px; color:#64748b;">Selon votre statut</div>
                    </div>
                </div>
                <div style="padding:14px 16px; background:#f8fafc; border:1px solid #f1f5f9; border-radius:14px; display:flex; align-items:center; gap:12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                    <span style="font-size:22px; background:white; width:44px; height:44px; display:flex; align-items:center; justify-content:center; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,0.04); flex-shrink:0;">🛡️</span>
                    <div>
                        <div style="font-size:13px; font-weight:800; color:#0f172a;">100 % Objectif</div>
                        <div style="font-size:12px; color:#64748b;">Tests vérifiés</div>
                    </div>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; align-items:center; gap:12px;">
                <button @click="step = 1" style="background: linear-gradient(135deg, #2563eb, #4f46e5); color:white; font-size:17px; font-weight:800; padding:18px 42px; border:none; cursor:pointer; border-radius:14px; box-shadow:0 10px 25px -5px rgba(37,99,235,0.4); transition:all 0.2s cubic-bezier(0.4, 0, 0.2, 1); display:inline-flex; align-items:center; gap:10px;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 15px 30px -5px rgba(37,99,235,0.5)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 10px 25px -5px rgba(37,99,235,0.4)';">
                    <span>Trouver mon logiciel</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                </button>
                <span style="font-size:12px; font-weight:600; color:#64748b; display:inline-flex; align-items:center; gap:5px;">
                    <svg width="14" height="14" fill="#10b981" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></svg>
                    Instantané & sans inscription
                </span>
            </div>
        </div>

        <div class="hp-wizard-header" x-show="step > 0 && step < 5" style="display:none;">
            <h3 class="hp-wizard-title">Trouvez le logiciel idéal en 4 questions</h3>
            <div class="hp-wizard-progress">
                <div class="hp-wizard-step-dot" :class="{'active': step >= 1}"></div>
                <div class="hp-wizard-step-dot" :class="{'active': step >= 2}"></div>
                <div class="hp-wizard-step-dot" :class="{'active': step >= 3}"></div>
                <div class="hp-wizard-step-dot" :class="{'active': step >= 4}"></div>
            </div>
        </div>
        
        <!-- Step 1 -->
        <div x-show="step === 1" style="display: none;">
            <p class="hp-wizard-question">1. Quel est votre statut actuel ?</p>
            <div class="hp-wizard-options">
                <div class="hp-wizard-option" @click="answer(1, 'micro')">
                    <span style="font-size: 24px; margin-right: 8px;">💼</span>
                    Micro-entrepreneur
                </div>
                <div class="hp-wizard-option" @click="answer(1, 'societe')">
                    <span style="font-size: 24px; margin-right: 8px;">🏢</span>
                    Société (EURL, SASU...)
                </div>
                <div class="hp-wizard-option" @click="answer(1, 'ei')">
                    <span style="font-size: 24px; margin-right: 8px;">👤</span>
                    Entreprise Individuelle
                </div>
                <div class="hp-wizard-option" @click="answer(1, 'creation')">
                    <span style="font-size: 24px; margin-right: 8px;">🚀</span>
                    En cours de création
                </div>
            </div>
        </div>

        <!-- Step 2 -->
        <div x-show="step === 2" style="display: none;">
            <p class="hp-wizard-question">2. Quel est votre chiffre d'affaires (estimé) ?</p>
            <div class="hp-wizard-options">
                <div class="hp-wizard-option" @click="answer(2, 'petit')">
                    <span style="font-size: 24px; margin-right: 8px;">🪙</span>
                    Moins de 36 000 € (Pas de TVA)
                </div>
                <div class="hp-wizard-option" @click="answer(2, 'moyen')">
                    <span style="font-size: 24px; margin-right: 8px;">💵</span>
                    Entre 36 000 € et 75 000 €
                </div>
                <div class="hp-wizard-option" @click="answer(2, 'grand')">
                    <span style="font-size: 24px; margin-right: 8px;">📈</span>
                    Plus de 75 000 €
                </div>
                <div class="hp-wizard-option" @click="answer(2, 'nsp')">
                    <span style="font-size: 24px; margin-right: 8px;">❓</span>
                    Je ne sais pas encore
                </div>
            </div>
        </div>

        <!-- Step 3 -->
        <div x-show="step === 3" style="display: none;">
            <p class="hp-wizard-question">3. Vous faites votre comptabilité seul ?</p>
            <div class="hp-wizard-options">
                <div class="hp-wizard-option" @click="answer(3, 'seul')">
                    <span style="font-size: 24px; margin-right: 8px;">🧮</span>
                    Oui, 100% tout seul
                </div>
                <div class="hp-wizard-option" @click="answer(3, 'expert')">
                    <span style="font-size: 24px; margin-right: 8px;">🤝</span>
                    J'ai/Je veux un expert-comptable
                </div>
                <div class="hp-wizard-option" @click="answer(3, 'mix')">
                    <span style="font-size: 24px; margin-right: 8px;">🔄</span>
                    Outil partagé avec mon comptable
                </div>
            </div>
        </div>

        <!-- Step 4 -->
        <div x-show="step === 4" style="display: none;">
            <p class="hp-wizard-question">4. Vous cherchez surtout à...</p>
            <div class="hp-wizard-options">
                <div class="hp-wizard-option" @click="answer(4, 'temps')">
                    <span style="font-size: 24px; margin-right: 8px;">⏱️</span>
                    Gagner du temps (Auto max)
                </div>
                <div class="hp-wizard-option" @click="answer(4, 'argent')">
                    <span style="font-size: 24px; margin-right: 8px;">💰</span>
                    Économiser (Le moins cher)
                </div>
                <div class="hp-wizard-option" @click="answer(4, 'allinone')">
                    <span style="font-size: 24px; margin-right: 8px;">🏦</span>
                    Tout regrouper (Banque + Compta)
                </div>
                <div class="hp-wizard-option" @click="answer(4, 'conseil')">
                    <span style="font-size: 24px; margin-right: 8px;">💬</span>
                    Être accompagné (Support client)
                </div>
            </div>
        </div>

        <!-- Result -->
        <div x-show="step === 5" style="display: none;" class="hp-wizard-result">
            <div style="font-size: 48px; margin-bottom:16px;">🎯</div>
            
            <p style="color:var(--home-muted); font-size:12px; margin-bottom:4px; text-transform:uppercase; font-weight:800; letter-spacing:1px;">Votre profil :</p>
            <p style="font-size:20px; font-weight:800; color:var(--home-accent); margin-bottom:32px; font-family:'Manrope';" x-text="resultProfile"></p>

            <div style="background:white; border:1px solid rgba(37, 99, 235, 0.15); border-radius:20px; padding:32px; display:inline-block; text-align:left; min-width:320px; max-width:500px; box-shadow:0 20px 40px rgba(37,99,235,0.06);">
                <p style="color:var(--home-muted); font-size:11px; margin-bottom:8px; text-transform:uppercase; font-weight:800; letter-spacing:1px;">Notre recommandation :</p>
                <h3 class="hp-selection-title" style="margin-bottom:20px; font-size:28px;">🥇 <span x-text="resultTool"></span></h3>
                
                <p style="font-weight:800; color: #0f172a; font-size:15px; margin-bottom:14px; font-family:'Manrope';">Pourquoi ce choix ?</p>
                <ul style="list-style:none; padding:0; margin:0 0 28px 0; color:var(--home-text); font-size:15px; display:flex; flex-direction:column; gap:10px;">
                    <template x-for="reason in resultReasons">
                        <li style="display:flex; gap:10px; align-items:center;">
                            <span style="color:#10b981; display:flex; align-items:center;">
                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            <span x-text="reason" style="font-weight: 500;"></span>
                        </li>
                    </template>
                </ul>
                
                <div style="text-align:center;">
                    <a :href="resultUrl" target="_blank" rel="sponsored nofollow" class="hp-hero-cta" style="padding: 16px 32px; font-size:16px; display:inline-block; width:100%; text-align:center; border-radius:10px;">Voir <span x-text="resultTool"></span> gratuitement →</a>
                </div>
            </div>

            <p style="margin-top:24px;"><a href="#" @click.prevent="reset()" style="color:var(--home-muted); font-size:14px; font-weight:600; text-decoration:underline;">Refaire le test</a></p>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('softwareWizardV2', () => ({
                step: 0,
                answers: {},
                resultTool: '',
                resultProfile: '',
                resultReasons: [],
                resultUrl: '',
                
                answer(stepNum, val) {
                    this.answers[stepNum] = val;
                    if (stepNum < 4) {
                        this.step++;
                    } else {
                        this.calculateResult();
                    }
                },
                
                calculateResult() {
                    // Logique V2 (plus fine avec le nouveau format)
                    let statusStr = this.answers[1] === 'micro' ? 'Micro-entrepreneur' : (this.answers[1] === 'societe' ? 'Dirigeant de société' : 'Indépendant');
                    let needStr = this.answers[4] === 'argent' ? 'cherchant l\'économie' : 'cherchant à gagner du temps';
                    this.resultProfile = statusStr + ' ' + needStr;

                    if (this.answers[1] === 'micro' && this.answers[4] === 'argent') {
                        this.resultTool = 'Abby';
                        this.resultReasons = ['100% gratuit pour la facturation', 'Parfait pour les micro-entrepreneurs', 'Idéal pour limiter les frais de départ'];
                        this.resultUrl = '{{ route('affiliate.redirect', 'abby') }}';
                    } else if (this.answers[3] === 'expert' || this.answers[4] === 'conseil') {
                        this.resultTool = 'Dougs';
                        this.resultReasons = ['Accompagnement par de vrais comptables', 'Conseil illimité inclus', 'Plateforme ultra-intuitive'];
                        this.resultUrl = '{{ route('affiliate.redirect', 'dougs') }}';
                    } else if (this.answers[4] === 'allinone') {
                        this.resultTool = 'Pennylane';
                        this.resultReasons = ['Outil tout-en-un (Banque + Compta)', 'Connectable avec votre expert-comptable', 'Gestion financière puissante'];
                        this.resultUrl = '{{ route('affiliate.redirect', 'pennylane') }}';
                    } else {
                        this.resultTool = 'Indy';
                        this.resultReasons = ['Simple à prendre en main', 'Adapté aux indépendants seuls', 'Automatisation maximale (TVA, URSSAF)'];
                        this.resultUrl = '{{ route('affiliate.redirect', 'indy') }}';
                    }
                    this.step = 5;
                },
                
                reset() {
                    this.step = 0;
                    this.answers = {};
                }
            }))
        })
    </script>

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

    <!-- Hubs (Recherches fréquentes) -->
    <section class="home-section">
        <h2 class="home-section-title">Les logiciels les plus recherchés</h2>
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
    </section>

    <!-- DATA Section: Le marché en chiffres -->
    <section class="home-section" style="background: #0f172a; border-radius: 24px; padding: 60px 40px; margin: 40px 0; color: white; text-align: center; box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.4);">
        <h2 style="font-family: 'Manrope', sans-serif; font-size: 36px; font-weight: 800; margin-bottom: 16px; color: #ffffff;">Le marché en chiffres</h2>
        <p style="color: #94a3b8; font-size: 18px; max-width: 600px; margin: 0 auto 40px;">Nous suivons le marché en continu pour vous fournir les données les plus fiables.</p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 24px;">
            <div style="background: rgba(255,255,255,0.05); padding: 24px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 32px; font-weight: 900; color: #fff; margin-bottom: 8px;">312</div>
                <div style="font-size: 14px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Logiciels</div>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 24px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 32px; font-weight: 900; color: #fff; margin-bottom: 8px;">18</div>
                <div style="font-size: 14px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Catégories</div>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 24px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 32px; font-weight: 900; color: #fff; margin-bottom: 8px;">4 200</div>
                <div style="font-size: 14px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Tarifs analysés</div>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 24px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 32px; font-weight: 900; color: #fff; margin-bottom: 8px;">850</div>
                <div style="font-size: 14px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Fonctionnalités</div>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 24px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 32px; font-weight: 900; color: #38bdf8; margin-bottom: 8px;">12k+</div>
                <div style="font-size: 14px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Modifications</div>
            </div>
        </div>
    </section>

    <!-- Top Selection Section (avec Badges) -->
    <section class="home-section" id="selection">
        
        <h2 class="home-section-title">Notre sélection de logiciels</h2>
        
        <div class="home-softwares-list">
            
            <!-- Notre sélection 1: Indy -->
            <div class="hp-selection-card">
                <div class="hp-selection-badge">🏆 Notre meilleure recommandation pour les indépendants</div>
                <div class="hp-selection-content" x-data="{ showWhy: false }">
                    <div style="flex:1;">
                        <h3 class="hp-selection-title" style="margin-bottom:8px;">Indy</h3>
                        
                        <!-- Notes séparées et Date de MAJ -->
                        <div style="display:flex; flex-wrap:wrap; gap:32px; align-items:flex-start; margin-bottom:16px;">
                            <div>
                                <div style="color:var(--home-muted); font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:2px;">Note BusinessKit</div>
                                <div style="color:#eab308; font-size:14px; letter-spacing:1px; margin-bottom:2px; display: flex; gap: 2px;">
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                </div>
                                <div style="font-weight:800; font-size:14px;">4.9/5</div>
                            </div>
                            <div>
                                <div style="color:var(--home-muted); font-size:11px; font-weight:700; text-transform:uppercase; margin-bottom:2px;">Avis utilisateurs</div>
                                <div style="color:#eab308; font-size:14px; letter-spacing:1px; margin-bottom:2px; display: flex; gap: 2px;">
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                </div>
                                <div style="font-weight:800; font-size:14px;">4.7/5</div>
                            </div>
                            <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:4px; font-size:11px; color:var(--home-text); font-weight:600; min-width: 140px;">
                                <li><span style="color:#10b981; font-weight:800; font-size:12px;">✓</span> 25 critères analysés</li>
                                <li><span style="color:#10b981; font-weight:800; font-size:12px;">✓</span> Prix vérifiés</li>
                                <li><span style="color:#10b981; font-weight:800; font-size:12px;">✓</span> Mise à jour ce mois</li>
                            </ul>
                        </div>

                        <div style="font-size:16px; font-weight:800; color:var(--home-text); margin-bottom:12px;">💰 Dès 0€/mois</div>
                        
                        <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
                            <span class="hp-badge green">🟢 Débutants</span>
                            <span class="hp-badge green">🟢 Micro-entreprise</span>
                            <span class="hp-badge green">🟢 SASU</span>
                            <span class="hp-badge green">🟢 Automatisation</span>
                        </div>
                        <p style="color:var(--home-muted); font-size:15px; margin-bottom:16px;">Une alternative simple à la comptabilité traditionnelle pour freelances et petites entreprises.</p>
                        
                        <div style="margin-bottom:16px; padding:16px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0; display:grid; grid-template-columns:1fr 1fr; gap:16px; font-size:13px;">
                            <div>
                                <strong style="color:var(--home-text); display:block; margin-bottom:8px;">Idéal pour :</strong>
                                <ul style="list-style:none; padding:0; margin:0; display:grid; gap:6px; color:var(--home-muted);">
                                    <li style="display: flex; gap: 6px; align-items: center;"><span style="color:#10b981; display:flex; align-items:center;"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg></span> Freelances seuls</li>
                                    <li style="display: flex; gap: 6px; align-items: center;"><span style="color:#10b981; display:flex; align-items:center;"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg></span> Micro-entrepreneurs</li>
                                    <li style="display: flex; gap: 6px; align-items: center;"><span style="color:#10b981; display:flex; align-items:center;"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg></span> SASU simples</li>
                                </ul>
                            </div>
                            <div>
                                <strong style="color:var(--home-text); display:block; margin-bottom:8px;">Moins adapté pour :</strong>
                                <ul style="list-style:none; padding:0; margin:0; display:grid; gap:6px; color:var(--home-muted);">
                                    <li style="display: flex; gap: 6px; align-items: center;"><span style="color:#ef4444; display:flex; align-items:center;"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg></span> Gestion complexe</li>
                                    <li style="display: flex; gap: 6px; align-items: center;"><span style="color:#ef4444; display:flex; align-items:center;"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg></span> Cabinet physique</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="home-software-action" style="min-width: 200px; text-align:center;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <a href="{{ route('tools.show', 'indy') }}" class="home-software-btn" style="flex:1;">Voir notre avis complet →</a>
                            <span style="font-size:12px; color:var(--home-muted); font-weight:700; margin-left:12px; white-space:nowrap;">⏱ 12 min</span>
                        </div>
                        <a href="{{ route('affiliate.redirect', 'indy') }}" target="_blank" rel="sponsored nofollow" class="home-software-btn" style="width:100%; background:var(--home-accent); color:white; border-color:var(--home-accent);">Créer mon compte gratuitement</a>
                        
                        <div style="margin-top:12px; text-align:left;">
                            <a href="#" @click.prevent="showWhy = !showWhy" style="font-size:13px; color:var(--home-primary-light); text-decoration:none; font-weight:700; display:flex; align-items:center; gap:4px; justify-content:center;">
                                Pourquoi cette recommandation ?
                                <svg x-show="!showWhy" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                <svg x-show="showWhy" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="display:none;"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
                            </a>
                            <div x-show="showWhy" style="display:none; margin-top:12px; padding:12px; background:var(--home-surface); border:1px solid var(--home-border); border-radius:8px; font-size:12px; color:var(--home-text);">
                                <strong style="display:block; margin-bottom:8px;">Nous recommandons Indy car :</strong>
                                <ul style="list-style:none; padding:0; margin:0; display:grid; gap:6px; color:var(--home-muted);">
                                    <li style="display: flex; gap: 6px; align-items: center;"><span style="color:#10b981; display:flex; align-items:center;"><svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg></span> Meilleure prise en main</li>
                                    <li style="display: flex; gap: 6px; align-items: center;"><span style="color:#10b981; display:flex; align-items:center;"><svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg></span> Excellent rapport fonctionnalités/prix</li>
                                    <li style="display: flex; gap: 6px; align-items: center;"><span style="color:#10b981; display:flex; align-items:center;"><svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg></span> Automatisation forte</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Pennylane -->
            <div class="home-software-card">
                <div class="home-software-rank">2</div>
                <div class="home-software-info">
                    <h3 style="margin-bottom:8px;">Pennylane</h3>
                    <div style="display:flex; flex-wrap:wrap; gap:24px; align-items:flex-start; margin-bottom:16px;">
                        <div>
                            <div style="color:var(--home-muted); font-size:10px; font-weight:700; text-transform:uppercase; margin-bottom:2px;">Note BusinessKit</div>
                            <div style="font-weight:800; font-size:12px; color: var(--home-primary);">4.7/5</div>
                        </div>
                        <div>
                            <div style="color:var(--home-muted); font-size:10px; font-weight:700; text-transform:uppercase; margin-bottom:2px;">Avis utilisateurs</div>
                            <div style="font-weight:800; font-size:12px; color: var(--home-primary);">4.5/5</div>
                        </div>
                        <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:4px; font-size:11px; color:var(--home-text); font-weight:600; min-width: 140px;">
                            <li><span style="color:#10b981; font-weight:800; font-size:12px;">✓</span> 25 critères analysés</li>
                            <li><span style="color:#10b981; font-weight:800; font-size:12px;">✓</span> Prix vérifiés</li>
                            <li><span style="color:#10b981; font-weight:800; font-size:12px;">✓</span> Mise à jour ce mois</li>
                        </ul>
                    </div>
                    
                    <div style="font-size:14px; font-weight:800; color:var(--home-text); margin-bottom:8px;">💰 Dès 14€/mois</div>
                    <div style="margin-bottom:8px; display:flex; gap:8px; flex-wrap:wrap;">
                        <span class="hp-badge yellow">🟡 PME</span>
                        <span class="hp-badge green">🟢 Banque + Compta</span>
                    </div>
                    <div class="home-software-advantages">
                        <span>Outil tout-en-un</span>
                        <span>Collaboration expert</span>
                    </div>
                </div>
                <div class="home-software-action" style="flex-direction:row; align-items:center; flex-wrap:wrap; gap:8px;">
                    <a href="{{ route('tools.show', 'pennylane') }}" class="home-software-btn" style="border:none;">Lire le test</a>
                    <span style="font-size:12px; color:var(--home-muted); font-weight:700;">⏱ 8 min</span>
                    <a href="{{ route('affiliate.redirect', 'pennylane') }}" target="_blank" rel="sponsored nofollow" class="home-software-btn" style="color:var(--home-accent); border-color:var(--home-accent); margin-left:auto;">Comparer</a>
                </div>
            </div>

            <!-- 3. Dougs -->
            <div class="home-software-card">
                <div class="home-software-rank">3</div>
                <div class="home-software-info">
                    <h3 style="margin-bottom:8px;">Dougs</h3>
                    <div style="display:flex; flex-wrap:wrap; gap:24px; align-items:flex-start; margin-bottom:16px;">
                        <div>
                            <div style="color:var(--home-muted); font-size:10px; font-weight:700; text-transform:uppercase; margin-bottom:2px;">Note BusinessKit</div>
                            <div style="font-weight:800; font-size:12px; color: var(--home-primary);">4.6/5</div>
                        </div>
                        <div>
                            <div style="color:var(--home-muted); font-size:10px; font-weight:700; text-transform:uppercase; margin-bottom:2px;">Avis utilisateurs</div>
                            <div style="font-weight:800; font-size:12px; color: var(--home-primary);">4.7/5</div>
                        </div>
                        <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:4px; font-size:11px; color:var(--home-text); font-weight:600; min-width: 140px;">
                            <li><span style="color:#10b981; font-weight:800; font-size:12px;">✓</span> 25 critères analysés</li>
                            <li><span style="color:#10b981; font-weight:800; font-size:12px;">✓</span> Prix vérifiés</li>
                            <li><span style="color:#10b981; font-weight:800; font-size:12px;">✓</span> Mise à jour ce mois</li>
                        </ul>
                    </div>

                    <div style="font-size:14px; font-weight:800; color:var(--home-text); margin-bottom:8px;">💰 Dès 49€/mois</div>
                    <div style="margin-bottom:8px; display:flex; gap:8px; flex-wrap:wrap;">
                        <span class="hp-badge green">🟢 Avec Comptable</span>
                        <span class="hp-badge red">🔴 Pas pour les Micro</span>
                    </div>
                    <div class="home-software-advantages">
                        <span>Expert-comptable en ligne</span>
                        <span>Conseil illimité</span>
                    </div>
                </div>
                <div class="home-software-action" style="flex-direction:row; align-items:center; flex-wrap:wrap; gap:8px;">
                    <a href="{{ route('tools.show', 'dougs') }}" class="home-software-btn" style="border:none;">Lire le test</a>
                    <span style="font-size:12px; color:var(--home-muted); font-weight:700;">⏱ 7 min</span>
                    <a href="{{ route('affiliate.redirect', 'dougs') }}" target="_blank" rel="sponsored nofollow" class="home-software-btn" style="color:var(--home-accent); border-color:var(--home-accent); margin-left:auto;">Comparer</a>
                </div>
            </div>
            
            <!-- 4. Abby -->
            <div class="home-software-card">
                <div class="home-software-rank">4</div>
                <div class="home-software-info">
                    <h3 style="margin-bottom:8px;">Abby</h3>
                    <div style="display:flex; flex-wrap:wrap; gap:24px; align-items:flex-start; margin-bottom:16px;">
                        <div>
                            <div style="color:var(--home-muted); font-size:10px; font-weight:700; text-transform:uppercase; margin-bottom:2px;">Note BusinessKit</div>
                            <div style="font-weight:800; font-size:12px; color: var(--home-primary);">4.5/5</div>
                        </div>
                        <div>
                            <div style="color:var(--home-muted); font-size:10px; font-weight:700; text-transform:uppercase; margin-bottom:2px;">Avis utilisateurs</div>
                            <div style="font-weight:800; font-size:12px; color: var(--home-primary);">4.4/5</div>
                        </div>
                        <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:4px; font-size:11px; color:var(--home-text); font-weight:600; min-width: 140px;">
                            <li><span style="color:#10b981; font-weight:800; font-size:12px;">✓</span> 25 critères analysés</li>
                            <li><span style="color:#10b981; font-weight:800; font-size:12px;">✓</span> Prix vérifiés</li>
                            <li><span style="color:#10b981; font-weight:800; font-size:12px;">✓</span> Mise à jour ce mois</li>
                        </ul>
                    </div>

                    <div style="font-size:14px; font-weight:800; color:var(--home-text); margin-bottom:8px;">💰 Dès 0€/mois</div>
                    <div style="margin-bottom:8px; display:flex; gap:8px; flex-wrap:wrap;">
                        <span class="hp-badge green">🟢 Micro-entreprise</span>
                        <span class="hp-badge red">🔴 Pas de paie</span>
                    </div>
                    <div class="home-software-advantages">
                        <span>Facturation gratuite</span>
                        <span>Gestion de projets</span>
                    </div>
                </div>
                <div class="home-software-action" style="flex-direction:row; align-items:center; flex-wrap:wrap; gap:8px;">
                    <a href="{{ route('tools.show', 'abby') }}" class="home-software-btn" style="border:none;">Lire le test</a>
                    <span style="font-size:12px; color:var(--home-muted); font-weight:700;">⏱ 5 min</span>
                    <a href="{{ route('affiliate.redirect', 'abby') }}" target="_blank" rel="sponsored nofollow" class="home-software-btn" style="color:var(--home-accent); border-color:var(--home-accent); margin-left:auto;">Comparer</a>
                </div>
            </div>
        </div>
    </section>

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

    <!-- Comparatifs les plus lus -->
    <section class="home-section">
        <h2 class="home-section-title">Les comparatifs les plus lus</h2>
                <div class="hp-grid-3">
            @foreach($latestArticles->where('type', 'comparison')->take(3) as $article)
            <a href="{{ $article->public_url }}" class="hp-card">
                <div class="hp-card-title">{{ $article->title }}</div>
                <div class="hp-card-desc">{{ Str::limit($article->excerpt ?? 'Découvrez notre comparatif complet.', 80) }}</div>
            </a>
            @endforeach
            @if($latestArticles->where('type', 'comparison')->count() == 0)
                @foreach($latestArticles->take(3) as $article)
                <a href="{{ $article->public_url }}" class="hp-card">
                    <div class="hp-card-title">{{ $article->title }}</div>
                    <div class="hp-card-desc">{{ Str::limit($article->excerpt ?? 'Découvrez notre analyse détaillée.', 80) }}</div>
                </a>
                @endforeach
            @endif
        </div>
    </section>

    <!-- Encart Auteur (SEO EEAT) -->
    <section class="hp-author-block">
        <div class="hp-author-avatar">👋</div>
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

    <!-- Newsletter -->
    <section class="hp-newsletter">
        <div style="font-size:48px; margin-bottom:16px;">📬</div>
        <h2 style="font-size: 32px; margin-bottom: 16px; font-weight: 800;">Évitez les 3 erreurs qui coûtent le plus cher aux freelances.</h2>
        <p style="font-size: 18px; color: var(--home-muted);">Recevez nos outils et conseils. 1 e-mail par semaine, 0 spam.</p>
        <form action="#" class="hp-newsletter-form">
            <input type="email" class="hp-newsletter-input" placeholder="votre@email.com" required>
            <button type="submit" class="hp-newsletter-btn">M'inscrire gratuitement</button>
        </form>
    </section>

</main>

@endsection
