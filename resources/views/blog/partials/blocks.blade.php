@foreach($blocks as $block)
    @php
        $blockType = $block['type'] ?? '';
    @endphp

    @if($blockType === 'affiliate_cta')
        @php
            $position = $block['position'] ?? 'after_intro';
            $positionClass = 'affiliate-cta--'.preg_replace('/[^a-z0-9_-]+/', '-', mb_strtolower($position));
            $affiliateBlock = app(\App\Services\AffiliateBlockService::class)->resolveBlock($article, $position);
        @endphp
        @if($affiliateBlock)
            <aside class="affiliate-cta {{ $positionClass }} {{ $affiliateBlock->style }}">
                <div class="affiliate-cta__copy">
                    <strong>{{ $affiliateBlock->title }}</strong>
                    <p>{{ $affiliateBlock->description }}</p>
                </div>
                <a class="affiliate-cta__button" href="{{ app(\App\Services\AffiliateBlockService::class)->trackedUrl($article, $affiliateBlock->exists ? $affiliateBlock : null, $position) }}" rel="sponsored nofollow">{{ $affiliateBlock->cta }}</a>
            </aside>
        @endif
    @elseif($blockType === 'markdown')
        <div class="markdown-body public-markdown">{!! app(\App\Services\ContextualInternalLinkRenderer::class)->render($block['content'] ?? '', $article) !!}</div>
    @elseif($blockType === 'pricing_table')
        @php
            $affiliatePlans = $article->project->plans;
            $actualCompetitorGroups = $article->project->relationLoaded('competitorPlans')
                ? $article->project->competitorPlans->groupBy('competitor_name')
                : collect();
            $configuredCompetitors = collect(array_keys($article->project->competitor_pricing_urls ?? []))
                ->merge($article->project->competitors ?? [])
                ->map(fn ($name) => trim((string) $name))
                ->filter(fn ($name) => $name !== '' && mb_strtolower($name) !== mb_strtolower($article->project->name))
                ->unique(fn ($name) => mb_strtolower($name))
                ->values();
            $configuredCompetitorGroups = $configuredCompetitors->mapWithKeys(function (string $name) use ($actualCompetitorGroups) {
                $plans = $actualCompetitorGroups->first(
                    fn ($plans, $groupName) => mb_strtolower((string) $groupName) === mb_strtolower($name)
                );

                return [$name => $plans ?: collect()];
            });
            $competitorGroups = $configuredCompetitorGroups->merge($actualCompetitorGroups);
            $isComparisonPricing = in_array($article->type, ['comparison', 'alternatives', 'best_tools'], true) && $competitorGroups->isNotEmpty();
            $pricingGroups = collect([$article->project->name => $affiliatePlans])->merge($competitorGroups);
            $allPricingPlans = $pricingGroups->flatten(1)->filter();
            $comparisonRows = $isComparisonPricing
                ? app(\App\Services\PricingComparisonPresenter::class)->rows($pricingGroups)
                : collect();
        @endphp
        @if($allPricingPlans->isNotEmpty())
            <section class="dynamic-pricing">
                <div class="dynamic-heading">
                    <span>{{ $isComparisonPricing ? 'Tarifs comparés' : 'Tarifs '.$article->project->name }}</span>
                    <small>Vérifié le {{ $allPricingPlans->max('verified_at')?->format('d/m/Y') ?? 'site officiel' }}</small>
                </div>

                @if($isComparisonPricing)
                    <div class="pricing-comparison-table">
                        <div class="pricing-row pricing-head">
                            <span>Outil</span>
                            <span>Prix d'entrée</span>
                            <span>Offres relevées</span>
                            <span>Gratuit / essai</span>
                            <span>Ce que couvre le prix</span>
                        </div>
                        @foreach($comparisonRows as $row)
                            <div class="pricing-row">
                                <strong>{{ $row['product'] }}</strong>
                                <span>{{ $row['entry_price'] }}</span>
                                <span>{{ $row['offers'] }}</span>
                                <span>{{ $row['free_trial'] }}</span>
                                <span>{{ $row['coverage'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="pricing-grid">
                        @foreach($affiliatePlans as $plan)
                            <article>
                                <h3>{{ $plan->name }}</h3>
                                <strong>{{ $plan->publicPriceLabel() }}</strong>
                                @if($plan->publicFeatureSummary())<p>{{ $plan->publicFeatureSummary() }}</p>@endif
                            </article>
                        @endforeach
                    </div>
                @endif

                <p class="data-note">Les prix peuvent varier selon le profil et le mode de facturation. Consultez les pages officielles avant de souscrire.</p>
                @if($isComparisonPricing)
                    <div class="pricing-source-links">
                        @foreach($article->project->sourcePages->whereNotNull('competitor_name') as $source)
                            <a href="{{ $source->url }}" target="_blank" rel="noopener">{{ $source->competitor_name }} source</a>
                        @endforeach
                        @if($article->project->pricing_url)
                            <a href="{{ $article->project->pricing_url }}" target="_blank" rel="noopener">{{ $article->project->name }} source</a>
                        @endif
                    </div>
                @endif
            </section>
        @endif
    @elseif($blockType === 'affiliate_disclosure')
        <div class="affiliate-disclosure">
            <strong>Transparence</strong>
            <p>Certains liens présents dans ce contenu peuvent être affiliés. Cela ne change ni notre méthodologie ni le prix payé.</p>
        </div>
    @elseif($blockType === 'last_verified')
        <p class="last-verified">
            <span>Données vérifiées</span>
            <strong>{{ \Carbon\Carbon::parse($block['date'] ?? now())->translatedFormat('d F Y') }}</strong>
        </p>
    @endif
@endforeach
