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
        <div class="panel-head"><div><h2>Opportunités éditoriales</h2><p>Score 0–100 selon volume, difficulté, intention et potentiel affilié</p></div><div class="search-box"><span>⌕</span><input wire:model.live.debounce.300ms="search" type="search" placeholder="Rechercher…"></div></div>
        @if($selectedIds)<div class="bulk-toolbar"><strong>{{ count($selectedIds) }} sélectionné(s)</strong><span>{{ $selectAll ? 'Tous les résultats filtrés sont sélectionnés.' : '' }}</span><button type="button" wire:click="clearSelection">Annuler</button><button class="danger" type="button" wire:click="deleteSelected" wire:confirm="Supprimer définitivement les mots-clés sélectionnés ? Les articles existants seront conservés.">Supprimer</button></div>@endif
        <div class="table-wrap"><table><thead><tr><th class="selection-cell"><input type="checkbox" wire:model.live="selectAll" aria-label="Tout sélectionner"></th><th>Mot-clé</th><th>Stratégie</th><th>Cluster</th><th>Intention</th><th>Volume</th><th>KD</th><th>CPC</th><th>Opportunité</th></tr></thead><tbody>
            @forelse($keywords as $keyword)<tr class="{{ in_array($keyword->id,$selectedIds) ? 'is-selected':'' }}"><td class="selection-cell"><input type="checkbox" wire:model.live="selectedIds" value="{{ $keyword->id }}" aria-label="Sélectionner {{ $keyword->keyword }}"></td><td><strong class="table-title">{{ $keyword->keyword }}</strong></td><td><span class="strategy-badge {{ $keyword->strategy_tier }}">{{ ['pillar'=>'Pilier','quick_win'=>'Quick win','niche'=>'Niche','supporting'=>'Support'][$keyword->strategy_tier] }}</span></td><td><span class="cluster-badge">{{ $keyword->cluster }}</span></td><td>{{ $keyword->intent ?: '—' }}</td><td>{{ number_format($keyword->search_volume,0,',',' ') }}</td><td>{{ $keyword->hasMeasuredDifficulty() ? number_format($keyword->keyword_difficulty,0) : '—' }}</td><td>{{ $keyword->cpc !== null ? $keyword->cpc.' €' : '—' }}</td><td><div class="score"><span style="width:{{ $keyword->opportunity_score }}%"></span></div><b class="score-value">{{ round($keyword->opportunity_score) }}</b></td></tr>
            @empty<tr><td colspan="9" class="empty-state">Importez un export de mots-clés pour commencer.</td></tr>@endforelse
        </tbody></table></div><div class="pagination">{{ $keywords->links() }}</div>
    </article>
</div>
