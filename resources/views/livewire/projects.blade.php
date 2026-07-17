<div class="dashboard-page os-page">
    <section class="page-heading"><div><span class="eyebrow dark">Portefeuille</span><h1>Projets & outils</h1><p>Centralisez les informations commerciales des logiciels que vous recommandez.</p></div></section>
    @if($message)<div class="alert success">✓ {{ $message }}</div>@endif

    <details class="panel project-form-disclosure" @if($editingId) open @endif>
        <summary><div><strong>{{ $editingId ? 'Modifier l’application' : 'Ajouter une application' }}</strong><span>Nom, URLs, affiliation et données éditoriales</span></div><b>{{ $editingId ? 'Formulaire ouvert' : 'Ouvrir le formulaire' }} ＋</b></summary>
        <form wire:submit="createProject" class="os-form">
            <div class="field full"><label>Nom de l’application</label><input wire:model="name" placeholder="ex. Brevo">@error('name')<small class="field-error">{{ $message }}</small>@enderror</div>
            <div class="field full"><label>Site officiel</label><input wire:model="websiteUrl" type="url" placeholder="https://example.com">@error('websiteUrl')<small class="field-error">{{ $message }}</small>@enderror</div>
            <div class="field"><label>Page tarifs</label><input wire:model="pricingUrl" type="url" placeholder="https://example.com/pricing"></div>
            <div class="field"><label>Lien affilié</label><input wire:model="affiliateUrl" type="url" placeholder="https://example.com/?ref=..."></div>
            <div class="field"><label>Pays</label><input wire:model="country" maxlength="2"></div><div class="field"><label>Devise</label><input wire:model="currency" maxlength="3"></div>
            <div class="field full"><label>Positionnement</label><input wire:model="positioning" placeholder="Outil de gestion de projets pour agences"></div><div class="field full"><label>Description vérifiée</label><textarea wire:model="description" rows="3"></textarea></div>
            <div class="field"><label>Fonctionnalités (une par ligne)</label><textarea wire:model="featuresText" rows="5"></textarea></div><div class="field"><label>Idéal pour (un profil par ligne)</label><textarea wire:model="bestForText" rows="5"></textarea></div>
            <div class="field full"><label>Concurrents reels (un par ligne)</label><textarea wire:model="competitorsText" rows="4" placeholder="Pennylane&#10;Abby&#10;Freebe&#10;Henrri"></textarea><p class="field-help">Utilise pour les vrais comparatifs. Format accepte aussi : Abby | https://.../tarifs.</p></div>
            <div class="field full"><label>Pages tarifs concurrents</label><textarea wire:model="competitorPricingUrlsText" rows="3" placeholder="Abby | https://.../tarifs&#10;Freebe | https://.../tarifs"></textarea><p class="field-help">Collectees avec le meme pipeline que la page tarifs affiliee.</p></div>
            <div class="field"><label>Points forts (un par ligne)</label><textarea wire:model="strengthsText" rows="5"></textarea></div><div class="field"><label>Limites (une par ligne)</label><textarea wire:model="limitationsText" rows="5"></textarea></div>
            <button class="primary-button form-submit" type="submit"><span wire:loading.remove wire:target="createProject">{{ $editingId ? 'Enregistrer les modifications' : 'Créer le projet' }} →</span><span wire:loading wire:target="createProject">Enregistrement…</span></button>
        </form>
    </details>

    <article class="panel projects-table-panel">
        <div class="panel-head"><div><h2>Portefeuille des projets</h2><p>{{ $projects->count() }} projet(s) affiché(s)</p></div><div class="search-box"><span>⌕</span><input wire:model.live.debounce.300ms="search" type="search" placeholder="Nom ou domaine…"></div></div>
        @if($selectedIds)<div class="bulk-toolbar"><strong>{{ count($selectedIds) }} sélectionné(s)</strong><span>{{ $selectAll ? 'Tous les projets filtrés sont sélectionnés.' : '' }}</span><button type="button" wire:click="clearSelection">Annuler</button><button class="danger" type="button" wire:click="deleteSelected" wire:confirm="Supprimer définitivement les projets sélectionnés ? Leurs sources, tarifs, mots-clés, campagnes, briefs et articles seront également supprimés.">Supprimer</button></div>@endif
        <div class="table-wrap"><table><thead><tr><th class="selection-cell"><input type="checkbox" wire:model.live="selectAll" aria-label="Tout sélectionner"></th><th>Application</th><th>Pays</th><th>Statut</th><th>Sources</th><th>Mots-clés</th><th>Contenus</th><th>Affiliation</th><th>Dernier crawl</th><th></th></tr></thead><tbody>
            @forelse($projects as $project)
                <tr class="{{ in_array($project->id,$selectedIds) ? 'is-selected':'' }}">
                    <td class="selection-cell"><input type="checkbox" wire:model.live="selectedIds" value="{{ $project->id }}" aria-label="Sélectionner {{ $project->name }}"></td>
                    <td><div class="project-table-name"><span class="project-logo">{{ strtoupper(substr($project->name,0,1)) }}</span><div><strong>{{ $project->name }}</strong><a href="{{ $project->website_url }}" target="_blank" rel="noopener">{{ parse_url($project->website_url,PHP_URL_HOST) }} ↗</a></div></div></td>
                    <td>{{ $project->country }} · {{ $project->currency }}</td>
                    <td><span class="state-badge">{{ $project->status }}</span></td>
                    <td><strong class="metric-cell">{{ $project->source_pages_count }}</strong></td>
                    <td><strong class="metric-cell">{{ $project->keywords_count }}</strong></td>
                    <td><strong class="metric-cell">{{ $project->articles_count }}</strong></td>
                    <td><span class="{{ $project->affiliate_url ? 'verified':'muted' }}">{{ $project->affiliate_url ? '✓ configurée' : '—' }}</span></td>
                    <td>{{ $project->last_crawled_at?->diffForHumans() ?? 'Jamais' }}</td>
                    <td><button class="table-action button-link" type="button" wire:click="editProject({{ $project->id }})">Modifier →</button></td>
                </tr>
            @empty<tr><td colspan="10" class="empty-state">Aucun projet ne correspond à cette recherche.</td></tr>@endforelse
        </tbody></table></div>
    </article>
</div>
