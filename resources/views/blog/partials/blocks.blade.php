@foreach($blocks as $block)
    @switch($block['type'] ?? '')
        @case('markdown')<div class="markdown-body public-markdown">{!! app(\App\Services\ContextualInternalLinkRenderer::class)->render($block['content'] ?? '', $article) !!}</div>@break
        @case('pricing_table')
            @if($article->project->plans->isNotEmpty())
                <section class="dynamic-pricing">
                    <div class="dynamic-heading">
                        <span>Tarifs {{ $article->project->name }}</span>
                        <small>Vérifié le {{ $article->project->plans->max('verified_at')?->format('d/m/Y') ?? 'site officiel' }}</small>
                    </div>
                    <div class="pricing-grid">
                        @foreach($article->project->plans as $plan)
                            <article>
                                <h3>{{ $plan->name }}</h3>
                                <strong>{{ $plan->publicPriceLabel() }}</strong>
                                @if($plan->publicFeatureSummary())<p>{{ $plan->publicFeatureSummary() }}</p>@endif
                            </article>
                        @endforeach
                    </div>
                    <p class="data-note">Les prix peuvent varier selon le profil et le mode de facturation. Consultez le site officiel avant de souscrire.</p>
                </section>
            @endif
            @break
        @case('affiliate_disclosure')<div class="affiliate-disclosure"><strong>Transparence</strong> Certains liens présents dans ce contenu peuvent être affiliés. Cela ne change ni notre méthodologie ni le prix payé.</div>@break
        @case('last_verified')<p class="last-verified">✓ Données vérifiées le {{ \Carbon\Carbon::parse($block['date'] ?? now())->translatedFormat('d F Y') }}</p>@break
    @endswitch
@endforeach
