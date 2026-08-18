<div>
    <div class="dashboard-page os-page">
    <section class="page-heading">
        <div><span class="eyebrow dark">Intelligence Éditoriale</span><h1>Pipeline Editorial (Agents)</h1><p>Gérez la création de contenu stratégique avec des agents autonomes et des validations humaines.</p></div>
    </section>

    <details class="panel project-form-disclosure" open>
        <summary><div><strong>Lancer une nouvelle thématique</strong><span>Stratégie, analyse, architecture et rédaction (ex: Indy Comptabilité)</span></div></summary>
        <form wire:submit="createPipeline" class="os-form">
            <div class="field full">
                <label for="theme">Nouvelle Thématique</label>
                <input type="text" wire:model="newTheme" id="theme" placeholder="ex: Indy Comptabilité">
                @error('newTheme')<small class="field-error">{{ $message }}</small>@enderror
            </div>
            <button class="primary-button form-submit" type="submit">Lancer Agent Stratégie →</button>
        </form>
    </details>

    <article class="panel">
        <div class="panel-head"><div><h2>Pipelines en cours</h2><p>État d'avancement des différentes thématiques</p></div></div>
        
        <div class="table-wrap">
            <table>
                <thead><tr><th>Thématique</th><th>Statut</th><th>Agent Actif</th><th>Actions / Instructions</th></tr></thead>
                <tbody>
                    @forelse($pipelines as $pipeline)
                        <tr>
                            <td><strong>{{ $pipeline->theme }}</strong></td>
                            <td><span class="state-badge">{{ $pipeline->status }}</span></td>
                            <td><strong>{{ $pipeline->current_agent ?? 'Aucun' }}</strong></td>
                            <td>
                                <a href="{{ route('admin.editorial-pipeline.show', $pipeline->id) }}" wire:navigate class="text-link" style="font-weight: 500;">
                                    Ouvrir le pipeline →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty-state">Aucun pipeline en cours.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</div>
