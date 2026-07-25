@extends('layouts.blog')

@section('title', 'Comparateur de Logiciels pour Indépendants')
@section('description', 'Trouvez le logiciel de facturation et comptabilité parfait pour votre entreprise en comparant leurs fonctionnalités.')

@section('content')
<main class="tool-container" x-data="softwareComparator()">
    
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
                    <template x-for="tool in tools" :key="tool.slug">
                        <th class="brand-col" x-show="isVisible(tool)">
                            <div class="compare-brand-logo" x-text="tool.logo"></div>
                            <a :href="'/outils/' + tool.slug" style="color:var(--tool-primary);text-decoration:none;" x-text="tool.name"></a>
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Prix de départ</td>
                    <template x-for="tool in tools" :key="tool.slug">
                        <td class="brand-col" x-show="isVisible(tool)" x-text="tool.price"></td>
                    </template>
                </tr>
                <tr>
                    <td>Déclaration URSSAF Auto</td>
                    <template x-for="tool in tools" :key="tool.slug">
                        <td :class="tool.urssaf ? 'check' : 'cross'" x-show="isVisible(tool)" x-text="tool.urssaf ? '✅' : '❌'"></td>
                    </template>
                </tr>
                <tr>
                    <td>Banque Pro incluse</td>
                    <template x-for="tool in tools" :key="tool.slug">
                        <td :class="tool.hasBank ? 'check' : 'cross'" x-show="isVisible(tool)" x-text="tool.hasBank ? '✅' : '❌'"></td>
                    </template>
                </tr>
                <tr>
                    <td>Expert-Comptable dédié</td>
                    <template x-for="tool in tools" :key="tool.slug">
                        <td :class="tool.accounting ? 'check' : 'cross'" x-show="isVisible(tool)" x-text="tool.accounting ? '✅' : '❌'"></td>
                    </template>
                </tr>
                <tr>
                    <td>Liasse fiscale (Sociétés)</td>
                    <template x-for="tool in tools" :key="tool.slug">
                        <td :class="tool.liasse ? 'check' : 'cross'" x-show="isVisible(tool)" x-text="tool.liasse ? '✅' : '❌'"></td>
                    </template>
                </tr>
                <tr>
                    <td>Saisie de notes de frais par IA</td>
                    <template x-for="tool in tools" :key="tool.slug">
                        <td :class="tool.iaFeature ? 'check' : 'cross'" x-show="isVisible(tool)" x-text="tool.iaFeature ? '✅' : '❌'"></td>
                    </template>
                </tr>
                <tr>
                    <td>Note globale (sur 10)</td>
                    <template x-for="tool in tools" :key="tool.slug">
                        <td class="brand-col" x-show="isVisible(tool)"><strong x-text="tool.score"></strong></td>
                    </template>
                </tr>
                <tr>
                    <td></td>
                    <template x-for="tool in tools" :key="tool.slug">
                        <td class="brand-col" x-show="isVisible(tool)">
                            <a :href="'/outils/' + tool.slug" class="hp-hero-cta" style="font-size:14px; padding:10px 20px;">Voir la fiche</a>
                        </td>
                    </template>
                </tr>
            </tbody>
        </table>
    </div>

</main>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('softwareComparator', () => ({
            filters: {
                free: false,
                ia: false,
                sasu: false,
                micro: false,
                bank: false
            },
            tools: [
                {
                    slug: 'indy',
                    name: 'Indy',
                    logo: 'I',
                    price: 'Gratuit',
                    isFree: true,
                    hasIa: true,
                    isSasu: true,
                    isMicro: true,
                    hasBank: true,
                    score: '9.7',
                    urssaf: true,
                    hasBank: true,
                    accounting: false,
                    liasse: true,
                    iaFeature: true
                },
                {
                    slug: 'dougs',
                    name: 'Dougs',
                    logo: 'D',
                    price: '49€ / mois',
                    isFree: false,
                    hasIa: false,
                    isSasu: true,
                    isMicro: true,
                    hasBank: false,
                    score: '9.2',
                    urssaf: true,
                    hasBank: false,
                    accounting: true,
                    liasse: true,
                    iaFeature: false
                },
                {
                    slug: 'pennylane',
                    name: 'Pennylane',
                    logo: 'P',
                    price: '14€ / mois',
                    isFree: false,
                    hasIa: true,
                    isSasu: true,
                    isMicro: true,
                    hasBank: true,
                    score: '9.4',
                    urssaf: false,
                    hasBank: true,
                    accounting: true,
                    liasse: true,
                    iaFeature: true
                },
                {
                    slug: 'tiime',
                    name: 'Tiime',
                    logo: 'T',
                    price: 'Gratuit (Fact.)',
                    isFree: true,
                    hasIa: true,
                    isSasu: true,
                    isMicro: true,
                    hasBank: true,
                    score: '9.3',
                    urssaf: false,
                    hasBank: true,
                    accounting: true,
                    liasse: true,
                    iaFeature: true
                },
                {
                    slug: 'abby',
                    name: 'Abby',
                    logo: 'A',
                    price: 'Gratuit',
                    isFree: true,
                    hasIa: false,
                    isSasu: false,
                    isMicro: true,
                    hasBank: false,
                    score: '8.8',
                    urssaf: true,
                    hasBank: false,
                    accounting: false,
                    liasse: false,
                    iaFeature: false
                }
            ],
            
            isVisible(tool) {
                if (this.filters.free && !tool.isFree) return false;
                if (this.filters.ia && !tool.hasIa) return false;
                if (this.filters.sasu && !tool.isSasu) return false;
                if (this.filters.micro && !tool.isMicro) return false;
                if (this.filters.bank && !tool.hasBank) return false;
                return true;
            }
        }))
    })
</script>
@endsection
