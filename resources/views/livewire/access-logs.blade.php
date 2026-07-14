<div class="dashboard-page os-page">
    <section class="page-heading"><div><span class="eyebrow dark">Audit & sécurité</span><h1>Logs d’accès admin</h1><p>Traçabilité des pages consultées et actions Livewire réalisées dans l’administration.</p></div></section>
    @if($message)<div class="alert success">✓ {{ $message }}</div>@endif
    <article class="panel">
        <div class="panel-head"><div><h2>Journal récent</h2><p>Utilisateur, route, IP, statut et temps de réponse</p></div><div class="search-box"><span>⌕</span><input wire:model.live.debounce.300ms="search" type="search" placeholder="Route ou IP…"></div></div>
        @if($selectedIds)<div class="bulk-toolbar"><strong>{{ count($selectedIds) }} sélectionné(s)</strong><button type="button" wire:click="clearSelection">Annuler</button><button class="danger" type="button" wire:click="deleteSelected" wire:confirm="Supprimer définitivement les entrées de journal sélectionnées ?">Supprimer</button></div>@endif
        <div class="table-wrap"><table><thead><tr><th class="selection-cell"><input type="checkbox" wire:model.live="selectAll" aria-label="Tout sélectionner"></th><th>Date</th><th>Administrateur</th><th>Requête</th><th>Route</th><th>IP</th><th>Statut</th><th>Durée</th></tr></thead><tbody>
            @forelse($logs as $log)<tr class="{{ in_array($log->id,$selectedIds) ? 'is-selected':'' }}"><td class="selection-cell"><input type="checkbox" wire:model.live="selectedIds" value="{{ $log->id }}" aria-label="Sélectionner le log {{ $log->id }}"></td><td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td><td><div class="user-cell"><span class="table-avatar">{{ strtoupper(substr($log->user?->name ?? '?',0,1)) }}</span><div><strong>{{ $log->user?->name ?? 'Compte supprimé' }}</strong><span>{{ Str::limit($log->user_agent,45) }}</span></div></div></td><td><span class="method-badge {{ strtolower($log->method) }}">{{ $log->method }}</span> {{ Str::limit($log->path,55) }}</td><td>{{ $log->route_name ?: '—' }}</td><td>{{ $log->ip_address }}</td><td><span class="http-status {{ $log->status_code >= 400 ? 'bad':'' }}">{{ $log->status_code }}</span></td><td>{{ $log->duration_ms }} ms</td></tr>
            @empty<tr><td colspan="8" class="empty-state">Aucun accès enregistré.</td></tr>@endforelse
        </tbody></table></div><div class="pagination">{{ $logs->links() }}</div>
    </article>
</div>
