<div class="dashboard-page os-page automation-page" x-data="{ running: false, planning: false }" @if($sourcesCollecting) wire:poll.2s.keep-alive="refreshPreparation" @endif
     x-init="@if($plan && $plan->status === 'planning') planning = true; @endif @if($run && in_array($run->status,['pending','processing'])) running = true; @endif"
     @planning-started.window="planning = true"
     @planning-step-finished.window="planning = true"
     @planning-retry-later.window="planning = true"
     @planning-finished.window="planning = false"
     @batch-started.window="running = true"
     @batch-recheck.window="running = true"
     @batch-retry-later.window="running = true"
     @batch-paused.window="running = false"
     @batch-stopped.window="running = false"
     @batch-item-finished.window="running = $event.detail.remaining > 0">
    <section class="page-heading"><div><span class="eyebrow dark">Pilote automatique</span><h1>Générer un lot de contenus</h1><p>Un affilié, ses sources, un export de mots-clés et un nombre de contenus : le reste est orchestré automatiquement.</p></div><span class="automation-state"><i></i>{{ $hasApiKey ? 'Gemini prêt' : 'Clé Gemini requise' }}</span></section>

    @if($errors->any())<div class="alert danger validation-summary"><strong>Analyse bloquée : corrigez ces champs.</strong><ul>@foreach($errors->all() as $validationError)<li>{{ $validationError }}</li>@endforeach</ul></div>@endif
    @if($message)<div class="alert success">✓ {{ $message }}</div>@endif
    @if($error)<div class="alert danger">{{ $error }}</div>@endif
    @if($crawlErrors)<details class="crawl-errors"><summary>{{ count($crawlErrors) }} source(s) non collectée(s)</summary>@foreach($crawlErrors as $crawlError)<p>{{ $crawlError }}</p>@endforeach</details>@endif

    <section class="automation-steps">
        <article class="panel automation-step {{ $workspaceReady ? 'done' : 'active' }}">
            <header><i>1</i><div><h2>Affilié, sources et mots-clés</h2><p>L’application crée la base de connaissances automatiquement.</p></div>@if($workspaceReady)<span>✓ Prêt</span>@endif</header>
            <div class="automation-form">
                <div class="mode-switch"><label><input type="radio" wire:model.live="mode" value="new"> Nouvel affilié</label><label><input type="radio" wire:model.live="mode" value="existing"> Projet existant</label></div>
                @if($mode === 'existing')
                    <div class="field full"><label>Projet existant</label><select wire:model="existingProjectId"><option value="">Sélectionner un projet</option>@foreach($projects as $existing)<option value="{{ $existing->id }}">{{ $existing->name }}</option>@endforeach</select>@error('existingProjectId')<span class="field-error">{{ $message }}</span>@enderror</div>
                @else
                    <div class="field"><label>Nom de l’outil</label><input wire:model="name" placeholder="ex. Semrush">@error('name')<span class="field-error">{{ $message }}</span>@enderror</div><div class="field"><label>Site officiel</label><input wire:model="websiteUrl" type="url" placeholder="https://...">@error('websiteUrl')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label>Page tarifs</label><input wire:model="pricingUrl" type="url" placeholder="https://.../pricing">@error('pricingUrl')<span class="field-error">{{ $message }}</span>@enderror</div><div class="field"><label>Lien affilié</label><input wire:model="affiliateUrl" type="url" placeholder="https://...?ref=...">@error('affiliateUrl')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="field mini"><label>Pays</label><input wire:model="country" maxlength="2">@error('country')<span class="field-error">{{ $message }}</span>@enderror</div><div class="field mini"><label>Devise</label><input wire:model="currency" maxlength="3">@error('currency')<span class="field-error">{{ $message }}</span>@enderror</div>
                @endif
                <div class="field full"><label>Autres pages officielles — une URL par ligne</label><textarea wire:model="extraSourceUrls" rows="3" placeholder="https://.../features&#10;https://.../integrations&#10;https://.../faq"></textarea><p class="field-help">Maximum 4 pages supplémentaires. Le site et la page tarifs sont inclus automatiquement.</p>@error('extraSourceUrls')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field full"><label>Concurrents réels de l'affilié — un par ligne</label><textarea wire:model="competitorsText" rows="4" placeholder="Pennylane&#10;Abby&#10;Freebe&#10;Henrri"></textarea><p class="field-help">Pour les comparatifs : seules ces marques seront autorisées. Vous pouvez aussi saisir « Abby | https://.../tarifs ».</p>@error('competitorsText')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field full"><label>Pages tarifs concurrents — facultatif</label><textarea wire:model="competitorPricingUrlsText" rows="3" placeholder="Abby | https://.../tarifs&#10;Freebe | https://.../tarifs"></textarea><p class="field-help">Ces pages sont collectées comme la page tarifs affiliée : rendu JS, extraction des offres, normalisation et preuves sourcées.</p>@error('competitorPricingUrlsText')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field upload-field"><label>Export Semrush ou Google Keyword Planner</label><input wire:model="csv" type="file" accept=".csv,.tsv,.txt,text/csv,text/tab-separated-values"><p class="field-help"><a href="/examples/semrush-template.csv">Télécharger le modèle CSV</a></p>@error('csv')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label>Clé Gemini {{ $hasApiKey ? '(déjà configurée)' : '' }}</label><input wire:model="apiKey" type="password" autocomplete="new-password" placeholder="{{ $hasApiKey ? 'Laisser vide pour conserver la clé' : 'Clé API Gemini' }}"><p class="field-help">Chiffrée côté serveur dès l’enregistrement.</p>@error('apiKey')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field full automation-keyword-paste"><label>Mots-clés Semrush à coller — facultatif en plus du fichier</label><textarea wire:model="pastedKeywords" rows="6" placeholder="Mot clé&#9;Intention&#9;Volume&#9;Tendance&#9;KD %&#9;CPC (EUR)&#10;logiciel devis facture&#9;I&#9;2400&#9;—&#9;54&#9;6,02"></textarea><p class="field-help">Copiez aussi la ligne d’en-tête Semrush. Si un fichier est ajouté, les deux sources sont fusionnées et les doublons mis à jour.</p>@error('pastedKeywords')<span class="field-error">{{ $message }}</span>@enderror</div>
                <button class="primary-button automation-submit" wire:click="prepare" wire:loading.attr="disabled" type="button" @disabled($sourcesCollecting || ($run && in_array($run->status,['pending','processing'])))><span wire:loading.remove wire:target="prepare">{{ $sourcesCollecting ? 'Collecte en arrière-plan…' : 'Analyser et préparer le dossier →' }}</span><span wire:loading wire:target="prepare">Mise en file des sources…</span></button>
            </div>
        </article>

        <article class="panel automation-step {{ $workspaceReady ? 'active' : 'locked' }}">
            <header><i>2</i><div><h2>Planification éditoriale</h2><p>Les angles sont dédupliqués et verrouillés avant la rédaction.</p></div>@if($project)<span>{{ $project->keywords_count }} mots-clés</span>@endif</header>
            <div class="automation-goal">
                @if($project)<div class="readiness-grid"><div><strong>{{ $project->source_pages_count }}</strong><span>Sources collectées</span></div><div><strong>{{ $project->keywords_count }}</strong><span>Mots-clés scorés</span></div><div><strong>{{ $project->name }}</strong><span>Projet sélectionné</span></div></div>@endif
                <div class="automation-configuration-grid" style="display: flex; gap: 24px; margin-bottom: 24px;">
                    <div class="count-picker"><label>Combien de contenus uniques voulez-vous obtenir ?</label><div><button type="button" wire:click="$set('contentCount', {{ max(1,$contentCount-1) }})">−</button><input wire:model="contentCount" type="number" min="1" max="30"><button type="button" wire:click="$set('contentCount', {{ min(30,$contentCount+1) }})">＋</button></div><small>Le compteur porte sur les articles validés, pas sur les brouillons techniques.</small></div>
                    <div class="count-picker"><label>Étaler la publication sur (jours) ?</label><div><button type="button" wire:click="$set('publicationDays', {{ max(1,($publicationDays ?? 0)-1) }})">−</button><input wire:model="publicationDays" type="number" min="1" max="365" placeholder="Immédiat"><button type="button" wire:click="$set('publicationDays', {{ min(365,($publicationDays ?? 0)+1) }})">＋</button></div><small>Laissez vide pour publier le lot immédiatement.</small></div>
                </div>
                <div class="field">
                    <label style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Consignes communes de la stratégie</span>
                        <div class="presets-buttons" style="display: flex; gap: 8px;">
                            <button type="button" wire:click="setPreset('pillar')" style="background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer;">Pages Mères (Pillars)</button>
                            <button type="button" wire:click="setPreset('money')" style="background: #fce7f3; color: #db2777; border: 1px solid #fbcfe8; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer;">Money Pages (Quick Wins)</button>
                            <button type="button" wire:click="setPreset('interception')" style="background: #fef9c3; color: #ca8a04; border: 1px solid #fef08a; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer;">Trafic de masse (Interception)</button>
                        </div>
                    </label>
                    <textarea wire:model="instructions" rows="4" placeholder="Ton expert accessible, audience PME, longueur souhaitée…"></textarea>
                </div>
                <div class="auto-decisions"><strong>Décisions automatiques</strong><span>✓ 3× plus d’idées analysées que de contenus demandés</span><span>✓ Doublons existants et doublons internes éliminés avant rédaction</span><span>✓ Promesse, audience, exclusions et mini-plan H2 verrouillés</span><span>✓ Réserve activée automatiquement si un brouillon dérive</span></div>
                @if($run && in_array($run->status,['pending','processing']))
                    <div class="launch-button"><span>↻</span><div><strong>Production automatique en cours</strong><small>{{ $run->items->sum('generation_step') }} partie(s) sauvegardée(s) · {{ $run->completed_count }} sur {{ $run->requested_count }} contenus validés.</small></div><b>…</b></div>
                @elseif($run && in_array($run->status,['paused','completed_with_errors']) && $run->items->where('status','pending')->count() > 0)
                    <button class="launch-button" wire:click="resumeRun" wire:loading.attr="disabled" type="button">
                        <span>↻</span>
                        <div>
                            <strong wire:loading.remove wire:target="resumeRun">Continuer la génération ({{ $run->completed_count }}/{{ $run->requested_count }} validés)</strong>
                            <strong wire:loading wire:target="resumeRun">Reprise en cours…</strong>
                            <small>{{ $run->items->where('status','pending')->count() }} article(s) restant(s) — reprenez sans rien perdre.</small>
                        </div>
                        <b>→</b>
                    </button>
                @elseif($plan && $plan->status === 'planning')
                    <div class="run-progress" wire:poll.5s.keep-alive="processPlanningStep"><div><strong>{{ $plan->ideas->where('status','candidate')->count() }}/{{ $plan->requested_count }} angles retenus</strong><span>{{ $plan->candidate_count }} idées analysées · étape {{ $plan->attempts }}/{{ $plan->requested_count >= 20 ? 15 : ($plan->requested_count >= 10 ? 10 : 6) }} · reprise automatique toutes les 5 secondes si Gemini est saturé</span></div></div>
                    <div class="launch-button"><span>⌁</span><div><strong>Gemini prépare le prochain lot…</strong><small>Aucune action requise : les timeouts et HTTP 503 sont réessayés automatiquement, même si l’onglet passe en arrière-plan.</small></div><b>…</b></div>
                @elseif($plan && $plan->isReady())
                    <div class="run-progress">
                        <div><strong>{{ $plan->accepted_count }}/{{ $plan->requested_count }} angles validés</strong><span>{{ $plan->candidate_count }} idées analysées · {{ $plan->duplicate_count }} doublons · {{ $plan->weak_angle_count }} angles faibles · {{ $plan->ideas->where('status','reserve')->count() }} réserves</span></div>
                        <div class="run-actions">
                            <button class="danger" type="button" wire:click="cancelPlan" wire:loading.attr="disabled" wire:confirm="Annuler cette planification et supprimer ces angles ? Vous pourrez relancer une analyse avec d'autres consignes.">Annuler et recommencer</button>
                        </div>
                    </div>
                    <button class="launch-button" wire:click="launchRun" wire:loading.attr="disabled" type="button" @disabled($plan->accepted_count !== $plan->requested_count)><span>✦</span><div><strong>Générer les {{ $plan->requested_count }} articles</strong><small>La rédaction suit uniquement les briefs verrouillés ci-dessous.</small></div><b>→</b></button>
                @elseif($plan && $plan->status === 'failed')
                    <div class="run-progress">
                        <div><strong>Planification interrompue (Erreur)</strong><span>{{ $plan->candidate_count }} idées analysées</span></div>
                        <div class="run-actions">
                            <button class="danger" type="button" wire:click="cancelPlan" wire:loading.attr="disabled">Annuler et recommencer</button>
                            @if($plan->ideas->where('status', 'candidate')->count() > 0)
                                <button type="button" wire:click="lockFailedPlan" wire:loading.attr="disabled">Valider les {{ $plan->ideas->where('status', 'candidate')->count() }} idées trouvées</button>
                            @endif
                        </div>
                    </div>
                @else
                    <button class="launch-button" wire:click="startRun" wire:loading.attr="disabled" type="button" @disabled(!$workspaceReady || !$hasApiKey)><span>⌁</span><div><strong wire:loading.remove wire:target="startRun">Planifier {{ $contentCount }} contenus</strong><strong wire:loading wire:target="startRun">Analyse et déduplication des angles…</strong><small>Aucun article n’est rédigé pendant cette étape.</small></div><b>→</b></button>
                @endif
                @if($plan)
                <div class="table-wrap"><table><thead><tr><th>#</th><th>Idée éditoriale</th><th>Intention</th><th>Angle / audience</th><th>Score</th><th>Similarité</th><th>Statut</th></tr></thead><tbody>@foreach($plan->ideas->whereIn('status',['candidate','accepted','reserve','generated','generating']) as $idea)<tr><td>{{ $idea->position ?? '—' }}</td><td><div class="title-with-kd"><strong class="table-title">{{ $idea->title }}</strong>@if($idea->keyword?->hasMeasuredDifficulty())<span class="kd-badge {{ $idea->keyword->keyword_difficulty <= 30 ? 'low' : ($idea->keyword->keyword_difficulty <= 50 ? 'medium' : 'high') }}">KD {{ number_format($idea->keyword->keyword_difficulty,0) }}</span>@elseif($idea->keyword)<span class="kd-badge">KD —</span>@endif</div><small>{{ $idea->primary_keyword }}</small></td><td>{{ $idea->intent }}</td><td>{{ str_replace('-',' ',$idea->angle) }}<br><small>{{ str_replace('-',' ',$idea->audience) }}</small></td><td>{{ number_format($idea->seo_score,1,',',' ') }}</td><td>{{ number_format($idea->similarity_score,0) }} %</td><td><span class="state-badge {{ $idea->status }}">{{ $idea->status }}</span></td></tr>@endforeach</tbody></table></div>
                @endif
            </div>
        </article>

        <article class="panel automation-step {{ $run ? 'active' : 'locked' }}">
            <header><i>3</i><div><h2>Production du lot</h2><p>Suivi détaillé de chaque contenu.</p></div>@if($run)<span class="run-status {{ $run->status }}">{{ str_replace('_',' ',$run->status) }}</span>@endif</header>
            @if($run)
                <div class="run-progress" @if(in_array($run->status,['pending','processing'])) wire:poll.5s.keep-alive="processNext" @endif><div><strong>{{ $run->progress_percentage }}%</strong><span>{{ $run->completed_count }} validés · {{ $run->items->sum('generation_step') }} parties sauvegardées · {{ $run->items->where('status','rejected')->count() }} remplacés · {{ $run->failed_count }} échecs techniques · {{ $run->requested_count }} attendus</span></div><div class="progress-track"><span style="width:{{ $run->progress_percentage }}%"></span></div>@if(in_array($run->status,['pending','processing']))<div class="run-actions"><span>La génération avance automatiquement.</span><button class="danger" type="button" wire:click="stopRun" wire:loading.attr="disabled" wire:confirm="Arrêter cette campagne ? Les articles terminés et les parties déjà générées seront conservés.">Arrêter la campagne</button></div>@elseif($run->status === 'paused')<div class="run-actions"><button type="button" wire:click="resumePausedRun({{ $run->id }})">Reprendre la campagne</button><button class="danger" type="button" wire:click="stopRun" wire:confirm="Arrêter définitivement cette campagne ? Les parties déjà générées seront conservées.">Arrêter la campagne</button></div>@elseif($run->status === 'completed_with_errors' && $run->failed_count > 0)<button type="button" wire:click="retryFailedRun({{ $run->id }})" x-show="!running">Réessayer les contenus en échec</button>@endif</div>
                <div class="batch-items">
                    @foreach($run->items as $item)
                        <div class="batch-item {{ $item->status }}">
                            <i>@if($item->status==='completed')✓@elseif($item->status==='failed')!@elseif($item->status==='rejected')↺@elseif($item->status==='processing')↻@else{{ $loop->iteration }}@endif</i>
                            <div>
                                <div class="title-with-kd"><strong>{{ $item->editorialIdea?->title ?? $item->keyword?->keyword ?? 'Brief supprimé' }}</strong>@php($itemKeyword = $item->editorialIdea?->keyword ?? $item->keyword)@if($itemKeyword?->hasMeasuredDifficulty())<span class="kd-badge {{ $itemKeyword->keyword_difficulty <= 30 ? 'low' : ($itemKeyword->keyword_difficulty <= 50 ? 'medium' : 'high') }}">KD {{ number_format($itemKeyword->keyword_difficulty,0) }}</span>@elseif($itemKeyword)<span class="kd-badge">KD —</span>@endif</div>
                                <span>
                                    {{ str_replace('_',' ',$item->content_type) }}
                                    @if(in_array($item->status,['pending','processing']) && ($item->generation_step || $item->api_attempts))
                                        · {{ $item->generation_step }} partie(s) sauvegardée(s)
                                        @if($item->api_attempts) · tentative {{ $item->api_attempts }} @endif
                                    @endif
                                    @if($item->error_message) · {{ Str::limit($item->error_message,90) }} @endif
                                </span>
                            </div>
                            <b>{{ $item->status }}</b>
                            @if($item->article)<a href="{{ route('admin.articles.edit',$item->article) }}" wire:navigate>Relire →</a>@endif
                        </div>
                    @endforeach
                </div>
            @else<div class="locked-state"><span>3</span><p>Préparez le dossier puis lancez une campagne.</p></div>@endif
        </article>
    </section>

    @if($recentRuns->isNotEmpty())<article class="panel recent-runs"><div class="panel-head"><div><h2>Campagnes récentes</h2><p>Historique des générations automatisées</p></div></div>@if($selectedIds)<div class="bulk-toolbar"><strong>{{ count($selectedIds) }} sélectionnée(s)</strong><button type="button" wire:click="clearSelection">Annuler</button><button class="danger" type="button" wire:click="deleteSelected" wire:confirm="Supprimer les campagnes sélectionnées ? Les articles déjà générés seront conservés.">Supprimer</button></div>@endif<div class="table-wrap"><table><thead><tr><th class="selection-cell"><input type="checkbox" wire:model.live="selectAll" aria-label="Tout sélectionner"></th><th>Campagne</th><th>Projet</th><th>Statut</th><th>Progression</th><th>Date</th><th>Action</th></tr></thead><tbody>@foreach($recentRuns as $recent)<tr class="{{ in_array($recent->id,$selectedIds) ? 'is-selected':'' }}"><td class="selection-cell"><input type="checkbox" wire:model.live="selectedIds" value="{{ $recent->id }}" aria-label="Sélectionner {{ $recent->name }}"></td><td><strong class="table-title">{{ $recent->name }}</strong></td><td>{{ $recent->project->name }}</td><td><span class="state-badge {{ $recent->status }}">{{ str_replace('_',' ',$recent->status) }}</span></td><td>{{ $recent->completed_count }}/{{ $recent->requested_count }} contenus</td><td>{{ $recent->created_at->diffForHumans() }}</td><td>@if($recent->status === 'paused')<button type="button" wire:click="resumePausedRun({{ $recent->id }})">Reprendre</button>@elseif($recent->status === 'completed_with_errors' && $recent->failed_count > 0)<button type="button" wire:click="retryFailedRun({{ $recent->id }})">Réessayer</button>@else<span>—</span>@endif</td></tr>@endforeach</tbody></table></div></article>@endif
</div>
