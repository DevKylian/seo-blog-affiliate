<div class="dashboard-page seo-engine-page">
    <div class="page-heading">
        <div>
            <h1>SEO Authority Engine</h1>
            <p>Orchestration IA et stratégie de contenu centralisée.</p>
        </div>
    </div>

    <div class="engine-layout">
        <!-- Main Content Area -->
        <div class="engine-main">
            <!-- Pipeline Steps (Visual Indicator) -->
            <div class="engine-stepper">
                @foreach($steps as $index => $name)
                    <div class="step-item {{ $currentStep == $index ? 'active' : ($currentStep > $index ? 'completed' : 'pending') }}">
                        <button wire:click="goToStep({{ $index }})" class="step-btn">
                            {{ $index }}. {{ $name }}
                        </button>
                        @if(!$loop->last)
                            <span class="step-separator">›</span>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Dynamic Step Content -->
            <div class="panel engine-content">
                                @if($currentStep == 1)
                    <div class="panel-head" style="border:none; padding-bottom: 0;">
                        <h2>Étape 1 : Projet & Produit</h2>
                        <p>Sélectionnez un projet existant ou créez-en un nouveau depuis l'onglet Projets.</p>
                    </div>
                    <div style="padding: 20px;">
                        <div class="field" style="max-width: 400px; margin-bottom: 20px;">
                            <label>Projet actif</label>
                            <select wire:model.live="projectId" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--line);">
                                <option value="">-- Sélectionner un projet --</option>
                                @foreach($projects as $proj)
                                    <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($projectId)
                            <button wire:click="goToNextStep" class="primary-button">Confirmer et continuer</button>
                        @endif
                    </div>
                @elseif($currentStep == 2)
                    <div class="panel-head" style="border:none; padding-bottom: 0;">
                        <h2>Étape 2 : Business Understanding</h2>
                        <p>Gemini va extraire le Product Profile (ICP, USP, Pain points) pour construire la stratégie.</p>
                    </div>
                    <div style="padding: 20px;">
                        @if($marketAnalysis)
                            <div class="alert success" style="margin-bottom: 20px;">✓ Analyse de produit existante (Version {{ $marketAnalysis->version }}).</div>
                            <details style="margin-bottom: 20px; background: var(--bg); border: 1px solid var(--line); border-radius: 8px; padding: 10px;">
                                <summary style="cursor: pointer; font-weight: bold; color: var(--ink);">Voir le Product Profile JSON</summary>
                                <pre style="font-size: 10px; padding: 10px; overflow-x: auto; color: var(--muted);">{{ json_encode($marketAnalysis->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                            
                            <div style="display: flex; gap: 10px;">
                                <button wire:click="goToNextStep" class="primary-button">Valider le profil et continuer</button>
                                <button wire:click="analyzeMarket" class="secondary-button" style="display: flex; align-items: center; gap: 8px;">
                                    <span wire:loading.remove wire:target="analyzeMarket">🔄 Regénérer</span>
                                    <span wire:loading wire:target="analyzeMarket">Analyse en cours...</span>
                                </button>
                            </div>
                        @else
                            @error('market_analysis') <div class="alert danger">{{ $message }}</div> @enderror
                            <p style="margin-bottom: 20px; color: var(--muted); font-size: 11px;">
                                Cette étape fera appel à Gemini 2.5 Flash pour comprendre en profondeur votre produit et votre cible.
                            </p>
                            <button wire:click="analyzeMarket" class="primary-button" style="display: flex; align-items: center; gap: 10px;">
                                <span wire:loading.remove wire:target="analyzeMarket">Lancer l'analyse du produit</span>
                                <span wire:loading wire:target="analyzeMarket">Analyse en cours (≈ 15s)...</span>
                            </button>
                        @endif
                    </div>
                @elseif($currentStep == 3)
                    <div class="panel-head" style="border:none; padding-bottom: 0;">
                        <h2>Étape 3 : Analyse de la Concurrence</h2>
                        <p>Identification des concurrents directs et des opportunités de comparaison.</p>
                    </div>
                    <div style="padding: 20px;">
                        @if($marketAnalysis && isset($marketAnalysis->data["competitors"]))
                            <div class="alert success" style="margin-bottom: 20px;">✓ Paysage concurrentiel identifié.</div>
                            <div style="display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); margin-bottom: 20px;">
                                @foreach($marketAnalysis->data["competitors"] ?? [] as $competitor)
                                    <div style="padding: 15px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface);">
                                        <strong>{{ is_string($competitor) ? $competitor : ($competitor["name"] ?? "Concurrent") }}</strong>
                                    </div>
                                @endforeach
                            </div>
                            <button wire:click="goToNextStep" class="primary-button">Continuer vers l'Intent Analysis</button>
                        @else
                            <div class="empty-state">L'analyse du produit (Étape 2) doit être complétée d'abord.</div>
                        @endif
                    </div>
                @elseif($currentStep == 4)
                    <div class="panel-head" style="border:none; padding-bottom: 0;">
                        <h2>Étape 4 : Intentions de Recherche</h2>
                        <p>Importez vos mots-clés Semrush. Le Smart Scorer va évaluer leur valeur business.</p>
                    </div>
                    <div style="padding: 20px; margin: -20px;">
                        @livewire("keywords", ["projectId" => $projectId])
                    </div>
                    <div style="padding: 20px; border-top: 1px solid var(--line); display: flex; justify-content: flex-end;">
                         <button wire:click="goToNextStep" class="primary-button">J'ai importé mes mots-clés, passer aux clusters</button>
                    </div>
                @elseif($currentStep == 5)
                    <div class="panel-head" style="border:none; padding-bottom: 0;">
                        <h2>Étape 5 : Topic Clusters</h2>
                        <p>Regroupement intelligent des mots-clés en silos thématiques.</p>
                    </div>
                    <div style="padding: 20px;">
                        @if(count($clusters) > 0)
                            <div class="alert success" style="margin-bottom: 20px;">✓ {{ count($clusters) }} clusters sémantiques identifiés.</div>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px;">
                                @foreach($clusters->take(8) as $cluster)
                                    <div class="stat-card" style="padding: 12px; border: 1px solid var(--line); border-radius: 8px;">
                                        <div style="font-size: 10px; color: var(--muted); text-transform: uppercase; font-weight: bold; margin-bottom: 4px;">{{ $cluster->type === "pillar" ? "Pilier" : ($cluster->type === "niche" ? "Niche" : "Support") }}</div>
                                        <div style="font-weight: 600; font-size: 13px; color: var(--ink); margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $cluster->name }}">{{ $cluster->name }}</div>
                                        <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--muted);">
                                            <span>{{ $cluster->keywords_count }} mot(s)</span>
                                            <span>Vol: {{ number_format($cluster->total_search_volume, 0, ",", " ") }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            
                            <div style="display: flex; gap: 10px;">
                                <button wire:click="goToNextStep" class="primary-button">Valider l'architecture et continuer</button>
                                <button wire:click="clusterKeywords" class="secondary-button" style="display: flex; align-items: center; gap: 8px;">
                                    <span wire:loading.remove wire:target="clusterKeywords">🔄 Re-calculer les clusters</span>
                                    <span wire:loading wire:target="clusterKeywords">Analyse en cours...</span>
                                </button>
                            </div>
                        @else
                            @error("clustering") <div class="alert danger">{{ $message }}</div> @enderror
                            <button wire:click="clusterKeywords" class="primary-button" style="display: inline-flex; align-items: center; gap: 10px;">
                                <span wire:loading.remove wire:target="clusterKeywords">Lancer la topologie des clusters</span>
                                <span wire:loading wire:target="clusterKeywords">Analyse en cours...</span>
                            </button>
                        @endif
                    </div>
                @elseif($currentStep == 6)
                    <div class="panel-head" style="border:none; padding-bottom: 0;">
                        <h2>Étape 6 : Feuille de Route Éditoriale</h2>
                        <p>Définissez le nombre d'articles. L'IA sélectionnera les meilleurs mots-clés selon le roadmap_level.</p>
                    </div>
                    <div style="padding: 20px;">
                        @error("planification") <div class="alert danger" style="margin-bottom: 20px;">{{ $message }}</div> @enderror
                        
                        <div style="background: var(--bg); border: 1px solid var(--line); border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                            <div class="field" style="margin-bottom: 16px;">
                                <label>Nombre de contenus à planifier</label>
                                <input type="number" wire:model="contentCount" min="1" max="50" style="width: 100%; max-width: 150px; padding: 10px; border-radius: 8px; border: 1px solid var(--line);">
                            </div>
                            <div class="field" style="margin-bottom: 0;">
                                <label>Instructions éditoriales spécifiques (Optionnel)</label>
                                <textarea wire:model="instructions" rows="3" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--line);"></textarea>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button wire:click="startStrategyPlan" class="primary-button" style="display: flex; align-items: center; gap: 10px;">
                                <span wire:loading.remove wire:target="startStrategyPlan">Générer la Roadmap</span>
                                <span wire:loading wire:target="startStrategyPlan">Génération en cours...</span>
                            </button>
                            @if($plan)
                                <button wire:click="goToNextStep" class="secondary-button">Voir la Roadmap existante</button>
                            @endif
                        </div>
                    </div>
                @elseif($currentStep == 7)
                    <div class="panel-head" style="border:none; padding-bottom: 0;">
                        <h2>Étape 7 : Briefs SEO (Ultra-Rich)</h2>
                        <p>L'IA a généré des briefs profonds (LSI, PAA, Tone, CTA) basés sur votre Product Profile.</p>
                    </div>
                    <div style="padding: 20px;">
                        @if($plan)
                            <div class="alert info" style="margin-bottom: 20px;">
                                <strong>Plan {{ $plan->id }}</strong> : {{ $plan->accepted_count }} briefs validés sur {{ $plan->requested_count }} demandés.
                            </div>
                            
                            <div style="display: grid; gap: 15px;">
                                @foreach($plan->ideas as $idea)
                                    <details style="border: 1px solid var(--line); border-radius: 8px; background: var(--surface);">
                                        <summary style="padding: 15px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <span class="state-badge {{ $idea->status }}" style="margin-right: 10px;">{{ $idea->roadmap_level ?? "N/A" }}</span>
                                                <strong style="font-size: 15px;">{{ $idea->title }}</strong>
                                            </div>
                                            <span style="font-size: 12px; color: var(--muted);">KD: {{ $idea->keyword?->keyword_difficulty ?? "—" }}</span>
                                        </summary>
                                        <div style="padding: 15px; border-top: 1px solid var(--line); font-size: 13px;">
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                                <div>
                                                    <p><strong>Mot-clé:</strong> {{ $idea->keyword?->keyword }}</p>
                                                    <p><strong>Intention:</strong> {{ $idea->intent }}</p>
                                                    <p><strong>Promesse:</strong> {{ $idea->unique_promise }}</p>
                                                    @if(isset($idea->brief_details["tone_of_voice"]))
                                                        <p><strong>Ton:</strong> {{ $idea->brief_details["tone_of_voice"] }}</p>
                                                    @endif
                                                </div>
                                                <div>
                                                    @if(isset($idea->brief_details["call_to_action"]))
                                                        <p><strong>CTA:</strong> {{ $idea->brief_details["call_to_action"] }}</p>
                                                    @endif
                                                    @if(isset($idea->brief_details["lsi_keywords"]) && is_array($idea->brief_details["lsi_keywords"]))
                                                        <p><strong>LSI:</strong> {{ implode(", ", $idea->brief_details["lsi_keywords"]) }}</p>
                                                    @endif
                                                    @if(isset($idea->brief_details["paa_questions"]) && is_array($idea->brief_details["paa_questions"]))
                                                        <p><strong>PAA:</strong> {{ implode(" / ", $idea->brief_details["paa_questions"]) }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                            <div style="margin-top: 15px;">
                                                <strong>Plan H2:</strong>
                                                <ul style="margin: 5px 0 0; padding-left: 20px;">
                                                    @foreach($idea->outline ?? [] as $h2)
                                                        <li>{{ $h2 }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                            <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
                                <button wire:click="goToNextStep" class="primary-button">Lancer la Production</button>
                            </div>
                        @else
                            <div class="empty-state">Veuillez d'abord générer la roadmap (Étape 6).</div>
                        @endif
                    </div>
                @elseif($currentStep == 8)
                    <div class="panel-head" style="border:none; padding-bottom: 0;">
                        <h2>Étape 8 : Production de Contenu</h2>
                        <p>Génération automatisée des articles via Gemini Flash-Lite.</p>
                    </div>
                    <div style="padding: 20px;">
                        @error("run") <div class="alert danger" style="margin-bottom: 20px;">{{ $message }}</div> @enderror
                        
                        @if($run)
                            <div class="alert info" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;"
                                 @if(in_array($run->status, ["pending", "processing"])) wire:poll.3s="processRunStep" @endif>
                                @if(in_array($run->status, ["pending", "processing"])) <span>⚙️</span> @else <span>✓</span> @endif
                                <div>
                                    <strong>{{ in_array($run->status, ["pending", "processing"]) ? "Génération en cours..." : "Génération terminée !" }}</strong>
                                    <div style="font-size: 12px; margin-top: 4px;">{{ $run->completed_count }} sur {{ $run->requested_count }} articles terminés. ({{ $run->progress_percentage }}%)</div>
                                </div>
                            </div>

                            <div style="display: grid; gap: 12px;">
                                @foreach($run->items as $item)
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid var(--line); border-radius: 8px; background: {{ $item->status === "completed" ? "#f0fdf4" : "white" }};">
                                        <div>
                                            <strong style="color: var(--ink); display: block; font-size: 14px;">{{ $item->editorialIdea?->title ?? $item->keyword?->keyword }}</strong>
                                            <div style="font-size: 11px; color: var(--muted); margin-top: 4px;">
                                                Statut : <span style="font-weight: bold; color: {{ $item->status === "completed" ? "#22805f" : "#6655e9" }}">{{ $item->status }}</span>
                                                @if($item->error_message) <span style="color: #dc3545;">- {{ Str::limit($item->error_message, 100) }}</span> @endif
                                            </div>
                                        </div>
                                        @if($item->article)
                                            <a href="{{ route("admin.articles.edit", $item->article) }}" target="_blank" class="secondary-button" style="padding: 6px 12px; font-size: 11px;">Voir l'article</a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            
                            @if(!in_array($run->status, ["pending", "processing"]))
                                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 15px; margin-top: 20px;">
                                    @if($run->items->where("status", "failed")->count() > 0)
                                        <button wire:click="retryFailedRun" class="secondary-button" style="display: flex; align-items: center; gap: 8px;">
                                            <span wire:loading.remove wire:target="retryFailedRun">🔄 Réessayer les erreurs</span>
                                            <span wire:loading wire:target="retryFailedRun">Relance...</span>
                                        </button>
                                    @endif
                                    <button wire:click="goToNextStep" class="primary-button">Passer à la Publication</button>
                                </div>
                            @endif
                        @else
                            <div class="empty-state" style="padding: 40px 20px; background: var(--bg); border: 1px dashed var(--line); border-radius: 8px; text-align: center;">
                                <p style="margin-bottom: 20px; color: var(--muted); font-size: 13px;">
                                    Le plan contient {{ $plan ? $plan->accepted_count : 0 }} articles prêts à être générés.
                                </p>
                                <button wire:click="launchRun" class="primary-button" style="display: inline-flex; align-items: center; gap: 10px;">
                                    <span wire:loading.remove wire:target="launchRun">Lancer la rédaction IA</span>
                                    <span wire:loading wire:target="launchRun">Démarrage...</span>
                                </button>
                            </div>
                        @endif
                    </div>
                @elseif($currentStep == 9)
                    <div class="panel-head" style="border:none; padding-bottom: 0;">
                        <h2>Étape 9 : Publication & Actifs</h2>
                        <p>Planifiez et publiez vos contenus sur le portail public.</p>
                    </div>
                    <div style="padding: 20px;">
                        <div style="border: 1px solid var(--line); border-radius: 8px; background: var(--surface); padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <div>
                                    <h3 style="margin: 0 0 4px;">Campagne prête pour la publication</h3>
                                    <p style="margin: 0; color: var(--muted); font-size: 13px;">{{ $run ? $run->completed_count : 0 }} articles générés avec succès.</p>
                                </div>
                                <div>
                                    <span class="state-badge completed" style="font-size: 13px; padding: 4px 12px;">Pre-Publish Audit : OK</span>
                                </div>
                            </div>
                            
                            <div style="display: grid; gap: 10px; border-top: 1px solid var(--line); padding-top: 20px;">
                                @if (session()->has("message"))
                                    <div style="color: var(--success); text-align: center; margin-bottom: 10px; font-weight: 500;">
                                        {{ session("message") }}
                                    </div>
                                @endif
                                
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px;">
                                    <div>
                                        <label style="display: block; font-size: 13px; color: var(--muted); margin-bottom: 4px;">Fréquence</label>
                                        <select wire:model="scheduleFrequency" style="width: 100%; padding: 8px; border: 1px solid var(--line); border-radius: 4px; background: var(--bg);">
                                            <option value="now">Immédiatement</option>
                                            <option value="daily">Par jour</option>
                                            <option value="weekly">Par semaine</option>
                                            <option value="monthly">Par mois</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 13px; color: var(--muted); margin-bottom: 4px;">Articles par intervalle</label>
                                        <input type="number" wire:model="schedulePostsPerInterval" min="1" style="width: 100%; padding: 8px; border: 1px solid var(--line); border-radius: 4px; background: var(--bg);">
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 13px; color: var(--muted); margin-bottom: 4px;">Date de début</label>
                                        <input type="datetime-local" wire:model="scheduleStartDate" style="width: 100%; padding: 8px; border: 1px solid var(--line); border-radius: 4px; background: var(--bg);">
                                    </div>
                                </div>

                                <button wire:click="publishRun" class="primary-button" style="display: flex; justify-content: center; gap: 10px; width: 100%; padding: 12px;">
                                    <span>🚀</span> Activer et publier sur la plateforme
                                </button>
                                
                                <div style="text-align: center; margin-top: 15px;">
                                    <a href="{{ route("admin.articles") }}" class="secondary-button" style="text-decoration: none; padding: 10px 20px; display: inline-block;">
                                        Voir les contenus dans le CMS
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="empty-state">
                        <span style="font-size: 32px; color: #a1a9b9;">⚙️</span>
                        <h2 style="margin: 10px 0 5px;">Étape {{ $currentStep }} : {{ $steps[$currentStep] ?? 'Inconnue' }}</h2>
                        <p>Contenu en cours de construction...</p>
                    </div>
                @endif
             </div>
        <!-- Persistent Sidebar -->
        <div class="engine-sidebar">
            <div class="panel" style="padding: 20px;">
                <h3 style="margin: 0 0 16px; font-size: 14px; font-weight: 700; color: #30394f;">Progression</h3>
                
                @if($activeProject)
                    <div class="active-project-card">
                        <span class="eyebrow">Projet Actif</span>
                        <strong>{{ $activeProject->name }}</strong>
                    </div>

                    <div class="stat-grid" style="grid-template-columns: 1fr 1fr; gap: 12px; margin: 20px 0;">
                        <div class="stat-card" style="padding: 12px; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                            <small>Mots-clés</small>
                            <strong style="margin: 0; font-size: 18px; color: #6655e9;">{{ $activeProject->keywords_count ?? 0 }}</strong>
                        </div>
                        <div class="stat-card" style="padding: 12px; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                            <small>Articles</small>
                            <strong style="margin: 0; font-size: 18px; color: #6655e9;">{{ $activeProject->articles_count ?? 0 }}</strong>
                        </div>
                        <div class="stat-card" style="padding: 12px; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                            <small>Pilotes</small>
                            <strong style="margin: 0; font-size: 18px; color: #6655e9;">0</strong>
                        </div>
                        <div class="stat-card" style="padding: 12px; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                            <small>Comparatifs</small>
                            <strong style="margin: 0; font-size: 18px; color: #6655e9;">0</strong>
                        </div>
                    </div>

                    <div class="authority-scores">
                        <h4 style="margin: 0 0 10px; font-size: 11px; color: #30394f;">Scores d'autorité</h4>
                        
                        <div class="score-bar">
                            <div class="score-labels">
                                <span>Score SEO</span>
                                <strong>{{ min(100, ($activeProject->keywords_count ?? 0) * 2) }}/100</strong>
                            </div>
                            <div class="score-track">
                                <div class="score-fill" style="width: {{ min(100, ($activeProject->keywords_count ?? 0) * 2) }}%; background: #6655e9;"></div>
                            </div>
                        </div>

                        <div class="score-bar" style="margin-top: 10px;">
                            <div class="score-labels">
                                <span>Score EEAT</span>
                                <strong>{{ min(100, ($activeProject->articles_count ?? 0) * 5) }}/100</strong>
                            </div>
                            <div class="score-track">
                                <div class="score-fill" style="width: {{ min(100, ($activeProject->articles_count ?? 0) * 5) }}%; background: #32bf87;"></div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="empty-card" style="padding: 30px 10px;">
                        Aucun projet sélectionné.<br>Commencez par l'étape 1.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <style>
        .engine-layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 24px; align-items: start; }
        .engine-stepper { display: flex; overflow-x: auto; padding-bottom: 16px; margin-bottom: 24px; }
        .step-item { display: flex; align-items: center; }
        .step-btn { padding: 6px 14px; border: 1px solid transparent; border-radius: 20px; background: white; color: #6f7890; font-size: 11px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: 0.2s; }
        .step-btn:hover { background: #f0f1f5; }
        .step-item.active .step-btn { background: #6655e9; color: white; box-shadow: 0 4px 10px #6655e944; }
        .step-item.completed .step-btn { background: #e5f7ef; color: #22805f; border-color: #32b98244; }
        .step-separator { margin: 0 12px; color: #c0c5ce; font-size: 16px; line-height: 1; }
        .active-project-card { padding: 14px; border-radius: 10px; background: #fafbfc; border: 1px solid var(--line); }
        .active-project-card strong { display: block; margin-top: 4px; font-size: 13px; color: var(--ink); }
        .score-labels { display: flex; justify-content: space-between; font-size: 10px; color: #7c8598; margin-bottom: 5px; }
        .score-track { height: 6px; background: #f0f1f5; border-radius: 4px; overflow: hidden; }
        .score-fill { height: 100%; border-radius: 4px; transition: width 0.3s ease; }
    </style>
</div>
