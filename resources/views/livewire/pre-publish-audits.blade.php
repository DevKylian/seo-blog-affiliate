<div class="dashboard-page audit-page">
    <section class="page-heading">
        <div>
            <span class="eyebrow dark">Contrôle qualité</span>
            <h1>Audits pré-publication</h1>
            <p>Bloquez les contenus risqués, rafraîchissez les sources et gardez les comparatifs propres avant mise en ligne.</p>
        </div>
        <div class="audit-actions">
            <button type="button" class="secondary-button" wire:click="planRefresh" wire:loading.attr="disabled">Planifier les refreshs</button>
            <button type="button" class="primary-button" wire:click="auditVisible" wire:loading.attr="disabled">Auditer la page</button>
        </div>
    </section>

    @if($message)<div class="alert success">✓ {{ $message }}</div>@endif
    @if($error)<div class="alert danger">{{ $error }}</div>@endif

    <article class="panel">
        <div class="panel-head">
            <div>
                <h2>File de validation</h2>
                <p>Articles en revue, planifiés ou publiés avec leur dernier verdict qualité.</p>
            </div>
            <div class="filters-row">
                <select wire:model.live="projectId">
                    <option value="">Tous les projets</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="auditStatus">
                    <option value="">Tous les audits</option>
                    <option value="not_audited">Non audité</option>
                    <option value="blocked">Bloqué</option>
                    <option value="needs_review">À relire</option>
                    <option value="passed">Validé</option>
                </select>
                <div class="search-box"><span>⌕</span><input wire:model.live.debounce.300ms="search" type="search" placeholder="Rechercher…"></div>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Audit</th>
                        <th>Blocages</th>
                        <th>Refresh</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($articles as $article)
                        @php($audit = $article->latestAudit)
                        <tr>
                            <td>
                                <div class="source-cell article-title-cell">
                                    <strong title="{{ $article->title }}">{{ $article->title }}</strong>
                                    <span>{{ $article->project?->name }} · {{ $article->primary_keyword ?: $article->keyword?->keyword ?: 'mot-clé libre' }}</span>
                                    <small>/{{ $article->slug }}</small>
                                </div>
                            </td>
                            <td>
                                <div class="audit-status-cell">
                                    <span class="state-badge {{ $article->prepublish_status ?: 'not_audited' }}">{{ str_replace('_', ' ', $article->prepublish_status ?: 'not_audited') }}</span>
                                    <strong>{{ $article->prepublish_score !== null ? number_format($article->prepublish_score, 1, ',', ' ') : '—' }}/100</strong>
                                    <small>{{ $article->prepublish_audited_at?->format('d/m/Y H:i') ?? 'Jamais audité' }}</small>
                                </div>
                            </td>
                            <td>
                                <div class="audit-reasons">
                                    @forelse(array_slice($audit?->blocking_reasons ?? [], 0, 3) as $reason)
                                        <span>{{ Str::limit($reason, 110) }}</span>
                                    @empty
                                        <span class="muted">Aucun blocage enregistré.</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <div class="audit-status-cell">
                                    <strong>{{ $article->refresh_status ?: '—' }}</strong>
                                    <small>{{ $article->refresh_reason ? Str::limit($article->refresh_reason, 90) : 'Pas de refresh demandé' }}</small>
                                </div>
                            </td>
                            <td>
                                <div class="article-table-actions">
                                    <button class="table-action button-link" type="button" wire:click="auditArticle({{ $article->id }})" wire:loading.attr="disabled">Auditer</button>
                                    <a class="table-action" href="{{ route('admin.articles.edit', $article) }}" wire:navigate>Modifier →</a>
                                    <a class="table-action view-action" href="{{ $article->status === 'published' ? $article->public_url : route('admin.articles.preview', $article) }}" target="_blank" rel="noopener">Voir ↗</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Aucun article à auditer.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $articles->links() }}</div>
    </article>

    <article class="panel audit-refresh-panel">
        <div class="panel-head">
            <div>
                <h2>Refresh queue</h2>
                <p>Sources tarifaires, claims et contenus à reprendre automatiquement.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Tâche</th><th>Cible</th><th>Priorité</th><th>Statut</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse($refreshTasks as $task)
                        <tr>
                            <td><strong class="table-title">{{ str_replace('_', ' ', $task->reason) }}</strong></td>
                            <td>
                                <div class="source-cell">
                                    <strong>{{ $task->article?->title ?: $task->sourcePage?->title ?: $task->project?->name }}</strong>
                                    @if($task->sourcePage)<span>{{ Str::limit($task->sourcePage->url, 80) }}</span>@endif
                                </div>
                            </td>
                            <td>{{ $task->priority }}</td>
                            <td><span class="state-badge {{ $task->status }}">{{ $task->status }}</span></td>
                            <td>{{ $task->scheduled_at?->format('d/m/Y H:i') ?? $task->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Aucune tâche de refresh.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</div>
