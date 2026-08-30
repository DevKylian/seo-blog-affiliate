@php($hasActiveRegeneration = $articles->getCollection()->contains(fn ($article) => in_array(data_get($article->quality_checks, 'regeneration_status'), ['queued', 'processing'], true)))
<div class="dashboard-page os-page" @if($hasActiveRegeneration) wire:poll.5s @endif>
    <section class="page-heading">
        <div><span class="eyebrow dark">CMS interne</span><h1>Articles</h1><p>Créez, relisez et gouvernez les sujets, intentions et angles du blog Laravel.</p></div>
        <a class="primary-link" href="{{ route('admin.articles.create') }}" wire:navigate>＋ Nouvel article</a>
    </section>
    @if($message)<div class="alert success">✓ {{ $message }}</div>@endif
    @if($error)<div class="alert danger">{{ $error }}</div>@endif
    <article class="panel">
        <div class="panel-head">
            <div><h2>Bibliothèque éditoriale</h2><p>Brouillons, contenus publiés et gouvernance des doublons</p></div>
            <div class="filters-row">
                <button type="button" wire:click="toggleMassView" class="filter-today-btn {{ $massView ? 'is-active' : '' }}" title="Activer l'affichage minimaliste de masse">
                    <span class="icon">≡</span>
                    <span>Masse</span>
                </button>
                <button type="button" wire:click="toggleToday" class="filter-today-btn {{ $todayOnly ? 'is-active' : '' }}" title="Filtrer les articles créés ou publiés aujourd'hui">
                    <span class="icon">⚡</span>
                    <span>Aujourd'hui</span>
                </button>
                <select wire:model.live="duplicateFilter"><option value="">Tous les contenus</option><option value="potential">Doublons potentiels</option><option value="merged">Doublons fusionnés</option><option value="ignored">Exceptions ignorées</option></select>
                <select wire:model.live="typeFilter"><option value="">Tous les types</option><option value="article">Article classique</option><option value="pilier">Page Pilier (Hub)</option><option value="guide">Guide</option><option value="review">Avis</option><option value="comparison">Comparatif</option></select>
<select wire:model.live="status"><option value="">Tous les statuts</option><option value="draft">Brouillon</option><option value="review">À relire</option><option value="scheduled">Programmé</option><option value="published">Publié</option><option value="archived">Archivé</option></select>
                <div class="search-box"><span>⌕</span><input wire:model.live.debounce.300ms="search" type="search" placeholder="Rechercher…"></div>
            </div>
        </div>
        @if($selectedIds)<div class="bulk-toolbar"><strong>{{ count($selectedIds) }} sélectionné(s)</strong><button type="button" wire:click="clearSelection">Annuler</button><button type="button" style="background: #10b981; color: white; border-color: #10b981;" wire:click="publishSelected" wire:confirm="Publier les articles sélectionnés maintenant ?">Publier</button><button class="danger" type="button" wire:click="deleteSelected" wire:confirm="Supprimer définitivement les articles sélectionnés, y compris leurs versions et relations ?">Supprimer</button></div>@endif
        @if($massView)
        <div x-data="{
            copyAll() {
                const text = Array.from(document.querySelectorAll('.mass-article-row'))
                    .map(row => row.dataset.title + '\t' + row.dataset.slug)
                    .join('\n');
                navigator.clipboard.writeText(text).then(() => { alert('Copié dans le presse-papier !') });
            }
        }" style="margin-bottom: 1rem; display: flex; justify-content: flex-end;">
            <button type="button" @click="copyAll()" class="primary-link" style="background:none;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-weight:bold;">📋 Copier {{ count($articles) }} titre(s) / slug(s)</button>
        </div>
        <livewire:markdown-importer />
        <div class="table-wrap">
            <table class="minimalist-table" style="width:100%; border-collapse: collapse;">
                <thead><tr><th style="text-align:left;padding:8px;border-bottom:1px solid #eee;">Titre</th><th style="text-align:left;padding:8px;border-bottom:1px solid #eee;">Slug</th><th style="text-align:left;padding:8px;border-bottom:1px solid #eee;">Statut</th></tr></thead>
                <tbody>
                    @forelse($articles as $article)
                        <tr class="mass-article-row" data-title="{{ $article->title }}" data-slug="{{ $article->slug }}">
                            <td style="padding:8px;border-bottom:1px solid #f9f9f9;"><strong>{{ $article->title }}</strong></td>
                            <td style="padding:8px;border-bottom:1px solid #f9f9f9;"><span style="color: #666;">{{ $article->slug }}</span></td>
                            <td style="padding:8px;border-bottom:1px solid #f9f9f9;"><span class="state-badge {{ $article->status }}">{{ $article->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-state">Aucun article.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @else
        <livewire:markdown-importer />
        <div class="table-wrap"><table><thead><tr><th class="selection-cell"><input type="checkbox" wire:model.live="selectAll" aria-label="Tout sélectionner"></th><th><button type="button" style="background:none;border:none;color:inherit;font:inherit;cursor:pointer;padding:0;display:inline-flex;align-items:center;gap:4px;" wire:click="sortBy('title')">Article @if($sortField === 'title') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif</button></th><th>Empreinte</th><th><button type="button" style="background:none;border:none;color:inherit;font:inherit;cursor:pointer;padding:0;display:inline-flex;align-items:center;gap:4px;" wire:click="sortBy('status')">Statut @if($sortField === 'status') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif</button></th><th>Doublon</th><th><button type="button" style="background:none;border:none;color:inherit;font:inherit;cursor:pointer;padding:0;display:inline-flex;align-items:center;gap:4px;" wire:click="sortBy('views')">Vues @if($sortField === 'views') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif</button></th><th><button type="button" style="background:none;border:none;color:inherit;font:inherit;cursor:pointer;padding:0;display:inline-flex;align-items:center;gap:4px;" wire:click="sortBy('id')">Publication @if($sortField === 'id') {!! $sortDirection === 'asc' ? '↑' : '↓' !!} @endif</button></th><th></th></tr></thead><tbody>
            @forelse($articles as $article)
                <tr class="{{ in_array($article->id,$selectedIds) ? 'is-selected':'' }}">
                    <td class="selection-cell"><input type="checkbox" wire:model.live="selectedIds" value="{{ $article->id }}" aria-label="Sélectionner {{ $article->title }}"></td>
                    <td><div class="source-cell article-title-cell"><div class="title-with-kd"><strong title="{{ $article->title }}">{{ $article->title }}</strong>@if($article->keyword?->hasMeasuredDifficulty())<span class="kd-badge {{ $article->keyword->keyword_difficulty <= 30 ? 'low' : ($article->keyword->keyword_difficulty <= 50 ? 'medium' : 'high') }}">KD {{ number_format($article->keyword->keyword_difficulty,0) }}</span>@elseif($article->keyword)<span class="kd-badge">KD —</span>@endif @if(in_array(data_get($article->quality_checks, 'regeneration_status'), ['queued','processing'], true))<span class="state-badge scheduled">Régénération</span>@elseif(data_get($article->quality_checks, 'regeneration_status') === 'failed')<span class="state-badge archived" title="{{ data_get($article->quality_checks, 'regeneration_error') }}">Échec regen</span>@endif</div><span>/{{ $article->slug }}</span>@if(data_get($article->quality_checks, 'regeneration_status') === 'failed' && data_get($article->quality_checks, 'regeneration_error'))<small>{{ Str::limit(data_get($article->quality_checks, 'regeneration_error'), 130) }}</small>@endif</div></td>
                    <td><div class="fingerprint-cell"><strong>{{ $article->topic_key ?: 'Non calculée' }}</strong><span>{{ $article->search_intent ?: '—' }} · {{ $article->content_angle ?: '—' }}</span></div></td>
                    <td><span class="state-badge {{ $article->status }}">{{ $article->status }}</span></td>
                    <td>@if($article->duplicate_status)<div class="duplicate-cell"><strong>{{ $article->duplicate_score ? round($article->duplicate_score).' %' : str_replace('_',' ',$article->duplicate_status) }}</strong>@if($article->canonicalArticle)<span>Similaire à #{{ $article->canonicalArticle->id }} — {{ Str::limit($article->canonicalArticle->title,35) }}</span>@endif @if(in_array($article->duplicate_status,['potential','needs_differentiation']))<div><button type="button" wire:click="mergeDuplicate({{ $article->id }})" wire:confirm="Fusionner les meilleures sections dans l’article canonique puis archiver ce brouillon ?">Fusionner</button><a href="{{ route('admin.articles.edit',$article) }}" wire:navigate>Modifier l’angle</a><button type="button" wire:click="archiveDuplicate({{ $article->id }})">Archiver</button><button type="button" wire:click="ignoreDuplicate({{ $article->id }})">Ignorer</button></div>@endif</div>@else—@endif</td>
                    <td><strong>{{ number_format($article->views ?: 0) }}</strong></td>
                    <td>{{ $article->published_at?->format('d/m/Y H:i') ?? $article->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td><div class="article-table-actions"><a class="table-action view-action" href="{{ $article->status === 'published' ? $article->public_url : route('admin.articles.preview',$article) }}" target="_blank" rel="noopener">Voir ↗</a><a class="table-action" href="{{ route('admin.articles.edit',$article) }}" wire:navigate>Modifier →</a><button class="table-action button-link" type="button" wire:click="regenerate({{ $article->id }})" wire:loading.attr="disabled" wire:target="regenerate({{ $article->id }})" wire:confirm="Régénérer cet article avec l’IA ? Le contenu actuel sera archivé en version précédente, puis remplacé." @disabled(in_array(data_get($article->quality_checks, 'regeneration_status'), ['queued','processing'], true))><span wire:loading.remove wire:target="regenerate({{ $article->id }})">{{ in_array(data_get($article->quality_checks, 'regeneration_status'), ['queued','processing'], true) ? 'En cours...' : 'Régénérer' }}</span><span wire:loading wire:target="regenerate({{ $article->id }})">File...</span></button></div></td>
                </tr>
            @empty<tr><td colspan="8" class="empty-state">Aucun article.</td></tr>@endforelse
        </tbody></table></div>
        @endif
        <div class="pagination">{{ $articles->links() }}</div>
    </article>
</div>
