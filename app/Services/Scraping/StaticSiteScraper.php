<?php

namespace App\Services\Scraping;

use App\Models\EvidenceChunk;
use App\Models\Plan;
use App\Models\PriceSnapshot;
use App\Models\SeoProject;
use App\Models\SourcePage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

class StaticSiteScraper
{
    private const MAX_BYTES = 5_000_000;

    public function __construct(
        private readonly GeminiPricingNormalizer $pricingNormalizer,
        private readonly BrowserHtmlFetcher $browserHtmlFetcher,
    ) {}

    public function scrape(SeoProject $project, string $url, string $type = 'other', ?string $competitorName = null): SourcePage
    {
        $url = $this->validatePublicUrl($url);
        $source = SourcePage::query()->updateOrCreate(
            ['seo_project_id' => $project->id, 'url' => $url],
            ['type' => $type, 'competitor_name' => $competitorName, 'status' => 'processing', 'error_message' => null],
        );

        try {
            $this->assertRobotsAllows($url);
            $response = $this->fetch($url);
            $capture = null;
            $extractionMethod = 'static_html_domcrawler';
            $verifiedHttpStatus = $response->status();
            if ($response->successful()) {
                $html = $response->body();
            } elseif ($this->shouldUseBrowserFallback($response->status())) {
                $capture = $this->browserHtmlFetcher->fetchPricingData($url);
                $html = $capture['html_snapshots'][0] ?? '';
                if ($html === '') {
                    throw new RuntimeException("La page refuse l’accès HTTP ({$response->status()}) et le rendu navigateur n’a retourné aucun contenu exploitable.");
                }
                $verifiedHttpStatus = (int) ($capture['http_status'] ?? 200);
                $extractionMethod = $capture['engine'] === 'playwright' ? 'playwright_rendered_dom' : 'browser_rendered_dom';
            } else {
                throw new RuntimeException("La page a retourné le statut HTTP {$response->status()}.");
            }

            if (strlen($html) > self::MAX_BYTES) {
                throw new RuntimeException('La page dépasse la limite de 5 Mo.');
            }

            $data = $this->extract($html, $url, $source->type);
            if ($source->type === 'pricing') {
                // Le navigateur est le moteur principal des pages tarifaires,
                // même si le HTML statique contient un faux "sur demande".
                $capture ??= $this->browserHtmlFetcher->fetchPricingData($url);
                if ($capture !== null) {
                    $capturedPrices = [];
                    foreach ($capture['html_snapshots'] as $renderedHtml) {
                        if (strlen($renderedHtml) > self::MAX_BYTES) {
                            continue;
                        }
                        $renderedData = $this->extract($renderedHtml, $url, $source->type);
                        if ($renderedData['prices'] !== []) {
                            $data['title'] = $renderedData['title'];
                            $capturedPrices = array_merge($capturedPrices, $renderedData['prices']);
                        }
                    }
                    $apiPrices = $this->extractApiPrices($capture['json_payloads']);
                    if ($capturedPrices === [] && $apiPrices === []) {
                        $capturedPrices = $data['prices'];
                    }
                    $data['prices'] = $this->uniquePriceCandidates(array_merge($capturedPrices, $apiPrices));
                    $extractionMethod = $capture['engine'] === 'playwright'
                        ? ($apiPrices !== [] ? 'playwright_dom_and_json' : 'playwright_rendered_dom')
                        : 'browser_rendered_dom';
                }
            }
            if ($source->type === 'pricing' && $data['prices'] === []) {
                throw new RuntimeException('Aucune offre tarifaire exploitable n’a été détectée, y compris après le rendu JavaScript.');
            }
            if ($data['chunks'] === []) {
                throw new RuntimeException('Aucun contenu tarifaire exploitable n’a été détecté, y compris après le rendu JavaScript.');
            }
            if ($source->type === 'pricing' && $data['prices'] !== []) {
                // Le DOM peut contenir simultanément tous les onglets (profil,
                // taille, pays, mensuel/annuel). Ils constituent des preuves,
                // pas autant de formules à afficher dans le CMS.
                $data['prices'] = $this->consolidatePrices($data['prices']);
                $data['prices'] = $this->pricingNormalizer->normalize($project, $url, $data['prices'], $competitorName);
                $data['prices'] = array_map(function (array $price): array {
                    $price['features'] = array_values(array_slice(array_unique(array_filter($price['features'] ?? [])), 0, 6));
                    $price['raw'] = $this->formatPlanEvidence($price);

                    return $price;
                }, $data['prices']);
                $data['chunks'] = array_column($data['prices'], 'raw');
                $data['content'] = implode("\n\n", $data['chunks']);
            }

            DB::transaction(function () use ($project, $source, $verifiedHttpStatus, $data, $extractionMethod, $competitorName): void {
                $source->update([
                    'title' => $data['title'],
                    'excerpt' => mb_substr($data['content'], 0, 700),
                    'content' => $data['content'],
                    'content_hash' => hash('sha256', $data['content']),
                    'http_status' => $verifiedHttpStatus,
                    'status' => 'verified',
                    'extraction_method' => $extractionMethod,
                    'confidence_score' => 0.78,
                    'verified_at' => now(),
                ]);

                $source->evidenceChunks()->delete();
                foreach ($data['chunks'] as $index => $chunk) {
                    EvidenceChunk::query()->create([
                        'source_page_id' => $source->id,
                        'category' => $this->categorize($chunk),
                        'value' => $chunk,
                        'source_excerpt' => $chunk,
                        'position' => $index,
                        'confidence_score' => 0.78,
                        'verified_at' => now(),
                    ]);
                }

                if ($source->type === 'pricing') {
                    Plan::query()
                        ->where('seo_project_id', $project->id)
                        ->where('source_page_id', $source->id)
                        ->update(['is_active' => false]);
                    foreach ($data['prices'] as $index => $price) {
                        $plan = Plan::query()->create([
                            'seo_project_id' => $project->id,
                            'source_page_id' => $source->id,
                            'competitor_name' => $competitorName,
                            'name' => $price['name'],
                            'position' => $index,
                            'is_active' => true,
                            'raw_price' => $price['raw'],
                            'currency' => $price['currency'],
                            'monthly_price' => $price['monthly_price'],
                            'monthly_price_max' => $price['monthly_price_max'] ?? null,
                            'annual_total' => $price['annual_total'],
                            'annual_total_max' => $price['annual_total_max'] ?? null,
                            'annual_effective_monthly' => $price['annual_effective_monthly'],
                            'annual_effective_monthly_max' => $price['annual_effective_monthly_max'] ?? null,
                            'billing_period' => $price['period'],
                            'price_unit' => $price['unit'],
                            'promotional_price' => $price['promotional_price'],
                            'features' => $price['features'],
                            'price_variants' => $price['price_variants'] ?? [],
                            'confidence_score' => 0.82,
                            'verified_at' => now(),
                        ]);
                        PriceSnapshot::query()->create([
                            'plan_id' => $plan->id,
                            'monthly_price' => $plan->monthly_price,
                            'monthly_price_max' => $plan->monthly_price_max,
                            'annual_total' => $plan->annual_total,
                            'annual_total_max' => $plan->annual_total_max,
                            'annual_effective_monthly' => $plan->annual_effective_monthly,
                            'annual_effective_monthly_max' => $plan->annual_effective_monthly_max,
                            'currency' => $plan->currency,
                            'raw_price' => $plan->raw_price,
                            'price_variants' => $plan->price_variants,
                            'verified_at' => now(),
                        ]);
                    }
                }
            });

            $project->update(['crawl_status' => 'completed', 'last_crawled_at' => now()]);

            return $source->fresh(['evidenceChunks']);
        } catch (Throwable $exception) {
            $source->update(['status' => 'failed', 'error_message' => mb_substr($exception->getMessage(), 0, 2000)]);
            $project->update(['crawl_status' => 'failed']);
            throw $exception;
        }
    }

    private function fetch(string $url): Response
    {
        $response = $this->http()->timeout(20)
            ->connectTimeout(8)
            ->retry(2, 300, throw: false)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
            ])->get($url);

        if ($response->successful()) {
            $contentType = strtolower($response->header('Content-Type', ''));
            if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/xhtml')) {
                throw new RuntimeException('La ressource distante n’est pas une page HTML.');
            }
        }

        return $response;
    }

    private function shouldUseBrowserFallback(int $status): bool
    {
        return in_array($status, [401, 403, 408, 425, 429], true) || $status >= 500;
    }

    private function extract(string $html, string $url, string $type = 'other'): array
    {
        $crawler = new Crawler($html, $url);
        $title = $crawler->filter('title')->count() ? $this->clean($crawler->filter('title')->first()->text('')) : parse_url($url, PHP_URL_HOST);
        $prices = $type === 'pricing' ? $this->extractPrices($crawler) : [];
        if ($type === 'pricing') {
            $this->removePricingNoise($crawler);
        }

        $chunks = [];

        if ($type === 'pricing' && $prices !== []) {
            $chunks = array_column($prices, 'raw');
        } else {
            $crawler->filter('h1, h2, h3, p, li')->each(function (Crawler $node) use (&$chunks): void {
                if (count($chunks) >= 180) {
                    return;
                }
                $text = $this->clean($node->text(''));
                if (mb_strlen($text) >= 25 && mb_strlen($text) <= 1200 && ! $this->isCallToAction($text) && ! in_array($text, $chunks, true)) {
                    $chunks[] = $text;
                }
            });
        }

        if ($chunks === [] && $type !== 'pricing') {
            throw new RuntimeException('Aucun contenu éditorial exploitable n’a été détecté.');
        }

        $content = implode("\n\n", $chunks);

        return ['title' => $title, 'content' => $content, 'chunks' => array_slice($chunks, 0, 120), 'prices' => $prices];
    }

    private function extractPrices(Crawler $crawler): array
    {
        $prices = [];
        $seen = [];
        $structuredPrices = [];
        $structuredSeen = [];
        $embeddedPrices = [];
        $embeddedSeen = [];

        // Quand l'éditeur publie plusieurs Offer JSON-LD nommées, celles-ci
        // constituent une excellente liste blanche des formules principales.
        $this->appendStructuredDataPrices($crawler, $structuredPrices, $structuredSeen);

        // Les applications React/Next/Nuxt embarquent souvent les vraies cartes
        // tarifaires dans un JSON d'hydratation. Ces données sont plus fiables
        // que les accroches marketing et les plafonds de la matrice comparative.
        $this->appendEmbeddedApplicationPrices($crawler, $embeddedPrices, $embeddedSeen);

        if (count($embeddedPrices) >= 2) {
            return array_slice($this->inheritDominantCurrency($this->enrichEmbeddedPricesFromDom($crawler, $embeddedPrices)), 0, 60);
        }

        $crawler->filter(implode(', ', [
            '.pricing-card',
            '[class*="price-card"]',
            '[class*="plan-card"]',
            '[class*="offer-card"]',
            '[class*="subscription-card"]',
            '[class*="package-card"]',
            '[class*="tier-card"]',
            '[class*="pricing"][class*="card"]',
            '[data-plan]',
            '[data-pricing-plan]',
            'li.plan',
        ]))->each(function (Crawler $container) use (&$prices, &$seen): void {
            $this->appendPrice($container, $prices, $seen);
        });

        // Repli générique pour les sites dont les cartes n'ont pas de classe
        // exploitable. Les offres déjà trouvées par la whitelist sont ignorées.
        $crawler->filter('h1, h2, h3, h4')->each(function (Crawler $heading) use (&$prices, &$seen): void {
            if ($this->isInsidePricingNoise($heading)) {
                return;
            }
            $name = trim($this->clean($heading->text('')), " .:\t\n\r\0\x0B");
            if (! $this->isPlanName($name)) {
                return;
            }

            $container = $this->pricingContainer($heading);
            if (! $container) {
                return;
            }

            $this->appendPrice($container, $prices, $seen, $name, true);
        });

        // Certaines interfaces modernes rendent le nom du plan dans un badge
        // <span>. Si le JSON-LD fournit une liste de noms, elle sert de garde-fou
        // pour retrouver ces cartes sans balayer tous les textes de la page.
        if (count($structuredPrices) >= 2) {
            $structuredNames = collect($structuredPrices)
                ->mapWithKeys(fn (array $price) => [
                    $this->canonicalPlanKey((string) $price['name']) => $this->basePlanName((string) $price['name']),
                ])
                ->filter();
            $crawler->filter('span, strong, p, h1, h2, h3, h4')->each(function (Crawler $label) use ($structuredNames, &$prices, &$seen): void {
                if ($this->isInsidePricingNoise($label) || $this->isInheritedPlanReference($label)) {
                    return;
                }
                $text = $this->cleanPricingText($label->text(''));
                $key = $this->canonicalPlanKey($text);
                if ($text === '' || ! $structuredNames->has($key)) {
                    return;
                }
                $container = $this->pricingContainer($label);
                if ($container !== null) {
                    $this->appendPrice($container, $prices, $seen, $structuredNames->get($key));
                }
            });
        }

        if ($prices === []) {
            $this->appendCompactTablePrices($crawler, $prices, $seen);
        }
        if (count($structuredPrices) >= 2) {
            $allowedNames = collect($structuredPrices)
                ->map(fn (array $price) => $this->canonicalPlanKey((string) $price['name']))
                ->filter()
                ->unique();
            $visualPrices = collect($prices)
                ->filter(fn (array $price) => $allowedNames->contains($this->canonicalPlanKey((string) $price['name'])))
                ->groupBy(fn (array $price) => $this->canonicalPlanKey((string) $price['name']));
            $orderedPrices = [];
            foreach ($structuredPrices as $structuredPrice) {
                $key = $this->canonicalPlanKey((string) $structuredPrice['name']);
                $matches = $visualPrices->get($key, collect());
                if ($matches->isEmpty()) {
                    $orderedPrices[] = $structuredPrice;

                    continue;
                }
                foreach ($matches as $visualPrice) {
                    if (empty($visualPrice['audience'])) {
                        $visualPrice['audience'] = $structuredPrice['audience'] ?? null;
                    }
                    $orderedPrices[] = $visualPrice;
                }
            }
            $prices = $orderedPrices;
        } elseif ($prices === []) {
            $prices = $structuredPrices;
        }

        return array_slice($prices, 0, 60);
    }

    /**
     * Repère les collections de cartes commerciales déjà préparées par le
     * frontend. Une carte n'est acceptée que si elle possède un nom ET un
     * libellé tarifaire explicite dans un champ dédié (caption/displayPrice…).
     * Une description contenant « valeur de 2 000 € » ne suffit jamais.
     */
    private function appendEmbeddedApplicationPrices(Crawler $crawler, array &$prices, array &$seen): void
    {
        $documents = [];
        $crawler->filter('script[type="application/json"], script#__NEXT_DATA__, script[id*="__NUXT"]')->each(function (Crawler $script) use (&$documents): void {
            $data = json_decode(trim($script->text('')), true);
            if (is_array($data)) {
                $documents[] = $data;
            }
        });

        $walk = function (mixed $value) use (&$walk, &$prices, &$seen): void {
            if (! is_array($value)) {
                return;
            }

            if (array_is_list($value) && count($value) >= 2) {
                $collection = [];
                foreach ($value as $item) {
                    if (! is_array($item) || array_is_list($item)) {
                        continue;
                    }

                    $name = $this->firstApiScalar($item, ['planName', 'plan_name', 'offerName', 'offer_name', 'tierName', 'tier_name', 'title', 'name']);
                    $priceText = $this->firstApiScalar($item, [
                        'caption', 'displayPrice', 'display_price', 'priceLabel', 'price_label',
                        'formattedPrice', 'formatted_price', 'billingLabel', 'billing_label',
                    ]);
                    if ($name === null || $priceText === null || ! $this->isPlanName($name) || ! $this->isExplicitPlanPrice($priceText)) {
                        continue;
                    }

                    $price = $this->parsePlanPrice($name, $priceText);
                    if (! $price) {
                        continue;
                    }

                    $description = $this->firstApiScalar($item, ['description', 'subtitle', 'tagline']);
                    $price['audience'] = $description !== null
                        ? $this->cleanPricingText(strip_tags($description))
                        : null;
                    $price['features'] = [];
                    $price['variant'] = 'Données tarifaires embarquées dans la page officielle';
                    $price['raw'] = $this->formatPlanEvidence($price);
                    $collection[] = $price;
                }

                // Deux cartes cohérentes au minimum sont nécessaires pour ne
                // pas confondre un composant isolé avec la grille des offres.
                if (count($collection) >= 2) {
                    foreach ($collection as $price) {
                        $key = $this->pricingFingerprint($price);
                        if (! isset($seen[$key])) {
                            $prices[] = $price;
                            $seen[$key] = true;
                        }
                    }
                }
            }

            foreach ($value as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };

        foreach ($documents as $document) {
            $walk($document);
        }
    }

    private function isExplicitPlanPrice(string $value): bool
    {
        return preg_match('/\b(?:gratuit(?:e)?|free)\b/iu', $value) === 1
            || preg_match('/sur (?:demande|devis)|contact(?:ez)?(?:-nous| sales)?|request (?:a )?quote/iu', $value) === 1
            || (preg_match('/(?:€|\$|£)|\b(?:EUR|USD|GBP)\b/iu', $value) === 1
                && preg_match('/\b(?:mois|month|mensuel|annuel|an|year|yr)\b|\/\s*(?:mo|m|an|yr|y)\b/iu', $value) === 1);
    }

    /** @param array<int, array<string, mixed>> $prices */
    private function inheritDominantCurrency(array $prices): array
    {
        $currency = collect($prices)->pluck('currency')->filter()->countBy()->sortDesc()->keys()->first();
        if (! is_string($currency) || $currency === '') {
            return $prices;
        }

        return array_map(function (array $price) use ($currency): array {
            $price['currency'] ??= $currency;
            $price['raw'] = $this->formatPlanEvidence($price);

            return $price;
        }, $prices);
    }

    /**
     * Extrait uniquement des objets tarifaires structurés capturés sur des
     * réponses XHR/fetch publiques. Un nombre isolé dans un JSON ne suffit pas.
     *
     * @param  array<int, array<string, mixed>>  $payloads
     * @return array<int, array<string, mixed>>
     */
    private function extractApiPrices(array $payloads): array
    {
        $prices = [];
        $seen = [];

        $walk = function (mixed $value, string $path = '') use (&$walk, &$prices, &$seen): void {
            if (! is_array($value)) {
                return;
            }

            if (! array_is_list($value)) {
                $name = $this->firstApiScalar($value, ['planName', 'plan_name', 'offerName', 'offer_name', 'tierName', 'tier_name', 'name', 'title', 'label']);
                $strongPlanObject = collect(['planId', 'plan_id', 'offerId', 'offer_id', 'tierId', 'tier_id'])
                    ->contains(fn (string $key) => array_key_exists($key, $value));
                $pricingPath = preg_match('/pricing|prices?|plans?|offers?|subscriptions?|tiers?|packages?/iu', $path) === 1;

                if ($name !== null && $this->isPlanName($name) && ($strongPlanObject || $pricingPath)) {
                    foreach ($value as $key => $amount) {
                        if (! is_scalar($amount) || preg_match('/^(?:monthly_?price|price_?monthly|annual_?price|yearly_?price|price_?(?:annual|yearly)|price|amount)$/iu', (string) $key) !== 1) {
                            continue;
                        }
                        $amountText = trim((string) $amount);
                        if ($amountText === '' || preg_match('/\d/u', $amountText) !== 1) {
                            continue;
                        }

                        $periodSource = mb_strtolower(implode(' ', array_filter([
                            (string) $key,
                            $this->firstApiScalar($value, ['billingPeriod', 'billing_period', 'interval', 'period', 'billingCycle', 'billing_cycle']),
                            $amountText,
                        ])));
                        $period = match (true) {
                            preg_match('/month|monthly|mois|mensuel/u', $periodSource) === 1 => ' / mois',
                            preg_match('/year|yearly|annual|annuel|an\b/u', $periodSource) === 1 => ' / an',
                            preg_match('/(?:€|eur|usd|gbp).*(?:\/|par|per)/iu', $amountText) === 1 => '',
                            default => null,
                        };
                        if ($period === null) {
                            continue;
                        }

                        $currency = strtoupper((string) ($this->firstApiScalar($value, ['currency', 'priceCurrency', 'price_currency', 'currencyCode', 'currency_code']) ?? 'EUR'));
                        $price = $this->parsePlanPrice($name, $amountText.' '.$currency.$period);
                        if (! $price || ! $this->hasNumericPlanPrice($price)) {
                            continue;
                        }

                        $features = $value['features'] ?? $value['benefits'] ?? $value['included'] ?? [];
                        $price['features'] = collect(is_array($features) ? $features : [])
                            ->map(fn ($feature) => is_scalar($feature) ? $this->cleanPricingText((string) $feature) : null)
                            ->filter()
                            ->take(6)
                            ->values()
                            ->all();
                        $price['audience'] = $this->firstApiScalar($value, ['audience', 'description', 'subtitle', 'tagline']);
                        $price['variant'] = 'Donnée tarifaire structurée chargée par la page · '.Str::of((string) $key)->snake(' ')->toString();
                        $price['raw'] = $this->formatPlanEvidence($price);
                        $fingerprint = $this->pricingFingerprint($price);
                        if (! isset($seen[$fingerprint])) {
                            $prices[] = $price;
                            $seen[$fingerprint] = true;
                        }
                    }
                }
            }

            foreach ($value as $key => $child) {
                if (is_array($child)) {
                    $walk($child, trim($path.'.'.(string) $key, '.'));
                }
            }
        };

        foreach ($payloads as $payload) {
            $walk($payload['data'] ?? null, (string) ($payload['url'] ?? 'api'));
        }

        return array_slice($prices, 0, 60);
    }

    /** @param array<string, mixed> $object @param array<int, string> $keys */
    private function firstApiScalar(array $object, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($object[$key]) && is_scalar($object[$key])) {
                $value = $this->cleanPricingText((string) $object[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /** @param array<int, array<string, mixed>> $prices */
    private function uniquePriceCandidates(array $prices): array
    {
        $seen = [];

        return collect($prices)->filter(function (array $price) use (&$seen): bool {
            $key = $this->pricingFingerprint($price);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        })->take(120)->values()->all();
    }

    private function appendPrice(Crawler $container, array &$prices, array &$seen, ?string $fallbackName = null, bool $strictFallback = false): void
    {
        if ($this->isInsidePricingNoise($container)) {
            return;
        }

        $name = $fallbackName ?: $this->firstPricingText($container, implode(', ', [
            '.card-title',
            '[class*="plan-name"]',
            '[class*="offer-name"]',
            '[class*="pricing-title"]',
            'h2',
            'h3',
            'h4',
        ]));
        $name = trim($this->clean((string) $name), " .:\t\n\r\0\x0B");
        if (! $this->isPlanName($name)) {
            return;
        }

        $features = $this->extractFeatureTexts($container);
        $audience = $this->firstPricingText($container, '.card-text, [class*="audience"], [class*="subtitle"], [class*="description"], p');
        if ($audience && ($this->containsPrice($audience) || $this->isCallToAction($audience))) {
            $audience = null;
        }

        $context = $this->pricingContext($container, $name);
        $variants = $this->explicitPriceVariants($container);
        if ($variants === []) {
            $variants[] = ['text' => $this->spacedText($container), 'label' => ''];
        }

        foreach ($variants as $variant) {
            if ($this->isBenefitValuationOnly($variant['text'])) {
                continue;
            }
            $price = $this->parsePlanPrice($name, $variant['text']);
            if (! $price) {
                continue;
            }
            if ($strictFallback && $price['period'] === null && ! $price['price_on_request']) {
                continue;
            }
            $price['features'] = $features;
            $price['audience'] = $audience;
            $price['variant'] = implode(' · ', array_filter([$context, $variant['label']]));
            $price['raw'] = $this->formatPlanEvidence($price);
            $key = $this->pricingFingerprint($price);
            if (isset($seen[$key])) {
                continue;
            }
            $prices[] = $price;
            $seen[$key] = true;
        }
    }

    /** @param array<int, array<string, mixed>> $prices */
    private function enrichEmbeddedPricesFromDom(Crawler $crawler, array $prices): array
    {
        return array_map(function (array $price) use ($crawler): array {
            $name = (string) ($price['name'] ?? '');
            if ($name === '') {
                return $price;
            }

            $container = $this->findPlanContainerByName($crawler, $name);
            if ($container === null) {
                return $price;
            }

            $features = $this->extractFeatureTexts($container);
            if ($features !== []) {
                $price['features'] = array_values(array_slice(array_unique(array_filter(array_merge(
                    $price['features'] ?? [],
                    $features,
                ))), 0, 6));
            }

            if (empty($price['audience'])) {
                $audience = $this->firstPricingText($container, '.card-text, [class*="audience"], [class*="subtitle"], [class*="description"], p');
                if ($audience && ! $this->containsPrice($audience) && ! $this->isCallToAction($audience)) {
                    $price['audience'] = $audience;
                }
            }

            $price['raw'] = $this->formatPlanEvidence($price);

            return $price;
        }, $prices);
    }

    private function findPlanContainerByName(Crawler $crawler, string $planName): ?Crawler
    {
        $target = $this->canonicalPlanKey($planName);
        if ($target === '') {
            return null;
        }

        $bestContainer = null;
        $bestScore = -1;
        $crawler->filter('h1, h2, h3, h4, span, strong, p, div')->each(function (Crawler $label) use ($target, &$bestContainer, &$bestScore): void {
            if ($this->isInsidePricingNoise($label) || $this->isInheritedPlanReference($label)) {
                return;
            }

            $text = $this->cleanPricingText($label->text(''));
            if ($text === '' || mb_strlen($text) > 120 || $this->canonicalPlanKey($text) !== $target) {
                return;
            }

            $container = $this->pricingContainer($label);
            if ($container === null) {
                return;
            }

            $score = count($this->extractFeatureTexts($container, 12)) * 3
                + ($this->containsPrice($this->spacedText($container)) ? 2 : 0);
            if ($score > $bestScore) {
                $bestContainer = $container;
                $bestScore = $score;
            }
        });

        return $bestContainer;
    }

    private function extractFeatureTexts(Crawler $container, int $limit = 6): array
    {
        $features = $container->filter('li:not(.excluded)')->each(fn (Crawler $feature) => $this->cleanPricingText($feature->text('')));

        return array_values(array_slice(array_unique(array_filter($features, fn (string $feature): bool => $this->isPlanFeature($feature))), 0, $limit));
    }

    private function isPlanFeature(string $feature): bool
    {
        if (mb_strlen($feature) < 5 || mb_strlen($feature) > 220 || $this->isCallToAction($feature) || $this->containsPrice($feature)) {
            return false;
        }

        $normalized = Str::ascii(mb_strtolower($feature));

        return preg_match('/\b(?:en plus de l[\' ]offre|toutes? les fonctionnalites? du plan|includes? (?:the )?plan)\b/u', $normalized) !== 1;
    }

    /** @return array<int, array{text:string,label:string}> */
    private function explicitPriceVariants(Crawler $container): array
    {
        $variants = [];
        $container->filter('[class*="price-amount"], [data-price]')->each(function (Crawler $amountNode) use ($container, &$variants): void {
            $amount = $this->cleanPricingText((string) ($amountNode->attr('data-price') ?: $amountNode->text('')));
            if ($amount === '' || preg_match('/\d/u', $amount) !== 1) {
                return;
            }

            $class = mb_strtolower((string) $amountNode->attr('class'));
            preg_match('/(?:choice|option|variant)[-_]?(\d+)/u', $class, $choiceMatch);
            $choice = $choiceMatch[1] ?? null;
            $mode = match (true) {
                str_contains($class, 'yearly'), str_contains($class, 'annual') => 'yearly',
                str_contains($class, 'monthly') => 'monthly',
                default => '',
            };

            $currency = $this->firstPricingText($container, '.price-currency, [class*="currency"]') ?? '';
            $period = $mode === 'yearly'
                ? ($this->firstPricingText($container, '.period, [class*="period"]') ?? '/ mois')
                : ($this->firstPricingText($container, '.period, [class*="period"]') ?? '');
            $prefix = $this->choiceText($container, '.before-amount', $choice);
            $tax = $this->choiceText($container, '.price-after', $choice);
            $billing = $mode === 'yearly' ? $this->choiceText($container, '.yearly-info', $choice) : '';
            $label = $this->choiceLabel($container, $choice);
            if ($mode === 'yearly' && ! str_contains(mb_strtolower($label), 'annuel')) {
                $label = trim($label.' · Facturation annuelle', ' ·');
            } elseif ($mode === 'monthly' && ! str_contains(mb_strtolower($label), 'mensuel')) {
                $label = trim($label.' · Facturation mensuelle', ' ·');
            }

            $billingMode = $mode === 'yearly' ? 'facturation annuelle' : ($mode === 'monthly' ? 'facturation mensuelle' : '');
            $text = trim(implode(' ', array_filter(
                [$prefix, $amount, $currency, $period, $tax, $billingMode, $billing],
                fn ($value) => $value !== null && $value !== '',
            )));
            $key = mb_strtolower($text.'|'.$label);
            $variants[$key] = ['text' => $text, 'label' => $label];
        });

        return array_values($variants);
    }

    private function choiceText(Crawler $container, string $baseSelector, ?string $choice): string
    {
        $selector = $choice ? $baseSelector.'.choice-'.$choice.', '.$baseSelector.'[data-choice="'.$choice.'"]' : $baseSelector;
        $text = $this->firstPricingText($container, $selector);
        if ($text === null && $choice !== null) {
            $text = $this->firstPricingText($container, $baseSelector);
        }

        return $text ?? '';
    }

    private function choiceLabel(Crawler $container, ?string $choice): string
    {
        if ($choice === null) {
            return '';
        }

        return $this->firstPricingText($container, '[data-double-card-choice="'.$choice.'"], [data-choice="'.$choice.'"], [data-variant="'.$choice.'"]') ?? '';
    }

    private function spacedText(Crawler $container): string
    {
        $node = $container->getNode(0);
        if (! $node?->ownerDocument) {
            return $this->cleanPricingText($container->text(''));
        }

        $xpath = new \DOMXPath($node->ownerDocument);
        $parts = [];
        foreach ($xpath->query('.//text()[normalize-space()]', $node) ?: [] as $textNode) {
            $text = $this->cleanPricingText((string) $textNode->nodeValue);
            if ($text !== '' && ! $this->isCallToAction($text)) {
                $parts[] = $text;
            }
        }

        return $this->cleanPricingText(implode(' ', $parts));
    }

    private function appendCompactTablePrices(Crawler $crawler, array &$prices, array &$seen): void
    {
        $crawler->filter('table')->each(function (Crawler $table) use (&$prices, &$seen): void {
            if ($this->isInsidePricingNoise($table) || $table->filter('tr')->count() > 8) {
                return;
            }

            $rows = $table->filter('tr');
            if ($rows->count() < 2) {
                return;
            }
            $headers = $rows->first()->filter('th, td')->each(fn (Crawler $cell) => $this->cleanPricingText($cell->text('')));
            if (count($headers) < 2 || count($headers) > 10) {
                return;
            }

            $rows->each(function (Crawler $row, int $rowIndex) use ($headers, &$prices, &$seen): void {
                if ($rowIndex === 0) {
                    return;
                }
                $cells = $row->filter('th, td')->each(fn (Crawler $cell) => $this->cleanPricingText($cell->text('')));
                if (count($cells) !== count($headers) || preg_match('/^(?:prix|tarifs?|abonnement|mensuel|annuel|price)/iu', $cells[0] ?? '') !== 1) {
                    return;
                }

                foreach (array_slice($headers, 1, null, true) as $column => $name) {
                    if (! $this->isPlanName($name) || empty($cells[$column])) {
                        continue;
                    }
                    $price = $this->parsePlanPrice($name, $cells[$column]);
                    if (! $price) {
                        continue;
                    }
                    $price['features'] = [];
                    $price['audience'] = null;
                    $price['variant'] = 'Tableau tarifaire';
                    $price['raw'] = $this->formatPlanEvidence($price);
                    $key = $this->pricingFingerprint($price);
                    if (! isset($seen[$key])) {
                        $prices[] = $price;
                        $seen[$key] = true;
                    }
                }
            });
        });
    }

    private function appendStructuredDataPrices(Crawler $crawler, array &$prices, array &$seen): void
    {
        $offers = [];
        $walk = function (mixed $value) use (&$walk, &$offers): void {
            if (! is_array($value)) {
                return;
            }
            $type = mb_strtolower(is_array($value['@type'] ?? null) ? implode(' ', $value['@type']) : (string) ($value['@type'] ?? ''));
            if (str_contains($type, 'offer') && isset($value['price'])) {
                $offers[] = $value;
            }
            foreach ($value as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };

        $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $script) use ($walk): void {
            $data = json_decode(trim($script->text('')), true);
            if (is_array($data)) {
                $walk($data);
            }
        });

        foreach (array_slice($offers, 0, 60) as $offer) {
            $description = $this->cleanPricingText((string) ($offer['description'] ?? ''));
            $name = $this->cleanPricingText((string) ($offer['name'] ?? data_get($offer, 'itemOffered.name', '')));
            if ($name === '' && preg_match('/^([^—–:|]{2,80})\s*[—–:|]/u', $description, $nameMatch) === 1) {
                $name = trim($nameMatch[1]);
            }
            $currency = strtoupper((string) ($offer['priceCurrency'] ?? data_get($offer, 'priceSpecification.priceCurrency', '')));
            $amount = $offer['price'] ?? data_get($offer, 'priceSpecification.price');
            if (! $this->isPlanName($name) || ! is_numeric($amount)) {
                continue;
            }
            $duration = strtoupper((string) data_get($offer, 'priceSpecification.billingDuration', ''));
            $unit = mb_strtolower((string) data_get($offer, 'priceSpecification.unitText', ''));
            $period = match (true) {
                str_contains($duration, 'P1Y') || str_contains($unit, 'year') || str_contains($unit, 'an') || preg_match('/\b(?:annuel|annual|yearly)\b/iu', $name) === 1 => ' par an',
                str_contains($duration, 'P1M') || str_contains($unit, 'month') || str_contains($unit, 'mois') || preg_match('/\b(?:mensuel|monthly)\b/iu', $name) === 1 => ' par mois',
                (float) $amount === 0.0 => ' par mois',
                default => '',
            };
            $price = $this->parsePlanPrice($name, $amount.' '.$currency.$period);
            if (! $price) {
                continue;
            }
            $price['features'] = [];
            $price['audience'] = $description ?: null;
            $price['variant'] = 'Données structurées de la page';
            $price['raw'] = $this->formatPlanEvidence($price);
            $key = $this->pricingFingerprint($price);
            if (! isset($seen[$key])) {
                $prices[] = $price;
                $seen[$key] = true;
            }
        }
    }

    private function pricingFingerprint(array $price): string
    {
        return hash('sha256', mb_strtolower(implode('|', [
            $price['name'] ?? '',
            $price['currency'] ?? '',
            $price['monthly_price'] ?? '',
            $price['annual_total'] ?? '',
            $price['annual_effective_monthly'] ?? '',
            $price['audience'] ?? '',
            $price['variant'] ?? '',
            implode(' ', $price['features'] ?? []),
        ])));
    }

    /**
     * Regroupe les états techniques du DOM en une formule commerciale unique.
     * Les montants extrêmes et chaque variante sourcée restent disponibles.
     *
     * @param  array<int, array<string, mixed>>  $prices
     * @return array<int, array<string, mixed>>
     */
    private function consolidatePrices(array $prices): array
    {
        $groups = [];

        foreach ($prices as $price) {
            $key = $this->canonicalPlanKey((string) ($price['name'] ?? ''));
            if ($key === '') {
                continue;
            }

            $groups[$key] ??= [];
            $groups[$key][] = $price;
        }

        $result = [];
        foreach ($groups as $variants) {
            $base = $variants[0];
            $base['name'] = $this->canonicalPlanName($variants);
            $base['features'] = collect($variants)
                ->flatMap(fn (array $variant) => $variant['features'] ?? [])
                ->filter()
                ->unique()
                ->take(6)
                ->values()
                ->all();
            $base['audience'] = collect($variants)
                ->pluck('audience')
                ->filter()
                ->unique()
                ->implode(' · ') ?: null;
            $base['variant'] = count($variants) > 1
                ? 'Tarif variable selon le profil, le volume ou le mode de facturation'
                : (string) ($base['variant'] ?? '');
            $base['price_variants'] = $this->compactPriceVariants($variants);

            foreach (['monthly_price', 'annual_total', 'annual_effective_monthly'] as $field) {
                $values = collect($variants)
                    ->pluck($field)
                    ->filter(fn ($value) => $value !== null && is_numeric($value))
                    ->map(fn ($value) => (float) $value)
                    ->unique()
                    ->sort()
                    ->values();
                $base[$field] = $values->isNotEmpty() ? $values->first() : null;
                $base[$field.'_max'] = $values->isNotEmpty() ? $values->last() : null;
            }

            // Pour l'affichage, un prix mensuel et un équivalent mensuel annuel
            // sont comparables. La formule expose donc une seule fourchette.
            $monthlyValues = collect($variants)
                ->map(fn (array $variant) => $variant['monthly_price'] ?? $variant['annual_effective_monthly'] ?? null)
                ->filter(fn ($value) => $value !== null && is_numeric($value))
                ->map(fn ($value) => (float) $value)
                ->unique()
                ->sort()
                ->values();
            if ($monthlyValues->isNotEmpty()) {
                $base['monthly_price'] = $monthlyValues->first();
                $base['monthly_price_max'] = $monthlyValues->last();
            }

            $base['currency'] = collect($variants)->pluck('currency')->filter()->first();
            $base['price_on_request'] = collect($variants)->contains(fn (array $variant) => (bool) ($variant['price_on_request'] ?? false));
            $base['starting_at'] = collect($variants)->contains(fn (array $variant) => (bool) ($variant['starting_at'] ?? false))
                || $this->hasPriceRange($base);
            $base['promotional_price'] = collect($variants)->contains(fn (array $variant) => (bool) ($variant['promotional_price'] ?? false));
            $base['tax_label'] = collect($variants)->pluck('tax_label')->filter()->unique()->count() === 1
                ? collect($variants)->pluck('tax_label')->filter()->first()
                : null;
            $base['raw'] = $this->formatPlanEvidence($base);
            $result[] = $base;
        }

        return array_values($result);
    }

    /** @param array<int, array<string, mixed>> $variants */
    private function canonicalPlanName(array $variants): string
    {
        return collect($variants)
            ->pluck('name')
            ->filter()
            ->sortBy(fn (string $name) => mb_strlen($name))
            ->first() ?: 'Offre';
    }

    private function canonicalPlanKey(string $name): string
    {
        $name = mb_strtolower(Str::ascii($this->basePlanName($name)));
        $name = preg_replace('/\s+(?:pour|for)\s+(?:les?|la|le)?\s*(?:auto.?entrepreneurs?|micro.?entreprises?|ei|sci|lmnp|societes?|tpe|pme|enterprise).*$/u', '', $name) ?? $name;
        $name = preg_replace('/\s*[—–|:]\s*(?:auto.?entrepreneurs?|micro.?entreprises?|ei|sci|lmnp|societes?|mensuel|annuel|monthly|yearly).*$/u', '', $name) ?? $name;

        return trim(preg_replace('/[^a-z0-9]+/u', '-', $name) ?? '', '-');
    }

    private function basePlanName(string $name): string
    {
        $name = $this->cleanPricingText($name);

        return trim(preg_replace('/\s*[\[(](?:mensuel|monthly|annuel|annual|yearly)[\])\]]\s*$/iu', '', $name) ?? $name);
    }

    /** @param array<int, array<string, mixed>> $variants */
    private function compactPriceVariants(array $variants): array
    {
        return collect($variants)->map(function (array $variant): array {
            return [
                'context' => mb_substr($this->cleanPricingText(implode(' · ', array_filter([
                    $variant['variant'] ?? null,
                    $variant['audience'] ?? null,
                ]))), 0, 300),
                'price' => $this->formatSinglePrice($variant),
                'billing_period' => $variant['period'] ?? null,
            ];
        })->unique(fn (array $variant) => mb_strtolower($variant['context'].'|'.$variant['price']))
            ->take(30)
            ->values()
            ->all();
    }

    private function hasPriceRange(array $price): bool
    {
        foreach (['monthly_price', 'annual_total', 'annual_effective_monthly'] as $field) {
            if (($price[$field] ?? null) !== null
                && ($price[$field.'_max'] ?? null) !== null
                && (float) $price[$field] !== (float) $price[$field.'_max']) {
                return true;
            }
        }

        return false;
    }

    private function pricingContainer(Crawler $heading): ?Crawler
    {
        foreach ($heading->ancestors() as $node) {
            $candidate = new Crawler($node);
            $class = mb_strtolower((string) $candidate->attr('class'));
            if (str_contains($class, 'pricing-card') || str_contains($class, 'price-card') || str_contains($class, 'plan-card')) {
                return $candidate;
            }
            $text = $this->clean($candidate->text(''));
            if (mb_strlen($text) > 12_000 || $candidate->filter('h1, h2, h3, h4')->count() > 2) {
                continue;
            }
            if ($this->containsPrice($text)) {
                return $candidate;
            }
        }

        return null;
    }

    private function parsePlanPrice(string $name, string $text): ?array
    {
        $hasNumericPrice = preg_match('/(?:€|\$|£)\s?\d+(?:[.,]\d{1,2})?|\d+(?:[.,]\d{1,2})?\s?(?:€|EUR|USD|GBP|dollars?|euros?|livres?)/iu', $text, $match) === 1;
        $priceOnRequest = preg_match('/sur (?:demande|devis)|contactez|contact sales|custom pricing|request (?:a )?quote/iu', $text) === 1;
        $freePlan = ! $hasNumericPrice && preg_match('/\b(?:gratuit(?:e)?|free)\b/iu', $text) === 1;
        if (! $hasNumericPrice && ! $priceOnRequest && ! $freePlan) {
            return null;
        }

        $amount = $freePlan ? 0.0 : null;
        if ($hasNumericPrice) {
            preg_match('/\d+(?:[.,]\d{1,2})?/', $match[0], $number);
            $amount = (float) str_replace(',', '.', $number[0]);
        }
        $normalized = mb_strtolower($text);
        $annualBilling = preg_match('/paiement annuel|payement annuel|facturation annuelle|facturé annuellement|billed annually|annual billing/iu', $normalized) === 1;
        $yearlyAmount = preg_match('/\/\s?(?:an|year|yr)\b|par an|per year/iu', $normalized) === 1;
        $monthlyAmount = preg_match('/mois|month|\/\s?mo\b|mensuel/iu', $normalized) === 1;
        $freeRecurringPlan = $amount === 0.0 && preg_match('/gratuit|free|sans engagement/iu', $normalized) === 1;

        return [
            'name' => mb_substr($name, 0, 255),
            'raw' => '',
            'currency' => match (true) {
                ! $hasNumericPrice => null,
                str_contains($match[0], '€') || stripos($match[0], 'eur') !== false || stripos($match[0], 'euro') !== false => 'EUR',
                str_contains($match[0], '£') || stripos($match[0], 'gbp') !== false || stripos($match[0], 'livre') !== false => 'GBP',
                default => 'USD',
            },
            'monthly_price' => $amount !== null && ($monthlyAmount || $freeRecurringPlan) && ! $annualBilling ? $amount : null,
            'annual_total' => $amount !== null ? ($yearlyAmount ? $amount : ($annualBilling ? round($amount * 12, 2) : null)) : null,
            'annual_effective_monthly' => $amount !== null ? ($yearlyAmount ? round($amount / 12, 2) : ($annualBilling ? $amount : null)) : null,
            'period' => ($annualBilling || $yearlyAmount) ? 'year' : (($monthlyAmount || $freeRecurringPlan) ? 'month' : null),
            'unit' => preg_match('/utilisateur|user|seat/iu', $normalized) ? 'per_user' : null,
            'promotional_price' => preg_match('/au lieu de|instead of|save|économisez|promotion|remise|soldes|\-\s*\d+\s*%/iu', $normalized) === 1,
            'starting_at' => preg_match('/\b(?:dès|à partir de|from|starting at)\b/iu', $normalized) === 1,
            'tax_label' => preg_match('/\bHT\b/iu', $text) === 1 ? 'HT' : (preg_match('/\bTTC\b/iu', $text) === 1 ? 'TTC' : null),
            'price_on_request' => $priceOnRequest,
            'features' => [],
        ];
    }

    private function isBenefitValuationOnly(string $text): bool
    {
        $hasValuation = preg_match('/\b(?:d[’\']une\s+)?valeur\s+(?:annuelle\s+)?(?:de\s+)?[^.!?]{0,60}(?:€|\$|£)|\bavantages?\s+(?:d[’\']une\s+)?valeur\b/iu', $text) === 1;
        if (! $hasValuation) {
            return false;
        }

        // Une vraie carte peut aussi citer la valeur des avantages après son
        // prix. Dans ce cas, le montant récurrent explicite reste exploitable.
        return preg_match('/(?:€|\$|£)\s?\d+(?:[.,]\d{1,2})?\s*\/\s*(?:mois|mo|month)|\d+(?:[.,]\d{1,2})?\s*(?:€|\$|£)\s*\/\s*(?:mois|mo|month)/iu', $text) !== 1;
    }

    private function containsPrice(string $text): bool
    {
        return preg_match('/(?:€|\$|£)\s?\d|\d+(?:[.,]\d{1,2})?\s?(?:€|EUR|USD|GBP|dollars?|euros?|livres?)|sur (?:demande|devis)|contact sales|custom pricing/iu', $text) === 1;
    }

    private function removePricingNoise(Crawler $crawler): void
    {
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $class = "translate(@class, '{$upper}', '{$lower}')";
        $id = "translate(@id, '{$upper}', '{$lower}')";
        $xpath = '//*[self::header or self::footer or self::nav or self::script or self::style or self::noscript or self::template or self::form'
            ." or contains({$class}, 'faq') or contains({$class}, 'testimonial') or contains({$class}, 'review')"
            ." or contains({$class}, 'comparison') or contains({$class}, 'comparative') or contains({$class}, 'comparatif') or contains({$class}, 'matrix') or contains({$class}, 'matrice')"
            ." or contains({$class}, 'feature-table') or contains({$class}, 'compare-table') or contains({$id}, 'compare-table')]";

        $this->removeNodes($crawler->filterXPath($xpath));
        $crawler->filter('button, a')->each(function (Crawler $node): void {
            if ($this->isCallToAction($this->clean($node->text('')))) {
                $this->removeNode($node);
            }
        });
    }

    private function isInsidePricingNoise(Crawler $node): bool
    {
        $nodes = [];
        if ($node->getNode(0)) {
            $nodes[] = $node->getNode(0);
        }
        foreach ($node->ancestors() as $ancestorNode) {
            $nodes[] = $ancestorNode;
        }
        foreach ($nodes as $ancestorNode) {
            if (! $ancestorNode instanceof \DOMElement) {
                continue;
            }
            if (in_array(mb_strtolower($ancestorNode->tagName), ['header', 'footer', 'nav', 'form'], true)) {
                return true;
            }
            $identity = mb_strtolower($ancestorNode->getAttribute('class').' '.$ancestorNode->getAttribute('id'));
            if (preg_match('/faq|questions?|testimonial|reviews?|avis|comparison|comparative|comparatif|matrix|matrice|feature.?table|compare.?table/u', $identity) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isInheritedPlanReference(Crawler $node): bool
    {
        foreach ($node->ancestors() as $ancestorNode) {
            if (! $ancestorNode instanceof \DOMElement) {
                continue;
            }
            if (mb_strtolower($ancestorNode->tagName) === 'li') {
                $text = $this->cleanPricingText($ancestorNode->textContent);

                return preg_match('/\b(?:en plus de l[’\']offre|toutes? les fonctionnalités? du plan|includes? (?:the )?plan)\b/iu', $text) === 1;
            }
        }

        return false;
    }

    private function removeNodes(Crawler $nodes): void
    {
        $toRemove = [];
        $nodes->each(function (Crawler $node) use (&$toRemove): void {
            if ($node->getNode(0)) {
                $toRemove[] = $node->getNode(0);
            }
        });
        foreach ($toRemove as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    private function removeNode(Crawler $crawler): void
    {
        $node = $crawler->getNode(0);
        if ($node?->parentNode) {
            $node->parentNode->removeChild($node);
        }
    }

    private function firstPricingText(Crawler $container, string $selector): ?string
    {
        $nodes = $container->filter($selector);
        if ($nodes->count() === 0) {
            return null;
        }

        $text = $this->cleanPricingText($nodes->first()->text(''));

        return $text !== '' ? $text : null;
    }

    private function cleanPricingText(string $value): string
    {
        $value = $this->clean($value);
        $value = preg_replace('/([€$£])\s*(\d+)\s*([,.])\s*(\d{1,2})\b/u', '$1 $2$3$4', $value) ?? $value;
        $value = preg_replace('/\b(\d+)\s*([,.])\s*(\d{1,2})\s*([€$£])/u', '$1$2$3 $4', $value) ?? $value;
        $withoutCta = preg_replace('/\b(?:Démarrer|Comparez? (?:les|toutes les) offres?|Comparer toutes les fonctionnalités|Prendre rendez-vous|En savoir plus)\b/iu', ' ', $value);
        if ($withoutCta !== null) {
            $value = $withoutCta;
        }
        $normalized = preg_replace('/\s+/u', ' ', $value);

        return trim($normalized ?? $value);
    }

    private function isCallToAction(string $value): bool
    {
        return preg_match('/^(?:démarrer|commencer|s[’\']inscrire|inscrivez-vous|comparez? (?:les|toutes les) offres?|comparer toutes les fonctionnalités|prendre rendez-vous|en savoir plus|essayer|choisir cette offre|contactez-nous)$/iu', trim($value)) === 1;
    }

    private function pricingContext(Crawler $container, string $planName): string
    {
        $node = $container->getNode(0);
        if (! $node?->ownerDocument) {
            return '';
        }

        $labels = [];

        $ancestor = $node->parentNode;
        for ($depth = 0; $ancestor instanceof \DOMElement && $depth < 4; $depth++, $ancestor = $ancestor->parentNode) {
            foreach (['data-segment', 'data-audience', 'data-profile', 'data-plan-group', 'aria-label'] as $attribute) {
                $value = $this->cleanPricingText($ancestor->getAttribute($attribute));
                if ($value !== '' && mb_strlen($value) <= 120) {
                    $labels[] = $value;
                }
            }

            $classTokens = preg_split('/\s+/u', mb_strtolower($ancestor->getAttribute('class'))) ?: [];
            foreach ($classTokens as $token) {
                if (preg_match('/^(?:micro|bnc|b2b|b2c|tpe|pme|eti|enterprise|startup|freelance|association|ecommerce|e-commerce|monthly|yearly)$/u', $token) === 1) {
                    $labels[] = $token;
                }
            }
        }

        return mb_substr(implode(' · ', array_values(array_unique($labels))), 0, 300);
    }

    private function formatPlanEvidence(array $price): string
    {
        if ($price['price_on_request'] && ! $this->hasNumericPlanPrice($price)) {
            $label = 'Prix sur demande';
        } else {
            $amount = $price['monthly_price'] ?? $price['annual_effective_monthly'] ?? $price['annual_total'];
            $maximum = $price['monthly_price'] !== null
                ? ($price['monthly_price_max'] ?? $amount)
                : ($price['annual_effective_monthly'] !== null
                    ? ($price['annual_effective_monthly_max'] ?? $amount)
                    : ($price['annual_total_max'] ?? $amount));
            $number = rtrim(rtrim(number_format((float) $amount, 2, ',', ' '), '0'), ',');
            $maximumNumber = rtrim(rtrim(number_format((float) $maximum, 2, ',', ' '), '0'), ',');
            $currency = match ($price['currency']) {
                'EUR' => '€',
                'GBP' => '£',
                default => $price['currency'] ?: '',
            };
            $period = $price['monthly_price'] !== null || $price['annual_effective_monthly'] !== null
                ? ' / mois'
                : ($price['annual_total'] !== null ? ' / an' : '');
            $hasRange = (float) $amount !== (float) $maximum;
            $label = $hasRange
                ? 'De '.trim($number.' '.$currency).' à '.trim($maximumNumber.' '.$currency).$period
                : ($price['starting_at'] ? 'Dès ' : '').trim($number.' '.$currency).$period;
            $label .= $price['tax_label'] ? ' '.$price['tax_label'] : '';
            if ($price['annual_effective_monthly'] !== null && $price['annual_total'] !== null) {
                $total = rtrim(rtrim(number_format((float) $price['annual_total'], 2, ',', ' '), '0'), ',');
                $totalMax = rtrim(rtrim(number_format((float) ($price['annual_total_max'] ?? $price['annual_total']), 2, ',', ' '), '0'), ',');
                $label .= $total === $totalMax
                    ? " (facturation annuelle : {$total} {$currency})"
                    : " (facturation annuelle : de {$total} à {$totalMax} {$currency})";
            }
            if ($hasRange) {
                $label .= ' (selon profil ou conditions)';
            }
        }

        $parts = ["Offre : {$price['name']}", "Prix : {$label}"];
        if (! empty($price['variant'])) {
            $parts[] = 'Variante / contexte : '.$price['variant'];
        }
        if (! empty($price['audience'])) {
            $parts[] = 'Public / objectif : '.$price['audience'];
        }
        if ($price['features'] !== []) {
            $parts[] = 'Fonctionnalités clés : '.implode(' ; ', array_slice($price['features'], 0, 6));
        }

        return implode("\n", $parts);
    }

    private function formatSinglePrice(array $price): string
    {
        $copy = $price;
        $copy['monthly_price_max'] = $copy['monthly_price'] ?? null;
        $copy['annual_total_max'] = $copy['annual_total'] ?? null;
        $copy['annual_effective_monthly_max'] = $copy['annual_effective_monthly'] ?? null;
        $copy['price_variants'] = [];
        $evidence = $this->formatPlanEvidence($copy);

        return trim((string) preg_replace('/^Prix\s*:\s*/u', '', explode("\n", $evidence)[1] ?? ''));
    }

    private function hasNumericPlanPrice(array $price): bool
    {
        return ($price['monthly_price'] ?? null) !== null
            || ($price['annual_effective_monthly'] ?? null) !== null
            || ($price['annual_total'] ?? null) !== null;
    }

    private function isPlanName(string $name): bool
    {
        $wordCount = count(preg_split('/\s+/u', trim($name)) ?: []);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || $wordCount > 10 || preg_match('/\d{3,}/', $name) || str_contains($name, '?')) {
            return false;
        }

        if (preg_match('/^(tarifs?|pricing|comparatif|comparaison|faq|fonctionnalités?|features?|complétez|vous voyez grand|commencez|découvrez|économisez|chaque plan\b|toutes? les fonctionnalités?\b|tous? les avantages?\b|tout ce qui est inclus\b)/iu', $name) === 1) {
            return false;
        }

        // Un véritable plan peut s'appeler "Simple". On rejette donc les
        // adjectifs marketing seulement lorsqu'ils forment une phrase-slogan.
        return preg_match('/^(?:des?|nos?|vos?|une?)\s+(?:tarifs?|prix|offres?|formules?)\b.{0,70}\b(?:clairs?|transparent(?:e|es|s)?|simples?|adaptés?|justes?|sans surprise)\b/iu', $name) !== 1;
    }

    private function assertRobotsAllows(string $url): void
    {
        $parts = parse_url($url);
        $robotsUrl = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').'/robots.txt';
        try {
            $response = $this->http()->timeout(8)->withHeaders(['User-Agent' => 'BusinessKitResearchBot/1.0'])->get($robotsUrl);
            if (! $response->successful()) {
                return;
            }
            $applies = false;
            $path = $parts['path'] ?? '/';
            foreach (preg_split('/\R/', $response->body()) as $line) {
                $line = trim(preg_replace('/#.*$/', '', $line));
                if (stripos($line, 'user-agent:') === 0) {
                    $agent = trim(substr($line, 11));
                    $applies = $agent === '*' || stripos($agent, 'BusinessKitResearchBot') !== false;
                } elseif ($applies && stripos($line, 'disallow:') === 0) {
                    $rule = trim(substr($line, 9));
                    if ($rule !== '' && str_starts_with($path, $rule)) {
                        throw new RuntimeException('Le crawl de cette URL est interdit par robots.txt.');
                    }
                }
            }
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            // Un robots.txt indisponible n’interdit pas la collecte de la page.
        }
    }

    private function validatePublicUrl(string $url): string
    {
        $url = trim($url);
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new RuntimeException('L’URL fournie est invalide.');
        }
        $host = parse_url($url, PHP_URL_HOST);
        $ips = gethostbynamel($host) ?: [];
        if ($ips === []) {
            throw new RuntimeException('Le domaine ne peut pas être résolu.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Les adresses locales ou privées ne peuvent pas être crawlées.');
            }
        }

        return $url;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withOptions(['verify' => $this->caBundlePath() ?: true]);
    }

    private function caBundlePath(): ?string
    {
        $configured = trim((string) config('services.scraping.ca_bundle', ''));
        $candidates = array_filter([
            $configured,
            ini_get('curl.cainfo') ?: null,
            ini_get('openssl.cafile') ?: null,
            getenv('CURL_CA_BUNDLE') ?: null,
            getenv('SSL_CERT_FILE') ?: null,
            getenv('USERPROFILE') ? getenv('USERPROFILE').'/.config/herd/config/php/cacert.pem' : null,
            getenv('USERPROFILE') ? getenv('USERPROFILE').'/.config/herd-lite/bin/cacert.pem' : null,
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function categorize(string $text): string
    {
        return match (true) {
            preg_match('/€|\$|£|prix|tarif|abonnement|billing/iu', $text) === 1 => 'pricing',
            preg_match('/intégration|integration|connect/iu', $text) === 1 => 'integration',
            preg_match('/limite|maximum|restriction|quota/iu', $text) === 1 => 'limitation',
            preg_match('/fonctionnalité|feature|permet|outil/iu', $text) === 1 => 'feature',
            default => 'general',
        };
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }
}
