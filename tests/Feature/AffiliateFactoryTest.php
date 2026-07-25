<?php

namespace Tests\Feature;

use App\Livewire\ContentSchedulerDashboard;
use App\Livewire\Settings;
use App\Models\AffiliateClick;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\KeywordSeed;
use App\Models\SearchIndexingSubmission;
use App\Models\SeoProject;
use App\Models\Setting;
use App\Models\User;
use App\Services\AffiliateSeoDefaults;
use App\Services\SearchEngineIndexingService;
use App\Services\SemrushCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AffiliateFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_defaults_seed_project_with_indy_clusters_and_ctas(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-affiliate-defaults',
            'website_url' => 'https://www.indy.fr',
            'affiliate_url' => 'https://www.indy.fr/?ref=blogseo',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);

        app(AffiliateSeoDefaults::class)->ensureForProject($project);
        app(AffiliateSeoDefaults::class)->ensureForProject($project);

        $this->assertGreaterThanOrEqual(25, $project->keywordSeeds()->count());
        $this->assertSame(1, $project->keywordSeeds()->where('seed', 'Indy avis')->where('intent_type', 'money')->count());
        $this->assertSame(1, $project->keywordSeeds()->where('seed', 'logiciel facture freelance')->where('affiliate_cluster', 'facturation')->count());
        $this->assertGreaterThanOrEqual(7, $project->affiliateBlocks()->count());
        $this->assertSame(1, $project->affiliateBlocks()->where('intent_type', 'money')->where('position', 'final')->count());
    }

    public function test_semrush_import_enriches_keywords_with_affiliate_intent_cluster_and_priority(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-affiliate-import',
            'website_url' => 'https://www.indy.fr',
            'affiliate_url' => 'https://www.indy.fr/?ref=blogseo',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);

        $count = app(SemrushCsvImporter::class)->importText($project, implode("\n", [
            "Keyword\tIntent\tVolume\tTrend\tKD %\tCPC (EUR)",
            "logiciel devis facture\tI C\t2400\t0\t54\t6,02",
            "Indy avis\tC\t590\t0\t24\t3,40",
            "declaration chiffre affaires micro entreprise\tI\t880\t0\t33\t1,20",
        ]));

        $this->assertSame(3, $count);

        $billing = $project->keywords()->where('keyword', 'logiciel devis facture')->first();
        $this->assertSame('facturation', $billing->affiliate_cluster);
        $this->assertSame('solution', $billing->intent_type);
        $this->assertSame('cherche-un-outil', $billing->user_moment);
        $this->assertGreaterThan(70, $billing->affiliate_priority);

        $money = $project->keywords()->where('keyword', 'Indy avis')->first();
        $this->assertSame('outils', $money->affiliate_cluster);
        $this->assertSame('money', $money->intent_type);
        $this->assertGreaterThan($billing->affiliate_priority, $money->affiliate_priority);

        $declaration = $project->keywords()->where('keyword', 'declaration chiffre affaires micro entreprise')->first();
        $this->assertSame('declarations', $declaration->affiliate_cluster);
        $this->assertSame('information', $declaration->intent_type);
        $this->assertSame('declarer et anticiper ses obligations', $declaration->problem_label);
    }

    public function test_public_article_renders_tracked_affiliate_cta_and_redirect_records_click(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-affiliate-render',
            'website_url' => 'https://www.indy.fr',
            'affiliate_url' => 'https://www.indy.fr/?ref=blogseo',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        app(AffiliateSeoDefaults::class)->ensureForProject($project);

        $keyword = Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel devis facture',
            'search_volume' => 2400,
            'keyword_difficulty' => 54,
            'intent' => 'Commerciale',
            'intent_type' => 'solution',
            'affiliate_cluster' => 'facturation',
            'affiliate_priority' => 91,
        ]);
        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'title' => 'Logiciel devis facture freelance',
            'slug' => 'logiciel-devis-facture-freelance',
            'type' => 'best_tools',
            'status' => 'published',
            'primary_keyword' => 'logiciel devis facture',
            'meta_description' => 'Comparer les options utiles pour un freelance.',
            'body' => '## Comparatif utile',
            'content_blocks' => [
                ['type' => 'affiliate_cta', 'position' => 'after_intro'],
                ['type' => 'markdown', 'content' => '## Comparatif utile'],
            ],
            'intent_type' => 'solution',
            'affiliate_priority' => 91,
            'published_at' => now(),
            'verified_at' => now(),
        ]);

        $this->get(route('best-tools.show', $article->slug))
            ->assertOk()
            ->assertSee('Automatiser devis et factures avec Indy')
            ->assertSee('/go/indy-affiliate-render', false)
            ->assertSee('rel="sponsored nofollow"', false);

        $block = $project->affiliateBlocks()->where('intent_type', 'solution')->where('affiliate_cluster', 'facturation')->first();
        $this->get(route('affiliate.redirect', [
            'project' => $project,
            'article' => $article->id,
            'block' => $block?->id,
            'position' => 'after_intro',
        ]))->assertRedirect('https://www.indy.fr/?ref=blogseo');

        $this->assertSame(1, AffiliateClick::query()->count());
        $this->assertDatabaseHas('affiliate_clicks', [
            'seo_project_id' => $project->id,
            'article_id' => $article->id,
            'keyword_id' => $keyword->id,
            'affiliate_cluster' => 'facturation',
            'intent_type' => 'solution',
            'position' => 'after_intro',
        ]);
    }

    public function test_public_home_free_tools_and_sitemap_expose_the_affiliate_hub(): void
    {
        Article::query()->create([
            'seo_project_id' => SeoProject::query()->create([
                'name' => 'Indy',
                'slug' => 'indy-public-hub',
                'website_url' => 'https://www.indy.fr',
                'country' => 'FR',
                'currency' => 'EUR',
            ])->id,
            'title' => 'Guide facture freelance',
            'slug' => 'guide-facture-freelance',
            'type' => 'informational',
            'status' => 'published',
            'meta_description' => 'Comprendre les bases de la facturation freelance.',
            'body' => '## Guide',
            'published_at' => now(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('BusinessKit')
            ->assertSee('TJM freelance');

        $this->get(route('free-tools.index'))
            ->assertOk()
            ->assertSee('Outils gratuits freelance');

        $this->get(route('free-tools.show', 'calculateur-tjm-freelance'))
            ->assertOk()
            ->assertSee('Objectif annuel')
            ->assertSee('TJM estimé');

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('home'), false)
            ->assertSee(route('free-tools.index'), false)
            ->assertSee(route('free-tools.show', 'calculateur-tjm-freelance'), false);
    }

    public function test_indexnow_key_file_is_served_only_for_the_configured_key(): void
    {
        Setting::put('indexnow_key', 'indexnowtestkey123', true);

        $this->get(route('indexnow.key', ['key' => 'indexnowtestkey123']))
            ->assertOk()
            ->assertSee('indexnowtestkey123', false);

        $this->get(route('indexnow.key', ['key' => 'wrongkey123']))
            ->assertNotFound();
    }

    public function test_published_article_can_be_submitted_to_indexnow_and_logged(): void
    {
        Http::fake([
            'https://api.indexnow.org/indexnow' => Http::response('', 202),
        ]);
        Setting::put('indexing_auto_enabled', '1');
        Setting::put('indexnow_enabled', '1');
        Setting::put('indexnow_key', 'indexnowtestkey456', true);

        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-indexnow',
            'website_url' => 'https://www.indy.fr',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Guide facture freelance',
            'slug' => 'guide-facture-freelance-indexnow',
            'type' => 'informational',
            'status' => 'published',
            'meta_description' => 'Comprendre la facturation freelance.',
            'body' => '## Guide',
            'published_at' => now(),
        ]);

        app(SearchEngineIndexingService::class)->submitArticle($article);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.indexnow.org/indexnow'
            && $request['key'] === 'indexnowtestkey456'
            && $request['urlList'] === [$article->public_url]);
        $this->assertSame(1, SearchIndexingSubmission::query()->where('provider', 'indexnow')->where('status', 'submitted')->count());
    }

    public function test_semrush_seed_engine_imports_facturation_keywords_with_affiliate_tags(): void
    {
        Setting::put('semrush_api_key', 'semrush-test-key', true);
        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-semrush-engine',
            'website_url' => 'https://www.indy.fr',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        $seed = KeywordSeed::query()->create([
            'seo_project_id' => $project->id,
            'seed' => 'logiciel devis facture',
            'affiliate_cluster' => 'facturation',
            'intent_type' => 'solution',
            'indy_fit' => 5,
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.semrush.com/apis/v4/keywords/v1/metrics*' => Http::response([
                'meta' => [
                    'country' => 'FR',
                    'keyword' => 'logiciel devis facture',
                    'success' => true,
                ],
                'data' => [
                    'competitive_density' => 66,
                    'cpc' => '602',
                    'intents' => ['COMMERCIAL', 'INFORMATIONAL'],
                    'keyword_difficulty' => 54,
                    'number_of_results' => '6100000',
                    'search_volume' => '2400',
                    'serp_features' => ['FEATURED_SNIPPET', 'PEOPLE_ALSO_ASK'],
                    'trends' => [72, 74, 80],
                ],
            ]),
        ]);

        $this->artisan('semrush:expand-seeds', [
            'project' => $project->slug,
            '--cluster' => 'facturation',
            '--limit' => 1,
        ])->assertExitCode(0);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Apikey semrush-test-key')
            && str_contains(urldecode($request->url()), 'keyword=logiciel devis facture')
            && str_contains($request->url(), 'country=FR'));

        $keyword = $project->keywords()->where('keyword', 'logiciel devis facture')->first();
        $this->assertNotNull($keyword);
        $this->assertSame(2400, $keyword->search_volume);
        $this->assertSame(54.0, $keyword->keyword_difficulty);
        $this->assertSame(6.02, $keyword->cpc);
        $this->assertSame('Commerciale, Informationnelle', $keyword->intent);
        $this->assertSame('solution', $keyword->intent_type);
        $this->assertSame('facturation', $keyword->affiliate_cluster);
        $this->assertGreaterThan(70, $keyword->affiliate_priority);

        $seed->refresh();
        $this->assertNotNull($seed->last_expanded_at);
        $this->assertSame(1, $seed->fetched_keywords_count);
        $this->assertNull($seed->last_error);
    }

    public function test_semrush_seed_engine_dry_run_does_not_call_api_or_update_seed(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-semrush-dry-run',
            'website_url' => 'https://www.indy.fr',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        $seed = KeywordSeed::query()->create([
            'seo_project_id' => $project->id,
            'seed' => 'facture freelance',
            'affiliate_cluster' => 'facturation',
            'intent_type' => 'information',
            'indy_fit' => 5,
            'is_active' => true,
        ]);

        Http::fake();

        $this->artisan('semrush:expand-seeds', [
            'project' => $project->slug,
            '--cluster' => 'facturation',
            '--limit' => 1,
            '--dry-run' => true,
        ])->assertExitCode(0);

        Http::assertNothingSent();
        $seed->refresh();
        $this->assertNull($seed->last_expanded_at);
        $this->assertSame(0, $seed->fetched_keywords_count);
        $this->assertSame(0, $project->keywords()->count());
    }

    public function test_settings_can_store_semrush_api_key(): void
    {
        Livewire::test(Settings::class)
            ->set('semrushApiKey', 'semrush-test-key')
            ->set('model', 'gemini-2.5-flash-lite')
            ->call('save')
            ->assertSet('hasSavedSemrushKey', true);

        $this->assertSame('semrush-test-key', Setting::value('semrush_api_key'));
    }

    public function test_content_factory_button_expands_facturation_seed_from_semrush(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-factory-button',
            'website_url' => 'https://www.indy.fr',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        KeywordSeed::query()->create([
            'seo_project_id' => $project->id,
            'seed' => 'logiciel devis facture',
            'affiliate_cluster' => 'facturation',
            'intent_type' => 'solution',
            'indy_fit' => 5,
            'is_active' => true,
        ]);
        Setting::put('semrush_api_key', 'semrush-test-key', true);

        Http::fake([
            'https://api.semrush.com/apis/v4/keywords/v1/metrics*' => Http::response([
                'meta' => ['country' => 'FR', 'keyword' => 'logiciel devis facture', 'success' => true],
                'data' => [
                    'competitive_density' => 66,
                    'cpc' => '602',
                    'intents' => ['COMMERCIAL'],
                    'keyword_difficulty' => 54,
                    'search_volume' => '2400',
                ],
            ]),
        ]);

        $this->actingAs($admin);
        Livewire::test(ContentSchedulerDashboard::class)
            ->set('projectId', $project->id)
            ->call('expandFacturationSeeds')
            ->assertSet('error', '')
            ->assertSee('Facturation');

        $this->assertDatabaseHas('keywords', [
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel devis facture',
            'intent_type' => 'solution',
            'affiliate_cluster' => 'facturation',
        ]);
    }
}
