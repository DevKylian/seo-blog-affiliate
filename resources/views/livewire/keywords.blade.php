<div class="dashboard-page os-page">
    <section class="page-heading"><div><span class="eyebrow dark">Analyse SEO</span><h1>Mots-clés SEO</h1><p>Importez vos exports Semrush ou Google Keyword Planner et priorisez l’intention commerciale.</p></div></section>
    @if($message)<div class="alert success">✓ {{ $message }}</div>@endif @if($error)<div class="alert danger">{{ $error }}</div>@endif
    <article class="panel import-panel"><form wire:submit="import" class="inline-form"><div class="field"><label>Projet</label><select wire:model.live="projectId"><option value="">Sélectionner</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></div><div class="field grow"><label>Export Semrush ou Keyword Planner (10 Mo max.)</label><input wire:model="csv" type="file" accept=".csv,.tsv,.txt,text/csv,text/tab-separated-values"></div><button class="primary-button" type="submit"><span wire:loading.remove wire:target="import,csv">Importer & scorer</span><span wire:loading wire:target="import,csv">Traitement…</span></button></form><p class="form-note" style="margin-bottom: 20px;">Détection automatique des CSV/TSV, lignes de métadonnées et colonnes Google Ads.</p>
    
    @if($projectId)
    <details class="paste-keywords" style="margin-bottom: 16px;">
        <summary>🤖 Idéation : Générer des mots-clés racines (Seeds) avec l'IA</summary>
        <div style="padding: 16px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; margin-top: 12px;">
            <p class="form-note" style="margin:0 0 12px;">L'IA va analyser l'URL et les données de votre projet pour suggérer 10 termes de recherche ciblés à exploiter sur Semrush.</p>
            <button class="primary-button" type="button" wire:click="generateSeeds"><span wire:loading.remove wire:target="generateSeeds">Suggérer des mots-clés</span><span wire:loading wire:target="generateSeeds">Analyse en cours…</span></button>
            @if(!empty($suggestedSeeds))
            <div style="margin-top: 20px; background: white; padding: 16px; border: 1px solid #cbd5e1; border-radius: 8px;">
                <strong style="display:block; margin-bottom: 8px;">Termes à rechercher dans Semrush (Keyword Magic Tool) :</strong>
                <ul style="margin: 0; padding-left: 20px; color: #334155; line-height: 1.6;">
                    @foreach($suggestedSeeds as $seed)
                        <li><strong>{{ $seed }}</strong></li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </details>
    @endif
    
    <details class="paste-keywords"><summary>Pas de fichier ? Coller directement le tableau Semrush</summary><form wire:submit="importPasted"><div class="field"><label>Tableau copié depuis Semrush</label><textarea wire:model="pastedKeywords" rows="8" placeholder="Mot clé&#9;Intention&#9;Volume&#9;Tendance&#9;KD %&#9;CPC (EUR)&#10;logiciel devis facture&#9;I&#9;2400&#9;—&#9;54&#9;6,02"></textarea></div><div class="paste-actions"><p class="form-note">Copiez la ligne d’en-tête et les lignes du tableau. Les tabulations Semrush sont reconnues automatiquement.</p><button class="primary-button" type="submit"><span wire:loading.remove wire:target="importPasted">Importer le texte & scorer</span><span wire:loading wire:target="importPasted">Traitement…</span></button></div></form></details></article>
    <article class="panel">
        <div class="panel-head"><div><h2>Opportunités éditoriales</h2><p>Score 0–100 selon volume, difficulté, intention et potentiel affilié</p></div><div class="search-box" style="display: flex; gap: 12px; align-items: center;"><button class="secondary-button" type="button" title="Nettoyer avec l'IA" wire:click="analyzeForCleaning" wire:loading.attr="disabled" style="display: flex; align-items: center; justify-content: center; padding: 6px 12px;"><span wire:loading.remove wire:target="analyzeForCleaning">✨</span><span wire:loading wire:target="analyzeForCleaning">⏳</span></button><button class="secondary-button" wire:click="exportCsv" type="button" style="display: flex; align-items: center; gap: 6px; white-space: nowrap;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Exporter</button><div><span>⌕</span><input wire:model.live.debounce.300ms="search" type="search" placeholder="Rechercher…"></div></div></div>
        @if($selectedIds)<div class="bulk-toolbar"><strong>{{ count($selectedIds) }} sélectionné(s)</strong><span>{{ $selectAll ? 'Tous les résultats filtrés sont sélectionnés.' : '' }}</span><button type="button" wire:click="clearSelection">Annuler</button><button class="danger" type="button" wire:click="deleteSelected" wire:confirm="Supprimer définitivement les mots-clés sélectionnés ? Les articles existants seront conservés.">Supprimer</button></div>@endif
        
        @if($showCleaningConfirmation)
        <div style="background: #fff0f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 16px; margin: 16px 20px;">
            <h3 style="color: #be123c; margin: 0 0 8px;">⚠️ {{ count($rejectedKeywords) }} mots-clés hors-sujet détectés</h3>
            <p style="color: #881337; margin: 0 0 12px; font-size: 14px;">L'IA vous recommande de supprimer ces mots-clés qui ne sont pas pertinents pour votre niche :</p>
            <div style="max-height: 200px; overflow-y: auto; background: white; border: 1px solid #fecdd3; border-radius: 6px; padding: 12px; margin-bottom: 12px;">
                <ul style="margin: 0; padding-left: 20px; color: #4c0519; font-size: 13px;">
                    @foreach($rejectedKeywords as $rk)
                        <li>{{ $rk['keyword'] }}</li>
                    @endforeach
                </ul>
            </div>
            <div style="display: flex; gap: 12px;">
                <button type="button" wire:click="confirmCleaning" style="background: #e11d48; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;">Oui, supprimer définitivement</button>
                <button type="button" wire:click="cancelCleaning" style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;">Annuler</button>
            </div>
        </div>
        @endif

        <div class="table-wrap"><table><thead><tr><th class="selection-cell"><input type="checkbox" wire:model.live="selectAll" aria-label="Tout sélectionner"></th><th>Mot-clé</th><th>Stratégie</th><th>Cluster</th><th>Intention</th><th>Volume</th><th>KD</th><th>CPC</th><th>Opportunité</th></tr></thead><tbody>
            @forelse($keywords as $keyword)<tr class="{{ in_array($keyword->id,$selectedIds) ? 'is-selected':'' }}"><td class="selection-cell"><input type="checkbox" wire:model.live="selectedIds" value="{{ $keyword->id }}" aria-label="Sélectionner {{ $keyword->keyword }}"></td><td><strong class="table-title">{{ $keyword->keyword }}</strong></td><td><span class="strategy-badge {{ $keyword->strategy_tier }}">{{ ['pillar'=>'Pilier','quick_win'=>'Quick win','niche'=>'Niche','supporting'=>'Support'][$keyword->strategy_tier] }}</span></td><td><span class="cluster-badge">{{ $keyword->cluster }}</span></td><td>{{ $keyword->intent ?: '—' }}</td><td>{{ number_format($keyword->search_volume,0,',',' ') }}</td><td>{{ $keyword->hasMeasuredDifficulty() ? number_format($keyword->keyword_difficulty,0) : '—' }}</td><td>{{ $keyword->cpc !== null ? $keyword->cpc.' €' : '—' }}</td><td><div class="score"><span style="width:{{ $keyword->opportunity_score }}%"></span></div><b class="score-value">{{ round($keyword->opportunity_score) }}</b></td></tr>
            @empty<tr><td colspan="9" class="empty-state">Importez un export de mots-clés pour commencer.</td></tr>@endforelse
        </tbody></table></div><div class="pagination">{{ $keywords->links() }}</div>
    </article>
</div>
