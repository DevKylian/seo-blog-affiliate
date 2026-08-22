    <!-- Super-Wizard (AlpineJS) V2 (Maintenant juste sous le Hero) -->
    <div class="hp-wizard" x-data="softwareWizardV2()" id="wizard" style="{{ isset($isArticle) && $isArticle ? 'margin-top: 20px; margin-bottom: 40px; border-top: none;' : '' }}">
        
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
                step: 1,
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
                    this.step = 1;
                    this.answers = {};
                }
            }))
        })
    </script>
