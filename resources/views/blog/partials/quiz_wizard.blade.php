    <!-- Super-Wizard (AlpineJS) V2 (Maintenant juste sous le Hero) -->
    <div class="hp-wizard" x-data="softwareWizardV2()" id="wizard" style="{{ isset($isArticle) && $isArticle ? 'margin-top: 20px; margin-bottom: 40px; border-top: none;' : '' }}">
        
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
                    <span style="font-size: 24px; margin-right: 8px;">💶</span>
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
