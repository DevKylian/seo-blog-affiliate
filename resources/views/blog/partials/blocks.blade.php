@foreach($blocks as $block)
    @php
        $blockType = $block['type'] ?? '';
    @endphp

    @if($blockType === 'affiliate_cta')
        @php
            $position = $block['position'] ?? 'after_intro';
            $positionClass = 'affiliate-cta--' . preg_replace('/[^a-z0-9_-]+/', '-', mb_strtolower($position));
            $affiliateBlock = app(\App\Services\AffiliateBlockService::class)->resolveBlock($article, $position);
        @endphp
        @if($affiliateBlock)
            @php
                $isIndy = $affiliateBlock->project && (in_array(mb_strtolower($affiliateBlock->project->slug), ['indy', 'indy-1', 'indy-fr'], true) || in_array(mb_strtolower($affiliateBlock->project->name), ['indy', 'blog & guides généraux', 'blog & guides generaux', 'guides généraux', 'guides generaux'], true));
                
                $themeColor = '#F75A77';
                $hoverColor = '#e04460';
                $textColor = '#fff';
                
                $slug = $affiliateBlock->project ? mb_strtolower($affiliateBlock->project->slug) : '';
                if (str_contains($slug, 'pennylane')) {
                    $themeColor = '#10B981'; $hoverColor = '#059669';
                } elseif (str_contains($slug, 'shine')) {
                    $themeColor = '#F59E0B'; $hoverColor = '#d97706'; $textColor = '#0f172a';
                } elseif (str_contains($slug, 'qonto')) {
                    $themeColor = '#6366F1'; $hoverColor = '#4f46e5';
                } elseif (str_contains($slug, 'blank')) {
                    $themeColor = '#1E293B'; $hoverColor = '#0f172a';
                } elseif (str_contains($slug, 'tiime')) {
                    $themeColor = '#F43F5E'; $hoverColor = '#e11d48';
                } elseif (str_contains($slug, 'abby')) {
                    $themeColor = '#8B5CF6'; $hoverColor = '#7c3aed';
                }
                
                $lines = array_filter(array_map('trim', explode("\n", $affiliateBlock->description)));
                $textLines = [];
                $bullets = [];
                foreach ($lines as $line) {
                    if (str_starts_with($line, '✅') || str_starts_with($line, '✓') || str_starts_with($line, '•')) {
                        $bullets[] = trim(mb_substr($line, 1));
                    } else {
                        $textLines[] = $line;
                    }
                }
            @endphp
            <aside class="premium-cta-indy custom-premium-reco {{ $positionClass }}" style="
                --cta-theme: {{ $themeColor }}; 
                --cta-theme-hover: {{ $hoverColor }}; 
                --cta-text: {{ $textColor }}; 
                --cta-shadow: {{ $themeColor }}20; 
                --cta-shadow-btn: {{ $themeColor }}40; 
                --cta-shadow-hover: {{ $themeColor }}59;
                background: linear-gradient(145deg, #ffffff, #f8fafc); 
                border: 1px solid #e2e8f0; 
                border-radius: 24px; 
                padding: 48px 32px; 
                margin: 48px 0; 
                text-align: center; 
                box-shadow: 0 20px 40px -10px rgba(0,0,0,0.05); 
                position: relative; 
                overflow: hidden;
            ">
                <!-- Top border gradient -->
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 6px; background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899);"></div>
                
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 72px; height: 72px; background: var(--cta-shadow); border-radius: 50%; color: var(--cta-theme); margin-bottom: 24px; box-shadow: 0 0 0 10px rgba(255,255,255,0.8);">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                </div>
                
                @if($isIndy)
                    <div style="margin-top: -10px; margin-bottom: 24px;">
                        <span style="background: #fef3c7; color: #d97706; padding: 6px 14px; border-radius: 100px; font-weight: 800; font-size: 11px; text-transform: uppercase; display: inline-block;">🏆 Choix N°1 de la Rédaction</span>
                    </div>
                @else
                    <div style="margin-top: -10px; margin-bottom: 24px;">
                        <span style="background: var(--cta-shadow); color: var(--cta-theme); padding: 6px 14px; border-radius: 100px; font-weight: 800; font-size: 11px; text-transform: uppercase; display: inline-block;">Top Recommandation</span>
                    </div>
                @endif
                
                <h3 style="color: #0f172a; font-size: 28px; font-weight: 800; margin-bottom: 16px; font-family: 'Manrope', sans-serif; letter-spacing: -0.5px;">{{ $affiliateBlock->title }}</h3>
                
                <div style="color: #475569; font-size: 16px; line-height: 1.8; margin-bottom: 32px; max-width: 650px; margin-left: auto; margin-right: auto;">
                    {!! nl2br(e(implode("\n", $textLines))) !!}
                </div>

                @if(count($bullets) > 0)
                    <div style="margin-bottom: 32px; max-width: 500px; margin-left: auto; margin-right: auto; text-align: left; background: white; padding: 24px; border-radius: 16px; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);">
                        <ul style="display: flex; flex-direction: column; gap: 12px; list-style: none; padding: 0; margin: 0;">
                            @foreach($bullets as $bullet)
                                <li style="display: flex; align-items: flex-start; gap: 12px; font-size: 15px; color: #334155; line-height: 1.5; font-weight: 500;">
                                    <svg style="width: 20px; height: 20px; color: var(--cta-theme); flex-shrink: 0; margin-top: 2px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    {{ $bullet }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($isIndy)
                    <div style="max-width: 650px; margin: 0 auto 32px auto; padding: 16px 24px; background: #f8fafc; border-radius: 16px; font-size: 15px; font-style: italic; border-left: 4px solid var(--cta-theme); text-align: left;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #64748b; font-size: 14px;">M</div>
                                <div>
                                    <strong style="display: block; font-style: normal; color: #1e293b; line-height: 1.2;">Marie L.</strong>
                                    <span style="font-size: 12px; color: #64748b; font-style: normal;">Infirmière libérale</span>
                                </div>
                            </div>
                            <div style="color: #fbbf24; font-style: normal; font-size: 16px; letter-spacing: 2px;">★★★★★</div>
                        </div>
                        <p style="margin: 0; color: #475569;">"Je ne perds plus mes soirées sur ma compta. Tout est synchronisé, c'est un vrai soulagement au quotidien."</p>
                    </div>
                @endif
                
                <a href="{{ app(\App\Services\AffiliateBlockService::class)->trackedUrl($article, $affiliateBlock->exists ? $affiliateBlock : null, $position) }}"
                    target="_blank" rel="sponsored noopener" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: var(--cta-theme); color: var(--cta-text); font-weight: 800; font-size: 16px; padding: 16px 36px; border-radius: 12px; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 10px 20px var(--cta-shadow-btn);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 15px 25px var(--cta-shadow-hover)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 10px 20px var(--cta-shadow-btn)';">
                    {{ $affiliateBlock->cta }}
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </a>
            </aside>
        @endif
    @elseif($blockType === 'markdown')
        <div class="markdown-body public-markdown">
            {!! app(\App\Services\ContextualInternalLinkRenderer::class)->render($block['content'] ?? '', $article) !!}</div>
    @elseif($blockType === 'pricing_table')
        @php
            $affiliatePlans = $article->project->plans;
            $actualCompetitorGroups = $article->project->relationLoaded('competitorPlans')
                ? $article->project->competitorPlans->groupBy('competitor_name')
                : collect();
            $configuredCompetitors = collect(array_keys($article->project->competitor_pricing_urls ?? []))
                ->merge($article->project->competitors ?? [])
                ->map(fn($name) => trim((string) $name))
                ->filter(fn($name) => $name !== '' && mb_strtolower($name) !== mb_strtolower($article->project->name))
                ->unique(fn($name) => mb_strtolower($name))
                ->values();
            $configuredCompetitorGroups = $configuredCompetitors->mapWithKeys(function (string $name) use ($actualCompetitorGroups) {
                $plans = $actualCompetitorGroups->first(
                    fn($plans, $groupName) => mb_strtolower((string) $groupName) === mb_strtolower($name)
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
                    <span>{{ $isComparisonPricing ? 'Tarifs comparés' : 'Tarifs ' . $article->project->name }}</span>
                    <small>Vérifié le {{ $allPricingPlans->max('verified_at')?->format('d/m/Y') ?? 'site officiel' }}</small>
                </div>

                @if($isComparisonPricing)
                    <div class="pricing-comparison-cards">
                        @foreach($comparisonRows as $row)
                            <article class="pricing-card">
                                <header class="pricing-card__header">
                                    <h3>{{ $row['product'] }}</h3>
                                    <div class="pricing-card__entry-price">
                                        <span class="pricing-card__entry-price-label" style="font-size: 11px; display: block; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 2px;">Prix d'entrée</span>
                                        {{ $row['entry_price'] }}
                                    </div>
                                </header>
                                
                                <div class="pricing-card__body">
                                    <div class="pricing-card__section">
                                        <h4>Offres relevées</h4>
                                        <ul>
                                            @foreach(explode('|', $row['offers']) as $item)
                                                @if(trim($item)) <li>{{ trim($item) }}</li> @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                    
                                    <div class="pricing-card__section">
                                        <h4>Gratuit / essai</h4>
                                        <ul>
                                            @foreach(explode('|', $row['free_trial']) as $item)
                                                @if(trim($item)) <li>{{ trim($item) }}</li> @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                    
                                    <div class="pricing-card__section">
                                        <h4>Inclus</h4>
                                        <ul>
                                            @foreach(explode('|', $row['coverage']) as $item)
                                                @if(trim($item)) <li>{{ trim($item) }}</li> @endif
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="pricing-grid">
                        @foreach($affiliatePlans as $plan)
                            <article>
                                <h3>{{ $plan->name }}</h3>
                                <strong>{{ $plan->publicPriceLabel() }}</strong>
                                @if($plan->publicFeatureSummary())
                                <p>{{ $plan->publicFeatureSummary() }}</p>@endif
                            </article>
                        @endforeach
                    </div>
                @endif

                <p class="data-note">Les prix peuvent varier selon le profil et le mode de facturation. Consultez les pages
                    officielles avant de souscrire.</p>
                @if($isComparisonPricing)
                    <div class="pricing-source-links">
                        @foreach($article->project->sourcePages->whereNotNull('competitor_name') as $source)
                            <a href="{{ $source->url }}" target="_blank" rel="noopener">{{ $source->competitor_name }} source</a>
                        @endforeach
                        @if($article->project->pricing_url)
                            <a href="{{ $article->project->pricing_url }}" target="_blank" rel="noopener">{{ $article->project->name }}
                                source</a>
                        @endif
                    </div>
                @endif
            </section>
        @endif
    @elseif($blockType === 'affiliate_disclosure')
        <div class="affiliate-disclosure">
            <strong>Transparence</strong>
            <p>Certains liens présents dans ce contenu peuvent être affiliés. Cela ne change ni notre méthodologie ni le prix
                payé.</p>
        </div>
    @elseif($blockType === 'last_verified')
        <p class="last-verified">
            <span>Données vérifiées</span>
            <strong>{{ \Carbon\Carbon::parse($block['date'] ?? now())->translatedFormat('d F Y') }}</strong>
        </p>
    @endif
@endforeach