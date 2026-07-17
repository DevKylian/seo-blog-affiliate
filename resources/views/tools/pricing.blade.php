@extends('layouts.blog')

@section('title', 'Tarifs '.$tool->name.' : prix mensuels et annuels')
@section('description', 'Tarifs '.$tool->name.' vérifiés, facturation mensuelle et annuelle, historique et conditions connues.')

@push('head')
<script type="application/ld+json">{!! json_encode(app(\App\Services\StructuredDataService::class)->pricing($tool), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<article class="pricing-page">
    <header>
        <a href="{{ route('tools.show', $tool->slug) }}">Fiche {{ $tool->name }}</a>
        <span class="eyebrow dark">Tarifs vérifiés</span>
        <h1>Prix de {{ $tool->name }}</h1>
        <p>Les montants ci-dessous proviennent des dernières pages officielles collectées. Une confiance inférieure à 70 % exige une vérification humaine.</p>
    </header>

    <div class="pricing-public-grid">
        @forelse($tool->plans as $plan)
            <article>
                <div>
                    <span class="state-badge">{{ round($plan->confidence_score * 100) }}% confiance</span>
                    <h2>{{ $plan->name }}</h2>
                </div>
                <strong>{{ $plan->formattedPriceRange() }}</strong>
                <p>{{ $plan->displayPeriod() ?: 'Conditions variables selon le profil' }}</p>
                @if($plan->annual_effective_monthly)
                    <div class="price-detail">
                        <span>Équivalent mensuel annuel</span>
                        <b>{{ $plan->annual_effective_monthly }}@if($plan->annual_effective_monthly_max && $plan->annual_effective_monthly_max != $plan->annual_effective_monthly) à {{ $plan->annual_effective_monthly_max }}@endif {{ $plan->currency }}</b>
                    </div>
                @endif
                <div class="price-detail">
                    <span>Variantes vérifiées</span>
                    <b>{{ count($plan->price_variants ?? []) ?: 'Aucune' }}</b>
                </div>
                <div class="price-detail">
                    <span>Unité</span>
                    <b>{{ $plan->price_unit ? str_replace('_', ' ', $plan->price_unit) : 'Non communiqué' }}</b>
                </div>
                <small>{{ Str::limit($plan->raw_price, 280) }}</small>
            </article>
        @empty
            <div class="blog-empty">
                <h2>Aucun tarif valide</h2>
                <p>La page tarifs doit encore être collectée et contrôlée.</p>
            </div>
        @endforelse
    </div>

    <div class="affiliate-disclosure">
        <strong>Important</strong>
        Les prix peuvent varier selon le pays, la devise, les taxes et les promotions. Confirmez toujours le montant final sur le site officiel.
    </div>
</article>
@endsection
