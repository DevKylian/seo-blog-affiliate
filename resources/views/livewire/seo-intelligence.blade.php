@php
    $typeLabels = [
        'refresh_content' => 'Renforcer contenu',
        'rewrite_title_meta' => 'Title/meta',
        'add_section' => 'Nouvelle section',
        'conversion_review' => 'Conversion',
        'content_gap' => 'Content gap',
        'indexing_followup' => 'Indexation',
    ];
@endphp

<div class="dashboard-page os-page seo-intelligence-page">
    <section class="page-heading">
        <div>
            <span class="eyebrow dark">Boucle SEO</span>
            <h1>SEO Intelligence</h1>
            <p>Search Console, Bing, actions éditoriales et différenciation automatique des articles.</p>
        </div>
        <div class="audit-actions">
            <button type="button" class="secondary-button" wire:click="analyzeWithoutImport" wire:loading.attr="disabled">Analyser les données locales</button>
            <button type="button" class="primary-button" wire:click="launchLoop" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="launchLoop">Lancer la boucle SEO</span>
                <span wire:loading wire:target="launchLoop">Démarrage...</span>
            </button>
        </div>
    </section>

    @if($message)<div class="alert success">✓ {{ $message }}</div>@endif
    @if($error)<div class="alert danger">{{ $error }}</div>@endif

    <section class="seo-kpi-grid">
        <article class="panel seo-kpi-card"><span>Impressions 28j</span><strong>{{ number_format($stats['impressions'], 0, ',', ' ') }}</strong><small>{{ number_format($stats['snapshots'], 0, ',', ' ') }} lignes importées</small></article>
        <article class="panel seo-kpi-card"><span>Clics 28j</span><strong>{{ number_format($stats['clicks'], 0, ',', ' ') }}</strong><small>CTR moyen {{ number_format($stats['ctr'], 2, ',', ' ') }} %</small></article>
        <article class="panel seo-kpi-card"><span>Position moyenne</span><strong>{{ $stats['position'] ?: '—' }}</strong><small>Dernier import : {{ $stats['last_import'] ? \Illuminate\Support\Carbon::parse($stats['last_import'])->diffForHumans() : 'jamais' }}</small></article>
        <article class="panel seo-kpi-card"><span>Actions ouvertes</span><strong>{{ number_format($stats['queued_actions'], 0, ',', ' ') }}</strong><small>Priorité faible = plus urgent</small></article>
    </section>

    <article class="panel seo-actions-panel">
        <div class="panel-head">
            <div>
                <h2>Actions prioritaires</h2>
                <p>Les opportunités viennent des impressions, positions, CTR et trous de couverture.</p>
            </div>
            <div class="filters-row">
                <select wire:model.live="projectId">
                    <option value="">Tous les projets</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="status">
                    <option value="">Tous statuts</option>
                    <option value="queued">À traiter</option>
                    <option value="in_progress">En cours</option>
                    <option value="done">Terminé</option>
                    <option value="dismissed">Ignoré</option>
                </select>
                <select wire:model.live="type">
                    <option value="">Tous types</option>
                    @foreach($typeLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <div class="search-box"><span>⌕</span><input wire:model.live.debounce.300ms="search" type="search" placeholder="Rechercher..."></div>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Page / requête</th>
                        <th>Signal</th>
                        <th>Priorité</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($actionItems as $item)
                        @php($evidence = $item->evidence ?? [])
                        <tr>
                            <td>
                                <div class="source-cell">
                                    <strong>{{ $item->title }}</strong>
                                    <span>{{ $typeLabels[$item->type] ?? str_replace('_', ' ', $item->type) }} · {{ $item->project?->name ?: 'projet auto' }}</span>
                                    @if($item->description)<small>{{ Str::limit($item->description, 130) }}</small>@endif
                                </div>
                            </td>
                            <td>
                                <div class="source-cell">
                                    <strong>{{ $item->article?->title ?: 'Nouvelle page ou section à créer' }}</strong>
                                    <span>{{ data_get($evidence, 'query') ?: $item->keyword?->keyword ?: 'requête à cadrer' }}</span>
                                    @if($item->article)<small>/{{ $item->article->slug }}</small>@endif
                                </div>
                            </td>
                            <td>
                                <div class="seo-signal-stack">
                                    <span>{{ number_format((int) data_get($evidence, 'impressions', 0), 0, ',', ' ') }} imp.</span>
                                    <span>{{ number_format((int) data_get($evidence, 'clicks', 0), 0, ',', ' ') }} clics</span>
                                    <span>pos. {{ data_get($evidence, 'position') ? number_format((float) data_get($evidence, 'position'), 1, ',', ' ') : '—' }}</span>
                                </div>
                            </td>
                            <td><span class="priority-pill">{{ $item->priority }}</span></td>
                            <td>
                                <div class="article-table-actions">
                                    @if($item->article)
                                        <button class="table-action button-link" type="button" wire:click="buildBrief({{ $item->article->id }})" wire:loading.attr="disabled">Brief</button>
                                        <a class="table-action" href="{{ route('admin.articles.edit', $item->article) }}" wire:navigate>Modifier →</a>
                                    @endif
                                    @if($item->status === 'queued')
                                        <button class="table-action button-link" type="button" wire:click="completeAction({{ $item->id }})">Terminer</button>
                                        <button class="table-action button-link muted-action" type="button" wire:click="dismissAction({{ $item->id }})">Ignorer</button>
                                    @else
                                        <span class="state-badge {{ $item->status }}">{{ str_replace('_', ' ', $item->status) }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Aucune action SEO pour ces filtres.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $actionItems->links() }}</div>
    </article>

    <section class="seo-two-columns">
        <article class="panel">
            <div class="panel-head">
                <div>
                    <h2>Requêtes qui comptent</h2>
                    <p>Top impressions agrégées sur les 28 derniers jours importés.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Requête</th><th>Imp.</th><th>Clics</th><th>Position</th></tr></thead>
                    <tbody>
                        @forelse($topQueries as $query)
                            <tr>
                                <td><strong class="table-title">{{ $query->query }}</strong></td>
                                <td>{{ number_format((int) $query->impressions, 0, ',', ' ') }}</td>
                                <td>{{ number_format((int) $query->clicks, 0, ',', ' ') }}</td>
                                <td>{{ number_format((float) $query->position, 1, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">Aucune donnée Search Console/Bing importée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="panel">
            <div class="panel-head">
                <div>
                    <h2>Briefs différenciants</h2>
                    <p>Angles anti-doublon injectables dans les prochaines générations.</p>
                </div>
            </div>
            <div class="seo-brief-list">
                @forelse($briefs as $brief)
                    <div class="seo-brief-item">
                        <strong>{{ $brief->article?->title ?: $brief->primary_keyword }}</strong>
                        <span>{{ $brief->project?->name }} · {{ $brief->generated_at?->diffForHumans() }}</span>
                        <p>{{ Str::limit($brief->prompt_directive, 220) }}</p>
                    </div>
                @empty
                    <div class="empty-state">Aucun brief généré pour le moment.</div>
                @endforelse
            </div>
        </article>
    </section>
</div>
