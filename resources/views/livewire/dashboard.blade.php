<div class="dashboard-page os-page">
    <section class="page-heading">
        <div><span class="eyebrow dark">SEO Affiliate Content OS</span><h1>Bonjour, {{ explode(' ', auth()->user()->name)[0] }} <span>👋</span></h1><p>Collectez des preuves, priorisez vos opportunités et générez des brouillons vérifiables.</p></div>
        <a class="primary-link" href="{{ route('admin.automation') }}" wire:navigate>✦ Lancer une campagne</a>
    </section>

    @unless($hasApiKey)
        <a class="setup-banner" href="{{ route('admin.settings') }}" wire:navigate><span>✦</span><div><strong>Connectez Gemini pour activer la génération</strong><p>Ajoutez votre clé API dans les réglages. Elle sera chiffrée côté serveur.</p></div><b>Configurer →</b></a>
    @endunless

    <section class="stat-grid">
        <article class="stat-card accent-purple"><div class="stat-icon">▦</div><div><span>Projets actifs</span><strong>{{ $stats['projects'] }}</strong><small>Outils suivis</small></div></article>
        <article class="stat-card accent-blue"><div class="stat-icon">✓</div><div><span>Sources vérifiées</span><strong>{{ $stats['sources'] }}</strong><small>Base de preuves</small></div></article>
        <article class="stat-card accent-orange"><div class="stat-icon">⌕</div><div><span>Mots-clés</span><strong>{{ $stats['keywords'] }}</strong><small>Opportunités Semrush</small></div></article>
        <article class="stat-card accent-green"><div class="stat-icon">✦</div><div><span>Contenus</span><strong>{{ $stats['articles'] }}</strong><small>Brouillons générés</small></div></article>
    </section>



    <section class="workflow-strip">
        <a href="{{ route('admin.projects') }}" wire:navigate><i>1</i><div><strong>Créer un projet</strong><span>Outil, tarifs, affiliation</span></div></a><b>→</b>
        <a href="{{ route('admin.research') }}" wire:navigate><i>2</i><div><strong>Collecter les preuves</strong><span>Sources, prix, limites</span></div></a><b>→</b>
        <a href="{{ route('admin.keywords') }}" wire:navigate><i>3</i><div><strong>Importer Semrush</strong><span>Intentions & opportunités</span></div></a><b>→</b>
        <a href="{{ route('admin.automation') }}" wire:navigate><i>4</i><div><strong>Flux automatique</strong><span>Génération IA</span></div></a>
    </section>

    <section class="dashboard-grid wide-side">
        <article class="panel">
            <div class="panel-head"><div><h2>Projets</h2><p>État de votre portefeuille éditorial</p></div><a class="text-link" href="{{ route('admin.projects') }}" wire:navigate>Tout voir →</a></div>
            <div class="table-wrap"><table><thead><tr><th>Application</th><th>Sources</th><th>Mots-clés</th><th>Contenus</th><th>Dernier crawl</th></tr></thead><tbody>
            @forelse($projects as $project)<tr><td><div class="project-cell"><span>{{ strtoupper(substr($project->name,0,1)) }}</span><div><strong>{{ $project->name }}</strong><small>{{ parse_url($project->website_url, PHP_URL_HOST) }}</small></div></div></td><td>{{ $project->source_pages_count }}</td><td>{{ $project->keywords_count }}</td><td>{{ $project->articles_count }}</td><td>{{ $project->last_crawled_at?->diffForHumans() ?? 'Jamais' }}</td></tr>@empty<tr><td colspan="5" class="empty-state">Créez votre premier projet pour démarrer.</td></tr>@endforelse
            </tbody></table></div>
        </article>
        <aside class="panel activity-panel"><div class="panel-head"><div><h2>Derniers accès admin</h2><p>Journal de sécurité</p></div><a class="text-link" href="{{ route('admin.logs') }}" wire:navigate>Logs →</a></div>
            <div class="activity-list">@forelse($logs as $log)<div><i class="method-dot {{ strtolower($log->method) }}"></i><p><strong>{{ $log->user?->name ?? 'Compte supprimé' }}</strong><span>{{ $log->method }} {{ $log->path }}</span></p><time>{{ $log->created_at->diffForHumans() }}</time></div>@empty<p class="empty-state">Aucun accès journalisé.</p>@endforelse</div>
        </aside>
    </section>
</div>
