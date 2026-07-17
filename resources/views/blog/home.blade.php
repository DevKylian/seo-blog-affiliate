@extends('layouts.blog')
@section('title','FreelanceOS - gérer son activité freelance')
@section('description','Facturation, comptabilité, TVA, déclarations, statuts et outils gratuits pour indépendants en France.')
@section('content')
<section class="freelance-hero">
    <div class="hero-copy">
        <span class="eyebrow dark">FreelanceOS</span>
        <h1>La base de travail pour piloter une activité freelance proprement.</h1>
        <p>Facturation, TVA, comptabilité, statuts et choix logiciels : des guides courts quand il faut comprendre, des dossiers profonds quand il faut décider, et des outils gratuits quand il faut calculer.</p>
        <div class="hero-actions">
            <a href="{{ route('blog.index') }}">Explorer les guides</a>
            <a href="{{ route('free-tools.index') }}">Utiliser les outils</a>
        </div>
    </div>
    <aside class="hero-dashboard" aria-label="Synthese editorialisee FreelanceOS">
        <div class="hero-ledger-head">
            <span>Dossier actif</span>
            <strong>Facturation freelance</strong>
        </div>
        <div class="hero-ledger">
            <span>Intention</span><b>Comprendre puis choisir</b>
            <span>Sources</span><b>Pages officielles</b>
            <span>Données prix</span><b>Vérifiées et datées</b>
            <span>Sortie utile</span><b>Guide + outil + CTA</b>
        </div>
        <p>Le site part du problème métier avant de parler logiciel. Les comparatifs restent utiles parce que les prix, limites et sources sont affichés.</p>
    </aside>
</section>

<section class="freelance-section">
    <div class="freelance-section-head">
        <span class="eyebrow dark">Que cherchez-vous à faire ?</span>
        <h2>Choisissez votre situation, pas une catégorie de blog.</h2>
    </div>
    <div class="intent-grid">
        <a href="{{ route('blog.index', ['cluster' => 'creation']) }}"><small>01</small><strong>Je lance mon activité</strong><span>Statut, démarches, premiers chiffres et erreurs à éviter.</span></a>
        <a href="{{ route('blog.index', ['cluster' => 'facturation']) }}"><small>02</small><strong>Je facture un client</strong><span>Devis, mentions obligatoires, facture conforme et relance.</span></a>
        <a href="{{ route('blog.index', ['cluster' => 'comptabilite']) }}"><small>03</small><strong>Je range ma compta</strong><span>Dépenses, justificatifs, obligations et organisation mensuelle.</span></a>
        <a href="{{ route('blog.index', ['cluster' => 'tva']) }}"><small>04</small><strong>Je passe un cap</strong><span>TVA, seuils, charges, changement de statut et outils adaptés.</span></a>
    </div>
</section>

<section class="freelance-section freelance-journey">
    <div class="freelance-section-head">
        <span class="eyebrow dark">Parcours freelance</span>
        <h2>Une progression claire, du premier client à la gestion régulière.</h2>
    </div>
    <ol>
        <li><strong>Je crée mon activité</strong><span>Guides piliers, checklists et articles associés.</span></li>
        <li><strong>Je trouve mes premiers clients</strong><span>Statut, TJM, devis et contrats.</span></li>
        <li><strong>Je facture correctement</strong><span>Factures, mentions, TVA et suivi de paiement.</span></li>
        <li><strong>Je gère ma comptabilité</strong><span>Dépenses, déclarations et outils.</span></li>
        <li><strong>J'optimise mon activité</strong><span>TVA, charges, changement de statut et comparatifs.</span></li>
    </ol>
</section>

<section class="freelance-section">
    <div class="freelance-section-head">
        <span class="eyebrow dark">Outils gratuits</span>
        <h2>Des calculateurs simples pour prendre une décision tout de suite.</h2>
    </div>
    <div class="free-tool-grid">
        <a href="{{ route('free-tools.show','calculateur-tjm-freelance') }}"><strong>Calculateur TJM freelance</strong><span>Combien facturer par jour pour atteindre votre objectif ?</span></a>
        <a href="{{ route('free-tools.show','calculateur-revenu-freelance') }}"><strong>Calculateur revenu freelance</strong><span>Combien vous reste-t-il après charges ?</span></a>
        <a href="{{ route('free-tools.show','checklist-creation-micro-entreprise') }}"><strong>Checklist création freelance</strong><span>Toutes les étapes après votre SIRET.</span></a>
    </div>
</section>

<section class="freelance-section">
    <div class="freelance-section-head">
        <span class="eyebrow dark">Ressources utiles</span>
        <h2>Les derniers dossiers publiés.</h2>
    </div>
    <div class="blog-grid">
        @forelse($latestArticles as $article)
            <article class="blog-card">
                <div class="blog-card-top"><span>{{ str_replace('_',' ',$article->type) }}</span><time>{{ $article->published_at?->translatedFormat('d M Y') }}</time></div>
                <h3><a href="{{ $article->public_url }}">{{ $article->title }}</a></h3>
                <p>{{ $article->meta_description }}</p>
                <footer><span>{{ $article->project->name }}</span><a href="{{ $article->public_url }}">Lire le guide</a></footer>
            </article>
        @empty
            <div class="blog-empty"><span>✦</span><h2>Les premiers guides arrivent</h2><p>Importez vos mots-clés Semrush puis lancez la factory.</p></div>
        @endforelse
    </div>
</section>

<section class="freelance-section indy-transition">
    <div>
        <span class="eyebrow dark">Et quand vous voulez automatiser</span>
        <h2>Vous préférez vous concentrer sur vos missions plutôt que sur l'administratif ?</h2>
        <p>Indy permet aux indépendants de centraliser leur facturation, leurs dépenses et leur comptabilité dans un seul outil.</p>
    </div>
    <ul>
        <li>Facturation</li>
        <li>Suivi des transactions</li>
        <li>Gestion administrative</li>
        <li>Comptabilité simplifiée</li>
    </ul>
</section>

<section class="freelance-section trust-band">
    <strong>Des guides vérifiés</strong>
    <span>Sources officielles utilisées</span>
    <span>Informations mises à jour régulièrement</span>
    <span>Exemples concrets pour indépendants</span>
    <span>Outils gratuits développés pour freelances</span>
</section>
@endsection
