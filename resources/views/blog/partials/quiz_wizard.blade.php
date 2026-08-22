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
        <div x-show="step === 5" style="display: none; text-align: center;" class="hp-wizard-result">
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 20px; padding: 40px 24px; display: inline-block; text-align: center; width: 100%; max-width: 600px; box-shadow: 0 10px 25px rgba(37,99,235,0.05);">
                
                <!-- Badge "Notre recommandation" -->
                <div style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; color: #2563eb; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 24px;">
                    <span style="display: inline-block; width: 6px; height: 6px; background: #2563eb; border-radius: 50%;"></span>
                    NOTRE RECOMMANDATION
                </div>

                <!-- Tool Icon -->
                <div style="margin: 0 auto 20px; width: 64px; height: 64px; background: white; border: 1px solid #e2e8f0; border-radius: 16px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <img :src="`https://www.google.com/s2/favicons?domain=${resultDomain}&sz=128`" :alt="resultTool" style="width: 70%; height: 70%; object-fit: contain; border-radius: 4px;">
                </div>

                <!-- Title -->
                <h3 style="font-size: 32px; font-weight: 900; color: #0f172a; margin: 0 0 12px; font-family: 'Manrope', sans-serif;" x-text="resultTool"></h3>
                
                <!-- Subtitle -->
                <p style="font-size: 15px; color: #475569; margin: 0 auto 28px; line-height: 1.5; font-weight: 500; max-width: 400px;" x-text="resultSubtitle"></p>

                <!-- Pills / Features -->
                <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-bottom: 32px;">
                    <template x-for="reason in resultReasons">
                        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 100px; font-size: 13px; font-weight: 600; color: #334155;">
                            <svg width="14" height="14" viewBox="0 0 20 20" fill="#2563eb"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                            <span x-text="reason"></span>
                        </span>
                    </template>
                </div>

                <!-- CTA Button -->
                <a :href="resultUrl" target="_blank" rel="sponsored nofollow" style="display: block; width: 100%; text-align: center; background: #005fef; color: white; font-size: 16px; font-weight: 800; padding: 18px 24px; border-radius: 8px; text-decoration: none; transition: all 0.2s; box-shadow: 0 4px 12px rgba(37,99,235,0.2);" onmouseover="this.style.backgroundColor='#004bbf';" onmouseout="this.style.backgroundColor='#005fef';">
                    Essayer <span x-text="resultTool"></span> gratuitement →
                </a>

                <!-- Trust Stars -->
                <div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 16px; font-size: 13px; font-weight: 600; color: #64748b;">
                    <div style="display: flex; color: #facc15;">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                    </div>
                    <span>4,8/5 · 500+ clients · Sans carte requise</span>
                </div>
            </div>

            <!-- Reset Quiz -->
            <div style="margin-top: 24px;">
                <a href="#" @click.prevent="reset()" style="display: inline-flex; align-items: center; gap: 8px; color: #94a3b8; font-size: 14px; font-weight: 600; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='#64748b'" onmouseout="this.style.color='#94a3b8'">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Recommencer le quiz
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('softwareWizardV2', () => ({
                step: 1,
                answers: {},
                resultTool: '',
                resultSubtitle: '',
                resultDomain: '',
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
                        this.resultDomain = 'abby.fr';
                        this.resultSubtitle = 'L\'alternative 100% gratuite pour gérer votre facturation sans limites.';
                        this.resultReasons = ['100% gratuit pour la facturation', 'Parfait pour les micro-entrepreneurs', 'Idéal pour limiter les frais de départ'];
                        this.resultUrl = '{{ route('affiliate.redirect', 'abby') }}';
                    } else if (this.answers[3] === 'expert' || this.answers[4] === 'conseil') {
                        this.resultTool = 'Dougs';
                        this.resultDomain = 'dougs.fr';
                        this.resultSubtitle = 'L\'expert-comptable en ligne avec un vrai conseil et une app intuitive.';
                        this.resultReasons = ['Accompagnement par de vrais comptables', 'Conseil illimité inclus', 'Plateforme ultra-intuitive'];
                        this.resultUrl = '{{ route('affiliate.redirect', 'dougs') }}';
                    } else if (this.answers[4] === 'allinone') {
                        this.resultTool = 'Pennylane';
                        this.resultDomain = 'pennylane.com';
                        this.resultSubtitle = 'La solution tout-en-un réunissant compte pro et comptabilité.';
                        this.resultReasons = ['Outil tout-en-un (Banque + Compta)', 'Connectable avec votre expert-comptable', 'Gestion financière puissante'];
                        this.resultUrl = '{{ route('affiliate.redirect', 'pennylane') }}';
                    } else {
                        this.resultTool = 'Indy';
                        this.resultDomain = 'indy.fr';
                        this.resultSubtitle = 'L\'outil préféré des indépendants pour tout automatiser de A à Z.';
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
