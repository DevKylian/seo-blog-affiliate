@extends('layouts.blog')

@section('title', 'Comparateur de Logiciels pour Indépendants — BusinessKit')
@section('description', 'Trouvez le logiciel de facturation et comptabilité parfait pour votre entreprise en comparant leurs fonctionnalités.')

@section('content')
@php
    $comparisonData = [
        [
            'slug' => 'indy',
            'name' => 'Indy',
            'logo' => 'I',
            'domain' => 'indy.fr',
            'price' => 'Gratuit',
            'isFree' => true,
            'hasIa' => true,
            'isSasu' => true,
            'isMicro' => true,
            'hasBank' => true,
            'score' => '9.7',
            'urssaf' => true,
            'accounting' => false,
            'liasse' => true,
            'iaFeature' => true
        ],
        [
            'slug' => 'dougs',
            'name' => 'Dougs',
            'logo' => 'D',
            'domain' => 'dougs.fr',
            'price' => '49€ / mois',
            'isFree' => false,
            'hasIa' => false,
            'isSasu' => true,
            'isMicro' => true,
            'hasBank' => false,
            'score' => '9.2',
            'urssaf' => true,
            'accounting' => true,
            'liasse' => true,
            'iaFeature' => false
        ],
        [
            'slug' => 'pennylane',
            'name' => 'Pennylane',
            'logo' => 'P',
            'domain' => 'pennylane.com',
            'price' => '14€ / mois',
            'isFree' => false,
            'hasIa' => true,
            'isSasu' => true,
            'isMicro' => true,
            'hasBank' => true,
            'score' => '9.4',
            'urssaf' => false,
            'accounting' => true,
            'liasse' => true,
            'iaFeature' => true
        ],
        [
            'slug' => 'tiime',
            'name' => 'Tiime',
            'logo' => 'T',
            'domain' => 'tiime.fr',
            'price' => 'Gratuit (Fact.)',
            'isFree' => true,
            'hasIa' => true,
            'isSasu' => true,
            'isMicro' => true,
            'hasBank' => true,
            'score' => '9.3',
            'urssaf' => false,
            'accounting' => true,
            'liasse' => true,
            'iaFeature' => true
        ],
        [
            'slug' => 'abby',
            'name' => 'Abby',
            'logo' => 'A',
            'domain' => 'abby.fr',
            'price' => 'Gratuit',
            'isFree' => true,
            'hasIa' => false,
            'isSasu' => false,
            'isMicro' => true,
            'hasBank' => false,
            'score' => '8.8',
            'urssaf' => true,
            'accounting' => false,
            'liasse' => false,
            'iaFeature' => false
        ]
    ];
@endphp

<main class="tool-container" x-data="{
    filters: { free: false, ia: false, sasu: false, micro: false, bank: false },
    toolsMap: {{ json_encode(collect($comparisonData)->keyBy('slug')) }},
    isVisible(slug) {
        let t = this.toolsMap[slug];
        if (!t) return true;
        if (this.filters.free && !t.isFree) return false;
        if (this.filters.ia && !t.hasIa) return false;
        if (this.filters.sasu && !t.isSasu) return false;
        if (this.filters.micro && !t.isMicro) return false;
        if (this.filters.bank && !t.hasBank) return false;
        return true;
    }
}">
    
    <!-- En-tête -->
    <header class="tool-header-hero">
        <h1>Trouvez le logiciel idéal en <span>1 minute</span></h1>
        <p>Utilisez nos filtres pour affiner la sélection ou comparez directement les solutions les plus populaires du marché pour les indépendants.</p>
    </header>

    <!-- Filtres Interactifs -->
    <div class="filters-bar">
        <span class="filter-label">Filtres rapides :</span>
        <label class="filter-chip" :class="{'active': filters.free}">
            <input type="checkbox" x-model="filters.free"> Gratuit (Plan basique)
        </label>
        <label class="filter-chip" :class="{'active': filters.ia}">
            <input type="checkbox" x-model="filters.ia"> IA Intégrée
        </label>
        <label class="filter-chip" :class="{'active': filters.sasu}">
            <input type="checkbox" x-model="filters.sasu"> Pour SASU / EURL
        </label>
        <label class="filter-chip" :class="{'active': filters.micro}">
            <input type="checkbox" x-model="filters.micro"> 100% Micro
        </label>
        <label class="filter-chip" :class="{'active': filters.bank}">
            <input type="checkbox" x-model="filters.bank"> Banque intégrée
        </label>
    </div>

    <!-- Comparateur Géant -->
    <h2 style="font-size: 28px; font-weight: 800; margin-bottom: 24px;">Comparatif détaillé</h2>
    <div class="compare-table-wrapper">
        <table class="compare-table">
            <thead>
                <tr>
                    <th>Fonctionnalités</th>
                    @foreach($comparisonData as $t)
                        <th class="brand-col" x-show="isVisible('{{ $t['slug'] }}')">
                            <div class="compare-brand-logo" style="padding: 0; overflow: hidden; background: white;">
                                <img src="https://logo.clearbit.com/{{ $t['domain'] }}" alt="{{ $t['name'] }}" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.outerHTML='<span>{{ $t['logo'] }}</span>'">
                            </div>
                            <a href="{{ route('tools.show', $t['slug']) }}" style="color:var(--tool-primary);text-decoration:none;">{{ $t['name'] }}</a>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Prix de départ</td>
                    @foreach($comparisonData as $t)
                        <td class="brand-col" x-show="isVisible('{{ $t['slug'] }}')">{{ $t['price'] }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td>Déclaration URSSAF Auto</td>
                    @foreach($comparisonData as $t)
                        <td class="{{ $t['urssaf'] ? 'check' : 'cross' }}" x-show="isVisible('{{ $t['slug'] }}')">{{ $t['urssaf'] ? '✅' : '❌' }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td>Banque Pro incluse</td>
                    @foreach($comparisonData as $t)
                        <td class="{{ $t['hasBank'] ? 'check' : 'cross' }}" x-show="isVisible('{{ $t['slug'] }}')">{{ $t['hasBank'] ? '✅' : '❌' }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td>Expert-Comptable dédié</td>
                    @foreach($comparisonData as $t)
                        <td class="{{ $t['accounting'] ? 'check' : 'cross' }}" x-show="isVisible('{{ $t['slug'] }}')">{{ $t['accounting'] ? '✅' : '❌' }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td>Liasse fiscale (Sociétés)</td>
                    @foreach($comparisonData as $t)
                        <td class="{{ $t['liasse'] ? 'check' : 'cross' }}" x-show="isVisible('{{ $t['slug'] }}')">{{ $t['liasse'] ? '✅' : '❌' }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td>Saisie de notes de frais par IA</td>
                    @foreach($comparisonData as $t)
                        <td class="{{ $t['iaFeature'] ? 'check' : 'cross' }}" x-show="isVisible('{{ $t['slug'] }}')">{{ $t['iaFeature'] ? '✅' : '❌' }}</td>
                    @endforeach
                </tr>
                <tr>
                    <td>Note globale (sur 10)</td>
                    @foreach($comparisonData as $t)
                        <td class="brand-col" x-show="isVisible('{{ $t['slug'] }}')"><strong>{{ $t['score'] }}</strong></td>
                    @endforeach
                </tr>
                <tr>
                    <td></td>
                    @foreach($comparisonData as $t)
                        <td class="brand-col" x-show="isVisible('{{ $t['slug'] }}')">
                            <a href="{{ route('tools.show', $t['slug']) }}" class="hp-hero-cta" style="font-size:14px; padding:10px 20px;">Voir la fiche</a>
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

</main>
@endsection
