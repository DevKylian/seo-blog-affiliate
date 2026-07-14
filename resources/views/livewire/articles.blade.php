<div class="dashboard-page os-page">
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
                <select wire:model.live="duplicateFilter"><option value="">Tous les contenus</option><option value="potential">Doublons potentiels</option><option value="merged">Doublons fusionnés</option><option value="ignored">Exceptions ignorées</option></select>
                <select wire:model.live="status"><option value="">Tous les statuts</option><option value="draft">Brouillon</option><option value="review">À relire</option><option value="scheduled">Programmé</option><option value="published">Publié</option><option value="archived">Archivé</option></select>
                <div class="search-box"><span>⌕</span><input wire:model.live.debounce.300ms="search" type="search" placeholder="Rechercher…"></div>
            </div>
        </div>
        @if($selectedIds)<div class="bulk-toolbar"><strong>{{ count($selectedIds) }} sélectionné(s)</strong><button type="button" wire:click="clearSelection">Annuler</button><button class="danger" type="button" wire:click="deleteSelected" wire:confirm="Supprimer définitivement les articles sélectionnés, y compris leurs versions et relations ?">Supprimer</button></div>@endif
        <div class="table-wrap"><table><thead><tr><th class="selection-cell"><input type="checkbox" wire:model.live="selectAll" aria-label="Tout sélectionner"></th><th>Article</th><th>Empreinte</th><th>Statut</th><th>Doublon</th><th>Publication</th><th></th></tr></thead><tbody>
            @forelse($articles as $article)
                <tr class="{{ in_array($article->id,$selectedIds) ? 'is-selected':'' }}">
                    <td class="selection-cell"><input type="checkbox" wire:model.live="selectedIds" value="{{ $article->id }}" aria-label="Sélectionner {{ $article->title }}"></td>
                    <td><div class="source-cell article-title-cell"><div class="title-with-kd"><strong title="{{ $article->title }}">{{ $article->title }}</strong>@if($article->keyword?->hasMeasuredDifficulty())<span class="kd-badge {{ $article->keyword->keyword_difficulty <= 30 ? 'low' : ($article->keyword->keyword_difficulty <= 50 ? 'medium' : 'high') }}">KD {{ number_format($article->keyword->keyword_difficulty,0) }}</span>@elseif($article->keyword)<span class="kd-badge">KD —</span>@endif</div><span>/{{ $article->slug }}</span></div></td>
                    <td><div class="fingerprint-cell"><strong>{{ $article->topic_key ?: 'Non calculée' }}</strong><span>{{ $article->search_intent ?: '—' }} · {{ $article->content_angle ?: '—' }}</span></div></td>
                    <td><span class="state-badge {{ $article->status }}">{{ $article->status }}</span></td>
                    <td>@if($article->duplicate_status)<div class="duplicate-cell"><strong>{{ $article->duplicate_score ? round($article->duplicate_score).' %' : str_replace('_',' ',$article->duplicate_status) }}</strong>@if($article->canonicalArticle)<span>Similaire à #{{ $article->canonicalArticle->id }} — {{ Str::limit($article->canonicalArticle->title,35) }}</span>@endif @if(in_array($article->duplicate_status,['potential','needs_differentiation']))<div><button type="button" wire:click="mergeDuplicate({{ $article->id }})" wire:confirm="Fusionner les meilleures sections dans l’article canonique puis archiver ce brouillon ?">Fusionner</button><a href="{{ route('admin.articles.edit',$article) }}" wire:navigate>Modifier l’angle</a><button type="button" wire:click="archiveDuplicate({{ $article->id }})">Archiver</button><button type="button" wire:click="ignoreDuplicate({{ $article->id }})">Ignorer</button></div>@endif</div>@else—@endif</td>
                    <td>{{ $article->published_at?->format('d/m/Y H:i') ?? $article->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td><div class="article-table-actions"><a class="table-action view-action" href="{{ $article->status === 'published' ? $article->public_url : route('admin.articles.preview',$article) }}" target="_blank" rel="noopener">Voir ↗</a><a class="table-action" href="{{ route('admin.articles.edit',$article) }}" wire:navigate>Modifier →</a></div></td>
                </tr>
            @empty<tr><td colspan="7" class="empty-state">Aucun article.</td></tr>@endforelse
        </tbody></table></div><div class="pagination">{{ $articles->links() }}</div>
    </article>
</div>
