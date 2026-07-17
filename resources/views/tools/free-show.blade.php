@extends('layouts.blog')
@section('title',$tool['title'])
@section('description',$tool['description'])
@section('content')
<article class="free-tool-page" data-tool="{{ $tool['type'] }}">
    <header>
        <a href="{{ route('free-tools.index') }}">Tous les outils gratuits</a>
        <span class="eyebrow dark">Outil gratuit</span>
        <h1>{{ $tool['title'] }}</h1>
        <p>{{ $tool['description'] }}</p>
    </header>

    @if($tool['type'] === 'tjm')
        <section class="tool-calculator" data-calculator="tjm">
            <label>Objectif annuel net souhaité<input type="number" data-input="goal" value="50000"></label>
            <label>Jours facturables par an<input type="number" data-input="days" value="180"></label>
            <label>Marge charges et imprévus (%)<input type="number" data-input="buffer" value="35"></label>
            <output><span>TJM estimé</span><strong data-output="result">-</strong></output>
        </section>
    @elseif($tool['type'] === 'revenu')
        <section class="tool-calculator" data-calculator="revenu">
            <label>Chiffre d'affaires mensuel<input type="number" data-input="revenue" value="5000"></label>
            <label>Charges estimées (%)<input type="number" data-input="charges" value="24"></label>
            <label>Dépenses mensuelles<input type="number" data-input="expenses" value="400"></label>
            <output><span>Revenu estimé avant impôt</span><strong data-output="result">-</strong></output>
        </section>
    @else
        <section class="checklist-tool">
            @foreach(['Vérifier le SIRET et les informations administratives','Ouvrir un compte dédié si nécessaire','Préparer un modèle de devis et facture','Lister les obligations URSSAF','Mettre en place le suivi des dépenses','Vérifier les seuils TVA','Choisir un outil pour centraliser la gestion'] as $item)
                <label><input type="checkbox"> {{ $item }}</label>
            @endforeach
        </section>
    @endif

    <aside class="tool-conversion">
        <strong>Après le calcul</strong>
        <p>Gardez le résultat comme base de décision, puis comparez les outils capables de suivre factures, dépenses et obligations sans ressaisie.</p>
        <a href="{{ route('tools.index') }}">{{ $tool['cta'] }}</a>
    </aside>
</article>

@if(in_array($tool['type'], ['tjm','revenu'], true))
<script>
(() => {
    const root = document.querySelector('[data-calculator="{{ $tool['type'] }}"]');
    if (!root) return;
    const number = name => Number(root.querySelector(`[data-input="${name}"]`)?.value || 0);
    const output = root.querySelector('[data-output="result"]');
    const format = value => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(Math.max(0, value));
    const compute = () => {
        if (root.dataset.calculator === 'tjm') {
            const goal = number('goal');
            const days = Math.max(1, number('days'));
            const buffer = 1 + (number('buffer') / 100);
            output.textContent = format((goal * buffer) / days) + ' / jour';
        } else {
            const revenue = number('revenue');
            const charges = revenue * (number('charges') / 100);
            const expenses = number('expenses');
            output.textContent = format(revenue - charges - expenses) + ' / mois';
        }
    };
    root.querySelectorAll('input').forEach(input => input.addEventListener('input', compute));
    compute();
})();
</script>
@endif
@endsection
