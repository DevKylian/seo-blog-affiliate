<?php

namespace Tests\Feature;

use App\Livewire\SeoIntelligence;
use App\Models\Article;
use App\Models\ContentRefreshTask;
use App\Models\Keyword;
use App\Models\Plan;
use App\Models\SearchPerformanceSnapshot;
use App\Models\SeoActionItem;
use App\Models\SeoProject;
use App\Models\Setting;
use App\Models\User;
use App\Services\SearchPerformanceImportService;
use App\Services\SeoIntelligenceService;
use App\Services\StructuredDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

final class SeoIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bing_webmaster_import_stores_query_performance(): void
    {
        $project = $this->project();
        Setting::put('bing_webmaster_enabled', '1');
        Setting::put('bing_webmaster_site_url', 'https://www.indy.fr');
        Setting::put('bing_webmaster_api_key', 'bing-test-key-123', true);

        $date = now()->subDays(3)->startOfDay();
        Http::fake([
            'https://ssl.bing.com/webmaster/api.svc/json/GetQueryStats*' => Http::response([
                'd' => [[
                    'Query' => 'logiciel facturation freelance',
                    'Date' => '/Date('.($date->timestamp * 1000).')/',
                    'Clicks' => 7,
                    'Impressions' => 140,
                    'AvgImpressionPosition' => 6.4,
                ]],
            ]),
        ]);

        $count = app(SearchPerformanceImportService::class)->importBing(now()->subDays(7), now()->subDays(2));

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('search_performance_snapshots', [
            'seo_project_id' => $project->id,
            'provider' => 'bing_webmaster',
            'query' => 'logiciel facturation freelance',
            'clicks' => 7,
            'impressions' => 140,
        ]);
    }

    public function test_seo_intelligence_creates_actions_refresh_tasks_and_briefs_without_duplicates(): void
    {
        $project = $this->project();
        $keyword = $this->keyword($project, 'logiciel facture gratuit');
        $article = $this->article($project, $keyword, [
            'title' => 'Logiciel facture : guide pour indépendants',
            'body' => '## Guide pratique'.PHP_EOL.'Ce contenu parle surtout de gestion administrative générale.',
            'status' => 'published',
            'published_at' => now()->subDays(30),
        ]);

        SearchPerformanceSnapshot::query()->create([
            'seo_project_id' => $project->id,
            'article_id' => $article->id,
            'provider' => 'google_search_console',
            'site_url' => 'https://BusinessKit.test',
            'page_url' => $article->public_url,
            'url_hash' => sha1($article->public_url),
            'query' => 'logiciel facture gratuit auto entrepreneur',
            'query_hash' => sha1('logiciel facture gratuit auto entrepreneur'),
            'date_from' => now()->subDays(28)->toDateString(),
            'date_to' => now()->subDays(2)->toDateString(),
            'clicks' => 2,
            'impressions' => 400,
            'ctr' => 0.005,
            'position' => 8.2,
            'imported_at' => now(),
        ]);

        $first = app(SeoIntelligenceService::class)->analyze(28);
        $second = app(SeoIntelligenceService::class)->analyze(28);

        $this->assertGreaterThanOrEqual(2, $first['actions']);
        $this->assertSame(0, $second['actions']);
        $this->assertDatabaseHas('seo_action_items', ['article_id' => $article->id, 'type' => 'refresh_content', 'status' => 'queued']);
        $this->assertDatabaseHas('seo_action_items', ['article_id' => $article->id, 'type' => 'rewrite_title_meta', 'status' => 'queued']);
        $this->assertDatabaseHas('content_refresh_tasks', ['article_id' => $article->id, 'reason' => 'performance_refresh', 'status' => 'queued']);
        $this->assertSame(1, ContentRefreshTask::query()->where('article_id', $article->id)->where('reason', 'performance_refresh')->count());
        $this->assertSame(SeoActionItem::query()->count(), SeoActionItem::query()->distinct('id')->count('id'));
        $this->assertDatabaseHas('serp_differentiation_briefs', [
            'seo_project_id' => $project->id,
            'article_id' => $article->id,
            'primary_keyword' => 'logiciel facture gratuit',
        ]);
    }

    public function test_generation_directive_uses_search_brief_for_keyword(): void
    {
        $project = $this->project();
        $keyword = $this->keyword($project, 'logiciel facturation btp');

        SearchPerformanceSnapshot::query()->create([
            'seo_project_id' => $project->id,
            'provider' => 'google_search_console',
            'site_url' => 'https://BusinessKit.test',
            'url_hash' => sha1('site-wide'),
            'query' => 'logiciel facturation btp artisans',
            'query_hash' => sha1('logiciel facturation btp artisans'),
            'date_from' => now()->subDays(28)->toDateString(),
            'date_to' => now()->subDays(2)->toDateString(),
            'clicks' => 4,
            'impressions' => 160,
            'ctr' => 0.025,
            'position' => 9.5,
            'imported_at' => now(),
        ]);

        $directive = app(SeoIntelligenceService::class)->generationDirective($project, $keyword, [
            'primary_keyword' => 'logiciel facturation btp',
            'topic' => 'facturation BTP',
        ]);

        $this->assertStringContainsString('DIFF', $directive);
        $this->assertStringContainsString('btp', mb_strtolower($directive));
        $this->assertDatabaseHas('serp_differentiation_briefs', [
            'seo_project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'primary_keyword' => 'logiciel facturation btp',
        ]);
    }

    public function test_structured_data_exposes_article_and_verified_software_offers(): void
    {
        $project = $this->project();
        Plan::query()->create([
            'seo_project_id' => $project->id,
            'name' => 'Essentiel',
            'raw_price' => '0 € / mois',
            'monthly_price' => 0,
            'currency' => 'EUR',
            'is_active' => true,
            'verified_at' => now(),
        ]);
        $article = $this->article($project, null, [
            'status' => 'published',
            'published_at' => now()->subDay(),
        ])->load(['project.plans', 'categories', 'tags', 'keyword']);

        $schema = app(StructuredDataService::class)->article($article);
        $toolSchema = app(StructuredDataService::class)->tool($project->load('plans'));

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertTrue(collect($schema['@graph'])->contains(fn (array $node): bool => ($node['@type'] ?? null) === 'Article'));
        $software = collect($toolSchema['@graph'])->first(fn (array $node): bool => ($node['@type'] ?? null) === 'SoftwareApplication');
        $this->assertNotEmpty($software['offers']);
        $this->assertSame(0.0, (float) $software['offers'][0]['price']);
        $this->assertSame('EUR', $software['offers'][0]['priceCurrency']);
    }

    public function test_admin_can_render_seo_intelligence_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(SeoIntelligence::class)
            ->assertSee('SEO Intelligence')
            ->assertSee('Lancer la boucle SEO');
    }

    private function project(): SeoProject
    {
        return SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-'.uniqid(),
            'website_url' => 'https://www.indy.fr',
            'pricing_url' => 'https://www.indy.fr/tarifs',
            'country' => 'FR',
            'currency' => 'EUR',
            'status' => 'active',
            'description' => 'Logiciel de gestion pour indépendants.',
            'competitors' => ['Abby', 'Freebe', 'Pennylane'],
        ]);
    }

    private function keyword(SeoProject $project, string $keyword): Keyword
    {
        return Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => $keyword,
            'search_volume' => 1000,
            'keyword_difficulty' => 40,
            'intent' => 'Commerciale',
            'intent_type' => 'solution',
            'affiliate_cluster' => 'facturation',
        ]);
    }

    private function article(SeoProject $project, ?Keyword $keyword = null, array $overrides = []): Article
    {
        return Article::query()->create([
            'seo_project_id' => $project->id,
            'keyword_id' => $keyword?->id,
            'type' => 'informational',
            'title' => 'Logiciel de facturation freelance',
            'slug' => 'article-'.uniqid(),
            'status' => 'review',
            'primary_keyword' => $keyword?->keyword ?: 'logiciel de facturation',
            'search_intent' => 'commercial',
            'meta_description' => 'Guide pour choisir un logiciel de facturation.',
            'body' => '## Guide'.PHP_EOL.'Contenu éditorial vérifié.',
            'verified_at' => now(),
            ...$overrides,
        ]);
    }
}
