@extends('layouts.blog')

@section('title', 'Comparateur & Fiches Logiciels pour Indépendants — BusinessKit')
@section('description', 'Comparez les meilleurs logiciels de comptabilité et de facturation pour indépendants. Fiches détaillées, comparatifs directs et tarifs vérifiés.')

@section('content')
<main class="tool-container" x-data="softwareComparator()">
    
    <!-- En-tête Hero -->
    <header class="tool-header-hero">
        <span style="display:inline-block; padding: 4px 12px; border-radius:20px; background:#dbeafe; color:#1e40af; font-size:12px; font-weight:800; margin-bottom:12px; text-transform:uppercase; letter-spacing:0.05em;">
            Guide & Comparateur 2026
        </span>
        <h1>Les meilleurs logiciels, <span>sans discours commercial</span></h1>
        <p>Comparez les fonctionnalités, consultez les tarifs vérifiés et découvrez nos comparatifs tête-à-tête pour choisir l'outil idéal.</p>
    </header>

    <!-- Section 1 : Fiches des Logiciels Analysés -->
    <section style="margin-bottom: 50px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
            <div>
                <h2 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px;">Fiches Logiciels Suivies</h2>
                <p style="font-size: 14px; color: #64748b; margin: 0;">Toutes les données officielles vérifiées et mises à jour.</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
            @forelse($tools as $tool)
                <article style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 4px 12px rgba(15,23,42,0.03); transition: transform 0.2s, box-shadow 0.2s;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                            <span style="display: grid; place-items: center; width: 42px; height: 42px; border-radius: 10px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; font-weight: 800; font-size: 18px;">
                                {{ strtoupper(substr($tool->name, 0, 1)) }}
                            </span>
                            <div>
                                <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: #0f172a;">
                                    <a href="{{ route('tools.show', $tool->slug) }}" style="color: inherit; text-decoration: none;">{{ $tool->name }}</a>
                                </h3>
                                <span style="font-size: 12px; color: #64748b;">{{ $tool->plans_count }} tarifs suivis</span>
                            </div>
                        </div>
                        <p style="font-size: 13px; color: #475569; line-height: 1.5; margin-bottom: 16px;">
                            {{ Str::limit($tool->description ?: $tool->positioning ?: 'Fiche détaillée du logiciel '.$tool->name.' pour indépendants.', 110) }}
                        </p>
                    </div>

                    <div style="display: flex; gap: 8px; border-top: 1px solid #f1f5f9; padding-top: 14px;">
                        <a href="{{ route('tools.show', $tool->slug) }}" style="flex: 1; text-align: center; background: #2563eb; color: white; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none;">
                            Fiche Outil →
                        </a>
                        <a href="{{ route('tools.pricing', $tool->slug) }}" style="background: #f1f5f9; color: #334155; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none;">
                            Tarifs 🏷️
                        </a>
                    </div>
                </article>
            @empty
                <div style="grid-column: 1/-1; padding: 40px; text-align: center; color: #64748b;">
                    Aucun logiciel répertorié pour le moment.
                </div>
            @endforelse
        </div>
    </section>

    <!-- Section 2 : Matrice des Comparatifs Directs -->
    <section style="margin-bottom: 60px;">
        <div style="margin-bottom: 24px;">
            <h2 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px;">Comparatifs Directs (Tête-à-tête)</h2>
            <p style="font-size: 14px; color: #64748b; margin: 0;">Analyses comparatives détaillées entre les principaux logiciels du marché.</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
            @php
                $popularComparisons = [
                    ['slug' => 'indy-vs-pennylane', 'title' => 'Indy vs Pennylane', 'desc' => 'Comptabilité auto-entrepreneur vs comptabilité complète'],
                    ['slug' => 'indy-vs-dougs', 'title' => 'Indy vs Dougs', 'desc' => 'Logiciel en autonomie vs Expert-comptable en ligne'],
                    ['slug' => 'indy-vs-abby', 'title' => 'Indy vs Abby', 'desc' => 'Deux géants de la gestion micro-entreprise'],
                    ['slug' => 'indy-vs-shine', 'title' => 'Indy vs Shine', 'desc' => 'Solution comptable vs Compte pro tout-en-un'],
                    ['slug' => 'pennylane-vs-dougs', 'title' => 'Pennylane vs Dougs', 'desc' => 'Le duel des solutions pour sociétés (SASU/EURL)'],
                    ['slug' => 'shine-vs-qonto', 'title' => 'Shine vs Qonto', 'desc' => 'Les deux meilleures neobanques professionnelles'],
                ];
            @endphp

            @foreach($popularComparisons as $comp)
                @php
                    $isPublished = isset($comparisonArticles) && $comparisonArticles->contains(fn($a) => $a->slug === $comp['slug']);
                @endphp
                <a href="{{ route('comparisons.show', $comp['slug']) }}" style="display: block; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; text-decoration: none; transition: all 0.2s; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-weight: 800; font-size: 16px; color: #0f172a;">{{ $comp['title'] }}</span>
                        @if($isPublished)
                            <span style="font-size: 11px; font-weight: 700; background: #dcfce7; color: #15803d; padding: 2px 8px; border-radius: 12px;">En ligne</span>
                        @else
                            <span style="font-size: 11px; font-weight: 700; background: #eff6ff; color: #1d4ed8; padding: 2px 8px; border-radius: 12px;">Comparatif IA</span>
                        @endif
                    </div>
                    <p style="font-size: 12px; color: #64748b; margin: 0 0 12px; line-height: 1.4;">{{ $comp['desc'] }}</p>
                    <span style="font-size: 12px; font-weight: 700; color: #2563eb; display: inline-flex; align-items: center; gap: 4px;">
                        Voir le comparatif →
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <!-- Section 3 : Tableau Comparatif Interactif -->
    <section>
        <div style="margin-bottom: 20px;">
            <h2 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px;">Tableau d'analyse des fonctionnalités</h2>
            <p style="font-size: 14px; color: #64748b; margin: 0;">Filtrez et comparez les caractéristiques techniques des outils en direct.</p>
        </div>

        <!-- Filtres Interactifs -->
        <div class="filters-bar" style="margin-bottom: 20px;">
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
                                <a :href="'/outils/' + tool.slug" class="hp-hero-cta" style="font-size:14px; padding:8px 16px;">Voir la fiche</a>
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

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
