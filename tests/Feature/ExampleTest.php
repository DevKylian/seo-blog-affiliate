<?php

namespace Tests\Feature;

use App\Livewire\ArticleEditor;
use App\Livewire\Articles as ArticlesTable;
use App\Livewire\Auth\Login;
use App\Livewire\Automation;
use App\Livewire\Dashboard;
use App\Livewire\Keywords as KeywordsTable;
use App\Livewire\Projects as ProjectsTable;
use App\Livewire\Research;
use App\Exceptions\PlannedContentRejectedException;
use App\Models\Article;
use App\Models\ContentRun;
use App\Models\EditorialIdea;
use App\Models\EditorialPlan;
use App\Models\EvidenceChunk;
use App\Models\Keyword;
use App\Models\Plan;
use App\Models\SeoProject;
use App\Models\Setting;
use App\Models\SourcePage;
use App\Models\User;
use App\Services\ArticleRegenerationWorkerLauncher;
use App\Services\CompetitorCatalog;
use App\Services\EditorialConsolidationService;
use App\Services\EditorialDuplicateDetector;
use App\Services\EditorialPlanBuilder;
use App\Services\GeminiContentGenerator;
use App\Services\GeneratedContentSanitizer;
use App\Services\InternalLinkService;
use App\Services\ProductKeywordMatcher;
use App\Services\Scraping\BrowserHtmlFetcher;
use App\Services\Scraping\StaticSiteScraper;
use App\Services\SemrushCsvImporter;
use App\Services\SeoContentStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_generation_uses_flash_only_after_three_flash_lite_capacity_failures(): void
    {
        Setting::put('gemini_model', 'gemini-2.5-flash-lite');
        $generator = app(GeminiContentGenerator::class);
        $method = new \ReflectionMethod(GeminiContentGenerator::class, 'contentModelForAttempt');

        $this->assertSame('gemini-2.5-flash-lite', $method->invoke($generator, 0));
        $this->assertSame('gemini-2.5-flash-lite', $method->invoke($generator, 2));
        $this->assertSame('gemini-2.5-flash', $method->invoke($generator, 3));

        Setting::put('gemini_model', 'gemini-2.5-flash');
        $this->assertSame('gemini-2.5-flash', $method->invoke($generator, 0));
    }

    public function test_exact_primary_keyword_remains_a_strong_duplicate_signal_when_the_ai_renames_the_topic(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'Indy', 'slug' => 'indy-dedupe', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);
        Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Facture acquittée : valider un paiement',
            'slug' => 'facture-acquittee-valider-paiement',
            'type' => 'informational',
            'status' => 'draft',
            'primary_keyword' => 'facture acquittée',
            'search_intent' => 'informationnelle',
            'entity_key' => 'indy-crm',
            'topic_key' => 'crm-facturation-acquittee',
            'content_angle' => 'valider-un-paiement',
            'editorial_audience' => 'pme',
            'topic_fingerprint' => 'indy-crm|crm-facturation-acquittee|informational|pme|valider-un-paiement',
            'body' => 'Une facture acquittée confirme le règlement reçu.',
        ]);
        $candidate = [
            'entity' => 'indy',
            'topic' => 'gestion-financiere',
            'intent' => 'informationnelle',
            'angle' => 'cloturer-les-transactions',
            'audience' => 'entreprises',
            'primary_keyword' => 'facture acquittée',
            'unique_promise' => 'Confirmer le règlement et archiver la preuve de paiement.',
            'outline' => ['Définition', 'Valeur juridique', 'Procédure', 'Archivage', 'FAQ'],
        ];

        $analysis = app(EditorialDuplicateDetector::class)->analyzeBlueprint($project, $candidate);

        $this->assertNotSame('allow', $analysis['decision']);
        $this->assertGreaterThanOrEqual(72, $analysis['score']);
    }

    public function test_multi_product_briefs_are_rejected_before_writing_when_the_title_is_mono_product_or_generic(): void
    {
        $project = new SeoProject(['name' => 'Indy']);
        $builder = app(EditorialPlanBuilder::class);
        $method = new \ReflectionMethod(EditorialPlanBuilder::class, 'multiProductFormatIssue');

        $genericAlternatives = $method->invoke($builder, $project, [
            'title' => 'Les 5 meilleures alternatives aux logiciels de facturation traditionnels',
            'content_type' => 'alternatives',
        ]);
        $monoBestTools = $method->invoke($builder, $project, [
            'title' => 'Indy : le logiciel idéal pour les indépendants',
            'content_type' => 'best_tools',
        ]);
        $validComparison = $method->invoke($builder, $project, [
            'title' => 'Indy vs Odoo : quel logiciel choisir ?',
            'content_type' => 'comparison',
        ]);

        $this->assertNotNull($genericAlternatives);
        $this->assertNotNull($monoBestTools);
        $this->assertNull($validComparison);
    }

    public function test_editorial_planning_rejects_a_second_angle_on_the_same_semrush_keyword_before_generation(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-plan-keyword-dedupe',
            'website_url' => 'https://example.com',
            'country' => 'FR',
            'currency' => 'EUR',
            'positioning' => 'Logiciel de facturation, devis et comptabilite pour independants.',
        ]);
        $source = SourcePage::query()->create([
            'seo_project_id' => $project->id,
            'url' => 'https://example.com/source',
            'type' => 'features',
            'title' => 'Facturation et devis',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        EvidenceChunk::query()->create([
            'source_page_id' => $source->id,
            'category' => 'feature',
            'value' => 'Devis, factures, relances et suivi de tresorerie.',
            'source_excerpt' => 'Indy documente la creation de devis, factures et relances de paiement.',
            'confidence_score' => .95,
            'verified_at' => now(),
        ]);
        $keyword = Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel devis facture',
            'intent' => 'Informationnelle',
            'opportunity_score' => 80,
        ]);
        $firstIdea = new EditorialIdea([
            'keyword_id' => $keyword->id,
            'primary_keyword' => $keyword->keyword,
            'title' => 'Logiciel Devis Facture : Creez des Documents Professionnels',
            'entity_key' => 'indy',
            'topic_key' => 'crm-facturation',
            'intent' => 'informational',
            'angle' => 'creation-documents-professionnels',
            'audience' => 'freelances-tpe',
            'problem' => 'Creer des documents commerciaux conformes.',
            'expected_outcome' => 'produire des devis et factures fiables',
            'funnel_stage' => 'consideration',
            'unique_promise' => 'Creer des devis et factures professionnels sans multiplier les outils.',
            'excluded_topics' => [],
            'outline' => ['Contexte', 'Devis', 'Factures', 'Relances', 'FAQ'],
            'fingerprint' => 'indy|crm-facturation|informational|creation-documents-professionnels|freelances-tpe|produire-des-devis-et-factures-fiables',
            'content_type' => 'informational',
        ]);
        $blueprint = [
            'title' => 'Logiciel Devis Facture : L outil indispensable',
            'entity' => 'indy',
            'topic' => 'crm-facturation',
            'intent' => 'informational',
            'angle' => 'cycle-commercial',
            'audience' => 'freelances-tpe',
            'problem' => 'Fluidifier le cycle commercial.',
            'expected_outcome' => 'gerer le cycle commercial complet',
            'funnel_stage' => 'consideration',
            'primary_keyword' => $keyword->keyword,
            'unique_promise' => 'Comprendre comment un outil devis facture fluidifie le cycle commercial sans cannibaliser le guide principal.',
            'excluded_topics' => [],
            'outline' => ['Diagnostic', 'Devis', 'Facturation', 'Relances', 'FAQ'],
            'fingerprint' => 'indy|crm-facturation|informational|cycle-commercial|freelances-tpe|gerer-le-cycle-commercial-complet',
            'content_type' => 'informational',
        ];
        $plan = EditorialPlan::query()->create(['seo_project_id' => $project->id, 'name' => 'Plan Indy Test', 'requested_count' => 2]);
        $method = new \ReflectionMethod(EditorialPlanBuilder::class, 'validate');

        $decision = $method->invoke(app(EditorialPlanBuilder::class), $plan, $blueprint, collect([$firstIdea]), $keyword);

        $this->assertFalse($decision['accepted']);
        $this->assertSame('duplicate', $decision['category']);
        $this->assertSame(100.0, $decision['similarity']);
        $this->assertStringContainsString('Mot-cle deja retenu', $decision['reason']);
    }

    public function test_synthetic_competitors_are_rejected_before_editorial_planning_accepts_them(): void
    {
        $project = new SeoProject([
            'name' => 'Indy',
            'competitors' => ['Pennylane', 'Abby', 'Freebe', 'Henrri'],
        ]);
        $builder = app(EditorialPlanBuilder::class);
        $method = new \ReflectionMethod(EditorialPlanBuilder::class, 'competitorIssue');

        $issue = $method->invoke($builder, $project, [
            'title' => 'InvoiceFlow Max : Tarifs et Fonctionnalites Expliques',
            'primary_keyword' => 'logiciel facturation invoiceflow max tarif',
            'content_type' => 'pricing',
            'entity' => 'invoiceflow-max',
            'topic' => 'tarifs-facturation',
            'problem' => 'Comparer un logiciel de facturation invente.',
            'expected_outcome' => 'Eviter une recommandation fondee sur une marque fictive.',
            'unique_promise' => 'Verifier les vrais concurrents avant de produire un article de comparaison.',
            'excluded_topics' => [],
            'outline' => ['Contexte', 'Tarifs', 'Fonctionnalites', 'Limites', 'FAQ'],
        ]);

        $this->assertStringContainsString('Concurrent inconnu', $issue);
    }

    public function test_configured_real_competitors_allow_comparison_briefs(): void
    {
        $project = new SeoProject([
            'name' => 'Indy',
            'competitors' => ['Pennylane', 'Abby', 'Freebe', 'Henrri'],
        ]);
        $builder = app(EditorialPlanBuilder::class);
        $method = new \ReflectionMethod(EditorialPlanBuilder::class, 'competitorIssue');

        $issue = $method->invoke($builder, $project, [
            'title' => 'Indy vs Pennylane : quel logiciel choisir ?',
            'primary_keyword' => 'indy vs pennylane',
            'content_type' => 'comparison',
            'entity' => 'indy',
            'topic' => 'comparatif-facturation',
            'problem' => 'Comparer deux solutions reelles du marche.',
            'expected_outcome' => 'Identifier le meilleur choix selon le profil.',
            'unique_promise' => 'Comparer Indy et Pennylane sur des criteres concrets et sourcables.',
            'excluded_topics' => [],
            'outline' => ['Profils adaptes', 'Fonctionnalites Indy', 'Fonctionnalites Pennylane', 'Limites', 'FAQ'],
        ]);

        $this->assertNull($issue);
    }

    public function test_competitor_catalog_falls_back_to_real_billing_competitors_for_indy(): void
    {
        $project = new SeoProject(['name' => 'Indy']);
        $competitors = app(CompetitorCatalog::class)->competitorsFor($project);

        $this->assertContains('Pennylane', $competitors);
        $this->assertContains('Abby', $competitors);
        $this->assertNotContains('InvoiceFlow Max', $competitors);
    }

    public function test_compte_pro_generic_phrase_is_not_rejected_as_a_fake_competitor(): void
    {
        $project = new SeoProject([
            'name' => 'Indy',
            'competitors' => ['Pennylane', 'Abby', 'Freebe', 'Henrri'],
        ]);
        $catalog = app(CompetitorCatalog::class);

        $this->assertSame([], $catalog->unknownCompetitorMentions($project, 'Ouvrir un Compte Pro et suivre ses factures.'));
        $this->assertSame([], $catalog->unknownCompetitorMentions($project, 'Comprendre Chorus Pro et la facturation électronique.'));
        $this->assertSame([], $catalog->unknownCompetitorMentions($project, 'Ensuite, comparez les limites de facturation.'));
        $this->assertSame(['InvoiceFlow Max'], $catalog->unknownCompetitorMentions($project, 'Comparer Indy avec InvoiceFlow Max.'));
        $this->assertSame(['Plomberie Pro'], $catalog->unknownCompetitorMentions($project, 'Cas pratique : Plomberie Pro facture ses chantiers.'));
        $this->assertStringContainsString('jamais "Plomberie Pro"', $catalog->promptDirective($project));
    }

    public function test_accounting_competitors_are_detected_during_multi_product_finalization(): void
    {
        $generator = app(GeminiContentGenerator::class);
        $method = new \ReflectionMethod(GeminiContentGenerator::class, 'extractComparedProducts');
        $project = new SeoProject(['name' => 'Indy']);
        $products = $method->invoke(
            $generator,
            $project,
            'Indy vs Odoo : quel logiciel choisir ?',
            '| Solution | Limites |\n|---|---|\n| Pennylane | Coût |\n| Shine | Fonctions bancaires |',
        );

        $this->assertContains('Indy', $products);
        $this->assertContains('Odoo', $products);
        $this->assertContains('Pennylane', $products);
        $this->assertContains('Shine', $products);
    }

    public function test_explicit_competitor_config_limits_compared_products(): void
    {
        $generator = app(GeminiContentGenerator::class);
        $method = new \ReflectionMethod(GeminiContentGenerator::class, 'extractComparedProducts');
        $project = new SeoProject([
            'name' => 'Indy',
            'competitors' => ['Abby', 'Freebe', 'Pennylane'],
        ]);

        $products = $method->invoke(
            $generator,
            $project,
            'Meilleur Logiciel de Facturation : Comparatif Indy vs Abby vs Freebe en 2026',
            '| Solution | Limites |\n|---|---|\n| Zoho CRM | Hors sujet |\n| Facture.net | Hors config |\n| Freebe | Freemium |',
        );

        $this->assertContains('Indy', $products);
        $this->assertContains('Abby', $products);
        $this->assertContains('Freebe', $products);
        $this->assertNotContains('Zoho CRM', $products);
        $this->assertNotContains('Facture.net', $products);
    }

    public function test_stale_abby_pricing_claims_are_rejected_before_publication(): void
    {
        $generator = app(GeminiContentGenerator::class);
        $method = new \ReflectionMethod(GeminiContentGenerator::class, 'assertStrategicFit');
        $project = new SeoProject([
            'name' => 'Indy',
            'competitors' => ['Abby', 'Freebe', 'Pennylane'],
        ]);
        $data = [
            'title' => 'Indy vs Abby vs Freebe',
            'brief_title' => 'Indy vs Abby vs Freebe',
            'body' => "Abby propose une offre Decouverte limitee a 3 factures ou devis par mois.",
            'product_keyword_fit' => true,
            'product_keyword_fit_reason' => 'ok',
            'compared_products' => ['Indy', 'Abby', 'Freebe'],
        ];

        $this->expectException(PlannedContentRejectedException::class);
        $this->expectExceptionMessage('Information tarifaire obsolete');

        $method->invokeArgs($generator, [&$data, 'comparison', $project, null]);
    }

    public function test_generation_resolves_slug_collisions_with_descriptive_non_numeric_variants(): void
    {
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-slug-collision', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Logiciel devis facture',
            'slug' => 'logiciel-devis-facture',
            'body' => 'Contenu existant',
            'status' => 'review',
        ]);
        $blueprint = [
            'entity' => 'indy',
            'topic' => 'logiciel-devis-facture',
            'intent' => 'commercial',
            'audience' => 'pme',
            'angle' => 'facturation-gratuite',
            'funnel_stage' => 'consideration',
            'primary_keyword' => 'logiciel devis facture gratuit',
            'unique_promise' => 'Comparer les options de facturation gratuites sans doublonner le guide principal.',
            'problem' => 'Choisir une solution gratuite sans confusion.',
            'expected_outcome' => 'identifier les limites gratuites',
            'excluded_topics' => [],
            'outline' => [],
            'fingerprint' => 'indy|logiciel-devis-facture|commercial|facturation-gratuite|pme',
        ];
        $method = new \ReflectionMethod(GeminiContentGenerator::class, 'availableSlug');
        $method->setAccessible(true);

        $slug = $method->invoke(app(GeminiContentGenerator::class), $project, $blueprint, ['article' => null, 'decision' => 'allow', 'score' => 0], 'Logiciel devis facture');

        $this->assertSame('logiciel-devis-facture-facturation-gratuite', $slug);
        $this->assertDoesNotMatchRegularExpression('/-\d{1,2}$/', $slug);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_an_admin_can_log_in_and_see_the_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_admin' => true,
        ]);

        Livewire::test(Login::class)
            ->set('email', $admin->email)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin);
        Livewire::test(Dashboard::class)
            ->assertSee('SEO Affiliate Content OS');
    }

    public function test_the_public_blog_renders_dynamic_pricing_blocks(): void
    {
        $project = SeoProject::query()->create(['name' => 'Tool X', 'slug' => 'tool-x', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        Plan::query()->create([
            'seo_project_id' => $project->id,
            'name' => 'Pro',
            'monthly_price' => 29,
            'currency' => 'EUR',
            'features' => ['Toutes les fonctionnalités Essentiel', 'Relances automatiques', 'Signature électronique'],
            'price_variants' => [['price' => 'Dès 29 € / mois']],
            'raw_price' => "Offre : Pro\nPrix : Dès 29 € / mois\nVariante / contexte : Tarif variable selon le profil",
            'is_active' => true,
            'verified_at' => now(),
        ]);
        Plan::query()->create([
            'seo_project_id' => $project->id,
            'name' => 'Premium',
            'monthly_price' => 15,
            'monthly_price_max' => 49,
            'currency' => 'EUR',
            'features' => ['Déclarations fiscales automatisées'],
            'raw_price' => "Offre : Premium\nVariante / contexte : selon le profil",
            'is_active' => true,
            'verified_at' => now(),
        ]);
        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'tool_review',
            'title' => 'Avis Tool X',
            'slug' => 'avis-tool-x',
            'status' => 'published',
            'body' => str_repeat('Contenu vérifié. ', 10),
            'content_blocks' => [['type' => 'markdown', 'content' => '## Test'], ['type' => 'pricing_table', 'project_id' => $project->id]],
            'published_at' => now(),
        ]);

        $this->get(route('blog.show', $article->slug))
            ->assertOk()
            ->assertSee('Tarifs Tool X')
            ->assertSee('Dès 29 € / mois')
            ->assertSee('De 15 € à 49 € / mois')
            ->assertSee('Relances automatiques · Signature électronique')
            ->assertDontSee('Offre : Pro')
            ->assertDontSee('Variante / contexte');
    }

    public function test_public_comparison_pricing_block_renders_affiliate_and_competitor_prices(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-comparison-pricing',
            'website_url' => 'https://example.com',
            'pricing_url' => 'https://example.com/indy-pricing',
            'country' => 'FR',
            'currency' => 'EUR',
            'competitors' => ['Abby', 'Freebe', 'Pennylane'],
            'competitor_pricing_urls' => [
                'Abby' => 'https://example.com/abby-pricing',
                'Freebe' => 'https://example.com/freebe-pricing',
                'Pennylane' => 'https://example.com/pennylane-pricing',
            ],
        ]);
        SourcePage::query()->create([
            'seo_project_id' => $project->id,
            'url' => 'https://example.com/abby-pricing',
            'type' => 'pricing',
            'competitor_name' => 'Abby',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        SourcePage::query()->create([
            'seo_project_id' => $project->id,
            'url' => 'https://example.com/freebe-pricing',
            'type' => 'pricing',
            'competitor_name' => 'Freebe',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        foreach ([
            [null, 'Indy Facturation', 0, ['Factures illimitées', 'Suivi des paiements']],
            ['Abby', 'Basique', 0, ['Certaines fonctions avancées réservées aux plans payants']],
            ['Freebe', 'Solo', 11, ['Fonctions comptables selon abonnement']],
        ] as [$competitorName, $name, $price, $features]) {
            Plan::query()->create([
                'seo_project_id' => $project->id,
                'competitor_name' => $competitorName,
                'name' => $name,
                'monthly_price' => $price,
                'currency' => 'EUR',
                'features' => $features,
                'is_active' => true,
                'verified_at' => now(),
            ]);
        }
        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'comparison',
            'title' => 'Indy vs Abby vs Freebe',
            'slug' => 'indy-vs-abby-vs-freebe',
            'status' => 'published',
            'body' => 'Comparatif officiel.',
            'content_blocks' => [['type' => 'markdown', 'content' => '## Comparatif'], ['type' => 'pricing_table', 'project_id' => $project->id]],
            'published_at' => now(),
        ]);

        $this->get(route('comparisons.show', $article->slug))
            ->assertOk()
            ->assertSee('Tarifs comparés')
            ->assertSee("Prix d'entrée", false)
            ->assertSee('Offres relevées')
            ->assertSee('Gratuit / essai')
            ->assertSee('Indy')
            ->assertSee('Abby')
            ->assertSee('Freebe')
            ->assertSee('Pennylane')
            ->assertSee('Abby source')
            ->assertSee('Freebe source')
            ->assertSee('Prix non extrait')
            ->assertSee('Certaines fonctions avancées réservées aux plans payants')
            ->assertDontSee('comparison-pricing-grid', false);
    }

    public function test_public_articles_render_contextual_internal_links_inside_the_content(): void
    {
        $project = SeoProject::query()->create(['name' => 'Tool X', 'slug' => 'tool-x-links', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $source = Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'informational',
            'title' => 'Structurer son budget logiciel',
            'slug' => 'structurer-budget-logiciel',
            'status' => 'published',
            'primary_keyword' => 'budget logiciel',
            'topic_key' => 'budget-logiciel',
            'body' => "## Anticiper les dépenses\n\nLes tarifs Tool X doivent être comparés au nombre d’utilisateurs, au coût de déploiement et aux fonctions réellement utilisées par l’équipe afin d’éviter une décision fondée uniquement sur le prix d’appel.\n\n## Réduire les tâches manuelles\n\nAutomatiser la facturation permet de réduire les ressaisies, mais exige de définir les statuts de paiement et les responsabilités de validation avant le déploiement.\n\n## Examiner les options\n\nLes alternatives à Tool X doivent être évaluées selon le volume de documents, les intégrations comptables et le niveau d’accompagnement attendu par l’entreprise.",
            'published_at' => now(),
        ]);
        $target = Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'pricing',
            'title' => 'Tous les prix et abonnements de Tool X',
            'slug' => 'tarifs-tool-x',
            'status' => 'published',
            'primary_keyword' => 'tarifs Tool X',
            'topic_key' => 'tarifs-abonnements',
            'content_angle' => 'cout-total-possession',
            'body' => "## Comparer les offres\n\nAnalyse détaillée des abonnements.",
            'published_at' => now(),
        ]);
        Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'informational',
            'title' => 'Automatisation des opérations de facturation',
            'slug' => 'automatiser-facturation',
            'status' => 'published',
            'primary_keyword' => 'automatiser la facturation',
            'topic_key' => 'automatisation-facturation',
            'content_angle' => 'workflow-paiement',
            'body' => "## Construire le workflow\n\nMéthode de configuration.",
            'published_at' => now(),
        ]);
        Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'alternatives',
            'title' => 'Les logiciels concurrents de Tool X',
            'slug' => 'alternatives-tool-x',
            'status' => 'published',
            'primary_keyword' => 'logiciel',
            'topic_key' => 'alternatives-logiciels',
            'content_angle' => 'options-selon-usage',
            'body' => "## Comparer les options\n\nAnalyse des compromis.",
            'published_at' => now(),
        ]);

        $this->assertSame(3, app(InternalLinkService::class)->refresh($source));
        $this->assertDatabaseHas('internal_links', [
            'source_article_id' => $source->id,
            'anchor_text' => 'les alternatives à Tool X',
        ]);

        $response = $this->get(route('blog.show', $source->slug))
            ->assertOk()
            ->assertSee('class="contextual-internal-link"', false)
            ->assertSee('tarifs Tool X')
            ->assertSee($target->public_url, false)
            ->assertDontSee('À lire aussi')
            ->assertDontSee($target->title);
        $this->assertSame(3, substr_count($response->getContent(), 'class="contextual-internal-link"'));
    }

    public function test_gemini_receives_three_natural_internal_link_targets_before_writing(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'Tool X', 'slug' => 'tool-x-prompt-links', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);
        $targets = collect([
            ['pricing', 'Tarifs Tool X', 'tarifs-tool-x-prompt', 'tarifs Tool X', 'tarifs-abonnements', 'cout-total-possession'],
            ['informational', 'Automatiser la facturation', 'automatiser-facturation-prompt', 'automatiser la facturation', 'automatisation-facturation', 'workflow-paiement'],
            ['alternatives', 'Alternatives à Tool X', 'alternatives-tool-x-prompt', 'alternatives à Tool X', 'alternatives-logiciels', 'options-selon-usage'],
        ])->map(fn (array $item) => Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => $item[0],
            'title' => $item[1],
            'slug' => $item[2],
            'status' => 'published',
            'entity_key' => 'tool-x',
            'primary_keyword' => $item[3],
            'topic_key' => $item[4],
            'content_angle' => $item[5],
            'unique_promise' => 'Une promesse complémentaire et spécifique.',
            'body' => "## Analyse\n\nContenu publié complémentaire.",
            'published_at' => now(),
        ]));
        $blueprint = [
            'entity' => 'tool-x',
            'topic' => 'pilotage-activite',
            'intent' => 'informational',
            'angle' => 'tableau-bord-dirigeant',
            'audience' => 'dirigeants-pme',
            'primary_keyword' => 'piloter son activité',
            'unique_promise' => 'Construire un tableau de bord opérationnel.',
            'outline' => ['Choisir les indicateurs', 'Construire le tableau de bord', 'Analyser les écarts'],
        ];

        $method = new \ReflectionMethod(GeminiContentGenerator::class, 'internalLinkDirective');
        $directive = $method->invoke(app(GeminiContentGenerator::class), $project, $blueprint, 'informational');

        $this->assertSame(3, substr_count($directive, 'URL exacte :'));
        $targets->each(fn (Article $target) => $this->assertStringContainsString($target->public_url, $directive));
        $this->assertStringContainsString('phrase qui apporte déjà une information utile', $directive);
        $this->assertStringContainsString('Utilise le titre fourni comme texte d’ancrage Markdown', $directive);
        $this->assertStringContainsString('Rôle : BoFu / conversion', $directive);
    }

    public function test_internal_link_strategy_reserves_conversion_contextual_and_pillar_slots(): void
    {
        $project = SeoProject::query()->create(['name' => 'Tool X', 'slug' => 'tool-x-link-strategy', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $conversion = Article::query()->create([
            'seo_project_id' => $project->id, 'type' => 'pricing', 'title' => 'Tarifs et abonnements Tool X',
            'slug' => 'strategie-tarifs', 'status' => 'published', 'topic_key' => 'tarifs-abonnements',
            'content_angle' => 'cout-total-possession', 'body' => '## Prix', 'published_at' => now(),
        ]);
        $contextual = Article::query()->create([
            'seo_project_id' => $project->id, 'type' => 'informational', 'title' => 'Automatiser la facturation des PME',
            'slug' => 'strategie-facturation', 'status' => 'published', 'primary_keyword' => 'automatiser la facturation',
            'topic_key' => 'automatisation-facturation', 'content_angle' => 'workflow-paiement',
            'body' => '## Automatisation', 'published_at' => now(),
        ]);
        $pillar = Article::query()->create([
            'seo_project_id' => $project->id, 'type' => 'informational', 'title' => 'Guide complet de la migration des données',
            'slug' => 'strategie-guide-migration', 'status' => 'published', 'primary_keyword' => 'migration des données',
            'topic_key' => 'migration-donnees', 'content_angle' => 'guide-complet',
            'body' => '## Préparer la migration', 'published_at' => now(),
        ]);

        $suggestions = app(InternalLinkService::class)->suggestionsForBlueprint($project, [
            'entity' => 'tool-x',
            'topic' => 'facturation-pme',
            'intent' => 'informational',
            'angle' => 'pilotage-encaissements',
            'audience' => 'dirigeants-pme',
            'primary_keyword' => 'facturation pme',
            'unique_promise' => 'Piloter les factures et les encaissements.',
            'outline' => ['Créer les factures', 'Suivre les encaissements'],
        ], 3, 'informational');

        $this->assertSame(['conversion', 'contextual', 'pillar'], $suggestions->pluck('role')->all());
        $this->assertTrue($suggestions[0]['article']->is($conversion));
        $this->assertTrue($suggestions[1]['article']->is($contextual));
        $this->assertTrue($suggestions[2]['article']->is($pillar));
    }

    public function test_a_link_written_naturally_by_gemini_is_not_duplicated_by_the_public_renderer(): void
    {
        $project = SeoProject::query()->create(['name' => 'Tool X', 'slug' => 'tool-x-no-double-link', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $target = Article::query()->create([
            'seo_project_id' => $project->id, 'type' => 'pricing', 'title' => 'Tarifs Tool X',
            'slug' => 'tarifs-tool-x-natural', 'status' => 'published', 'body' => '## Tarifs', 'published_at' => now(),
        ]);
        $source = Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'informational',
            'title' => 'Piloter le coût logiciel',
            'slug' => 'piloter-cout-logiciel',
            'status' => 'published',
            'body' => "## Estimer le budget\n\nAvant de choisir une formule, consultez [le détail des abonnements]({$target->public_url}) afin de rapprocher le prix du nombre d’utilisateurs.",
            'published_at' => now(),
        ]);
        $source->internalLinks()->create([
            'target_article_id' => $target->id,
            'anchor_text' => 'les tarifs de Tool X',
            'automatic' => true,
        ]);

        $response = $this->get(route('blog.show', $source->slug))->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), $target->public_url));
        $response->assertSee('class="contextual-internal-link"', false);
        $response->assertSee('le détail des abonnements');
    }

    public function test_publishing_from_content_studio_refreshes_the_project_internal_links(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'Tool X', 'slug' => 'tool-x-studio-links', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        foreach ([
            ['pricing', 'Tarifs Tool X', 'studio-tarifs', 'tarifs', 'cout'],
            ['alternatives', 'Alternatives Tool X', 'studio-alternatives', 'alternatives', 'choix'],
            ['informational', 'Automatiser Tool X', 'studio-automatiser', 'automatisation', 'workflow'],
        ] as $target) {
            Article::query()->create([
                'seo_project_id' => $project->id, 'type' => $target[0], 'title' => $target[1],
                'slug' => $target[2], 'status' => 'published', 'topic_key' => $target[3],
                'content_angle' => $target[4], 'body' => '## Contenu complémentaire', 'published_at' => now(),
            ]);
        }
        $article = Article::query()->create([
            'seo_project_id' => $project->id, 'type' => 'informational', 'title' => 'Guide Tool X pour PME',
            'slug' => 'guide-tool-x-pme', 'status' => 'review', 'topic_key' => 'pilotage-pme',
            'content_angle' => 'tableau-bord', 'body' => '## Piloter son activité',
        ]);

        $article->update(['status' => 'published']);

        $this->assertSame('published', $article->fresh()->status);
        $this->assertSame(3, $article->internalLinks()->count());
    }

    public function test_gemini_key_is_encrypted_at_rest(): void
    {
        Setting::put('gemini_api_key', 'secret-key-that-is-long-enough', true);

        $raw = DB::table('settings')->where('key', 'gemini_api_key')->value('value');
        $this->assertNotSame('secret-key-that-is-long-enough', $raw);
        $this->assertSame('secret-key-that-is-long-enough', Setting::value('gemini_api_key'));
    }

    public function test_semrush_csv_is_imported_and_scored(): void
    {
        $project = SeoProject::query()->create(['name' => 'Tool X', 'slug' => 'tool-x', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $path = tempnam(sys_get_temp_dir(), 'semrush');
        file_put_contents($path, "keyword,search_volume,keyword_difficulty,intent,cpc,country\navis Tool X,200,25,Commerciale,3.2,FR\n");

        $count = app(SemrushCsvImporter::class)->import($project, $path);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('keywords', ['keyword' => 'avis Tool X', 'cluster' => 'Avis']);
        $this->assertGreaterThan(0, $project->keywords()->first()->opportunity_score);
    }

    public function test_a_semrush_table_can_be_pasted_and_is_classified_by_content_strategy(): void
    {
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-pasted-keywords', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $table = implode("\n", [
            "Mot clé\tIntention\tVolume\tTendance\tKD %\tCPC (EUR)",
            "logiciel devis facture\tI\t2 400\t—\t54\t6,02",
            "logiciel devis facture mac\tC\t210\t—\t19\t4,46",
            "logiciel devis facture artisan\tI C\t210\t—\t65\t7,91",
        ]);

        $count = app(SemrushCsvImporter::class)->importText($project, $table);

        $this->assertSame(3, $count);
        $this->assertSame('pillar', $project->keywords()->where('keyword', 'logiciel devis facture')->first()->strategyTier());
        $this->assertSame('quick_win', $project->keywords()->where('keyword', 'logiciel devis facture mac')->first()->strategyTier());
        $artisan = $project->keywords()->where('keyword', 'logiciel devis facture artisan')->first();
        $this->assertSame('niche', $artisan->strategyTier());
        $this->assertSame('Informationnelle, Commerciale', $artisan->intent);
        $this->assertSame(7.91, $artisan->cpc);
    }

    public function test_a_poor_keyword_paste_does_not_erase_existing_semrush_metrics(): void
    {
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-preserve-kd', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel de facturation',
            'search_volume' => 2400,
            'keyword_difficulty' => 54,
            'intent' => 'Commerciale',
            'cpc' => 6.02,
        ]);

        app(SemrushCsvImporter::class)->importText($project, implode("\n", [
            "Keyword\tIntent",
            "logiciel de facturation\tC",
        ]));

        $keyword = $project->keywords()->where('keyword', 'logiciel de facturation')->first();

        $this->assertSame(2400, $keyword->search_volume);
        $this->assertSame(54.0, $keyword->keyword_difficulty);
        $this->assertSame(6.02, $keyword->cpc);
    }

    public function test_import_backfills_equivalent_unmeasured_keyword_variants(): void
    {
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-backfill-kd', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'facture devis logiciel gratuit',
            'intent' => 'Commerciale',
        ]);

        app(SemrushCsvImporter::class)->importText($project, implode("\n", [
            "Keyword\tIntent\tAvg. monthly searches\tCompetition (indexed value)\tTop of page bid (high range)",
            "logiciel devis facture gratuit\tC\t5000\t69\t11,62",
        ]));

        $keyword = $project->keywords()->where('keyword', 'facture devis logiciel gratuit')->first();

        $this->assertSame(5000, $keyword->search_volume);
        $this->assertSame(69.0, $keyword->keyword_difficulty);
        $this->assertSame(11.62, $keyword->cpc);
    }

    public function test_a_flattened_semrush_browser_copy_is_rebuilt_without_fake_kd_values_or_numeric_keywords(): void
    {
        $project = SeoProject::query()->create(['name' => 'Revolut', 'slug' => 'revolut-flat-keywords', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $clipboard = implode("\n", [
            'Mot clé', 'Intention', 'Volume', 'Tendance', 'KD %', 'CPC (EUR)', 'Con.', 'FS', 'Résultats', 'Mise à jour', 'Sélectionnés:0',
            'banque en ligne', 'C', '49 500', '73', '4,97', '0,78', '63', '1 mois',
            'banque en ligne gratuite', 'I', '14 800', '51', '4,03', '0,80', '62', '3 semaines',
            'banque en ligne pro', 'C', '1 900', '42', '18,43', '0,90', '55', '4 semaines',
        ]);

        $count = app(SemrushCsvImporter::class)->importText($project, $clipboard);

        $this->assertSame(3, $count);
        $this->assertSame([
            'banque en ligne' => 73.0,
            'banque en ligne gratuite' => 51.0,
            'banque en ligne pro' => 42.0,
        ], $project->keywords()->orderBy('id')->get()->mapWithKeys(fn (Keyword $keyword) => [$keyword->keyword => $keyword->keyword_difficulty])->all());
        $this->assertDatabaseMissing('keywords', ['seo_project_id' => $project->id, 'keyword' => '0,78']);
        $this->assertDatabaseMissing('keywords', ['seo_project_id' => $project->id, 'keyword' => 'Intention']);
    }

    public function test_a_flattened_semrush_browser_copy_keeps_kd_after_a_zero_trend_column(): void
    {
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-flat-zero-trend', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $clipboard = implode("\n", [
            'Mot cle', 'Intention', 'Volume', 'Tendance', 'KD %', 'CPC (EUR)', 'Con.', 'FS', 'Resultats', 'Mise a jour', 'Selectionnes:0',
            'logiciel de facturation', 'C', '2 400', '0', '54', '6,02', '0,78', '63', '1 mois',
            'logiciel facture gratuit', 'I', '210', '+12%', '19%', '4,46', '0,50', '51', '3 semaines',
        ]);

        $count = app(SemrushCsvImporter::class)->importText($project, $clipboard);

        $this->assertSame(2, $count);
        $this->assertDatabaseHas('keywords', [
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel de facturation',
            'search_volume' => 2400,
            'keyword_difficulty' => 54,
            'cpc' => 6.02,
        ]);
        $this->assertDatabaseHas('keywords', [
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel facture gratuit',
            'keyword_difficulty' => 19,
            'cpc' => 4.46,
        ]);
    }

    public function test_a_flattened_semrush_browser_copy_without_selection_marker_starts_after_the_header(): void
    {
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-flat-no-selection-marker', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $clipboard = implode("\n", [
            'Mot cle', 'Intention', 'Volume', 'Tendance', 'KD %', 'CPC (EUR)',
            'logiciel de facturation', 'C', '2 400', '0', '54', '6,02',
            'logiciel devis facture', 'I C', '1 900', '0%', '42', '5,10',
        ]);

        $count = app(SemrushCsvImporter::class)->importText($project, $clipboard);

        $this->assertSame(2, $count);
        $this->assertDatabaseHas('keywords', [
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel de facturation',
            'keyword_difficulty' => 54,
        ]);
        $this->assertDatabaseHas('keywords', [
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel devis facture',
            'keyword_difficulty' => 42,
        ]);
    }

    public function test_semrush_overview_clipboard_imports_only_real_keyword_rows(): void
    {
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-semrush-overview', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $clipboard = implode("\n", [
            'All keywords:', '11,759', 'Total Volume:', '167,090', 'Average KD:', '37%',
            'Keyword', 'Intent', 'Volume', 'Trend', 'KD %', 'CPC (EUR)', 'Com.', 'SERP Features', 'Results', 'Updated', 'Selected:0',
            'logiciel de facturation', 'C', '4,400', '43', '3.50', '0.66', 'Sitelinks, Reviews, Image pack, Video, People also ask', '63', '4 weeks',
            'logiciel de facturation gratuit', 'I', 'C', '2,400', '56', '2.87', '0.77', 'Featured snippet, Reviews, Video, People also ask, Related searches', '62', '4 weeks',
            'quel logiciel facturation quickbill advanced choisir', 'I', '1,600', 'n/a', '0.00', '0.00', 'Video, Video carousel, People also ask, Related searches', '322', '1 month',
            'logiciel devis facture batiment', 'I', '1,300', '34', '7.91', '0.95', 'Sitelinks, Image pack, Video, Video carousel, People also ask, Related searches', '68', '1 month',
        ]);

        $count = app(SemrushCsvImporter::class)->importText($project, $clipboard);

        $this->assertSame(4, $count);
        $this->assertSame(4, $project->keywords()->count());
        $this->assertDatabaseHas('keywords', [
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel de facturation',
            'search_volume' => 4400,
            'keyword_difficulty' => 43,
            'cpc' => 3.5,
        ]);
        $this->assertDatabaseHas('keywords', [
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel de facturation gratuit',
            'intent' => 'Informationnelle, Commerciale',
            'keyword_difficulty' => 56,
        ]);
        $this->assertDatabaseHas('keywords', [
            'seo_project_id' => $project->id,
            'keyword' => 'quel logiciel facturation quickbill advanced choisir',
            'search_volume' => 1600,
            'keyword_difficulty' => 0,
        ]);
        $this->assertDatabaseMissing('keywords', ['seo_project_id' => $project->id, 'keyword' => 'Sitelinks, Reviews, Image pack, Video, People also ask']);
        $this->assertDatabaseMissing('keywords', ['seo_project_id' => $project->id, 'keyword' => 'Selected:0']);
    }

    public function test_editorial_ideas_keep_the_exact_semrush_keyword_id_and_difficulty(): void
    {
        $project = SeoProject::query()->create(['name' => 'Revolut', 'slug' => 'revolut-exact-kd', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $low = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'banque en ligne pro', 'keyword_difficulty' => 22]);
        $high = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'banque traditionnelle', 'keyword_difficulty' => 73]);
        $keywords = collect([$low, $high]);
        $builder = app(EditorialPlanBuilder::class);
        $method = new \ReflectionMethod($builder, 'sourceKeyword');

        $selected = $method->invoke($builder, $keywords, [
            'source_keyword_id' => $low->id,
            'primary_keyword' => $low->keyword,
        ], ['primary_keyword' => $low->keyword]);
        $mismatch = $method->invoke($builder, $keywords, [
            'source_keyword_id' => $high->id,
            'primary_keyword' => $low->keyword,
        ], ['primary_keyword' => $low->keyword]);

        $this->assertSame($low->id, $selected->id);
        $this->assertSame(22.0, $selected->keyword_difficulty);
        $this->assertSame($low->id, $mismatch->id, 'Un identifiant incohérent ne doit jamais attribuer le KD d’un autre mot-clé.');
    }

    public function test_foundation_plans_reserve_two_places_for_pillar_keywords_before_quick_wins(): void
    {
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-foundation-plan', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $pillarOne = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'logiciel devis facture', 'search_volume' => 2400, 'keyword_difficulty' => 54, 'opportunity_score' => 70]);
        $pillarTwo = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'logiciel pour devis facture', 'search_volume' => 1900, 'keyword_difficulty' => 56, 'opportunity_score' => 68]);
        $quickOne = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'logiciel devis facture mac', 'search_volume' => 210, 'keyword_difficulty' => 19, 'opportunity_score' => 95]);
        $quickTwo = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'logiciel devis facture paysagiste', 'search_volume' => 170, 'keyword_difficulty' => 13, 'opportunity_score' => 94]);
        $plan = EditorialPlan::query()->create(['seo_project_id' => $project->id, 'name' => 'Fondations', 'requested_count' => 3]);
        foreach ([[$quickOne, 95], [$quickTwo, 94], [$pillarOne, 70], [$pillarTwo, 68]] as $index => [$keyword, $score]) {
            EditorialIdea::query()->create([
                'editorial_plan_id' => $plan->id,
                'keyword_id' => $keyword->id,
                'title' => 'Idée '.$index,
                'primary_keyword' => $keyword->keyword,
                'entity_key' => 'indy',
                'topic_key' => 'topic-'.$index,
                'intent' => 'commercial',
                'angle' => 'angle-'.$index,
                'audience' => 'pme',
                'problem' => 'Problème '.$index,
                'expected_outcome' => 'Résultat '.$index,
                'funnel_stage' => 'consideration',
                'unique_promise' => 'Promesse '.$index,
                'excluded_topics' => [],
                'outline' => ['Section A', 'Section B'],
                'fingerprint' => 'fingerprint-'.$index,
                'content_type' => 'informational',
                'roadmap_level' => $keyword->strategyTier() === 'pillar' ? 'Level 1 - Pillar' : 'Level 3 - Long Tail',
                'status' => 'candidate',
                'seo_score' => $score,
            ]);
        }

        $method = new \ReflectionMethod(EditorialPlanBuilder::class, 'lockPlan');
        $method->invoke(app(EditorialPlanBuilder::class), $plan, $plan->ideas()->get());
        $accepted = $plan->ideas()->with('keyword')->where('status', 'accepted')->orderBy('position')->get();

        $this->assertSame(['pillar', 'pillar', 'quick_win'], $accepted->map(fn (EditorialIdea $idea) => $idea->keyword->strategyTier())->all());
    }

    public function test_newly_imported_keywords_are_analyzed_even_when_the_project_already_has_hundreds_of_stronger_keywords(): void
    {
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-new-keyword-priority', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $oldDate = now()->subDay();
        for ($index = 1; $index <= 501; $index++) {
            Keyword::query()->create([
                'seo_project_id' => $project->id,
                'keyword' => 'logiciel facturation Indy historique '.$index,
                'search_volume' => 500,
                'keyword_difficulty' => 45,
                'opportunity_score' => 95,
                'created_at' => $oldDate,
                'updated_at' => $oldDate,
            ]);
        }
        $newKeyword = Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel devis facture paysagiste Indy',
            'search_volume' => 170,
            'keyword_difficulty' => 13,
            'opportunity_score' => 40,
        ]);

        $method = new \ReflectionMethod(EditorialPlanBuilder::class, 'strategicKeywords');
        $selected = $method->invoke(app(EditorialPlanBuilder::class), $project);

        $this->assertTrue($selected->take(120)->contains('id', $newKeyword->id));
        $this->assertTrue($newKeyword->isUnplanned());
    }

    public function test_google_keyword_planner_tsv_with_metadata_is_imported(): void
    {
        $project = SeoProject::query()->create(['name' => 'CRM', 'slug' => 'crm', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $path = tempnam(sys_get_temp_dir(), 'keyword-planner');
        file_put_contents($path, implode("\n", [
            "Keyword Stats 2026-06-29 at 15_29_18\t\t\t\t\t\t\t\t",
            "1 juin 2025 - 31 mai 2026\t\t\t\t\t\t\t\t",
            "Keyword\tCurrency\tAvg. monthly searches\tVariation sur trois mois\tCompetition\tCompetition (indexed value)\tTop of page bid (low range)\tTop of page bid (high range)\tOrganic average position",
            "crm gratuit\tEUR\t5000\t0%\tÉlevé\t68\t7,11\t22,73\t4,5",
            "logiciel crm\tEUR\t5000\t0%\tMoyen\t48\t2,51\t16,36\t",
        ]));

        $count = app(SemrushCsvImporter::class)->import($project, $path);

        $this->assertSame(2, $count);
        $this->assertDatabaseHas('keywords', [
            'seo_project_id' => $project->id,
            'keyword' => 'crm gratuit',
            'search_volume' => 5000,
            'keyword_difficulty' => 68,
            'intent' => 'Commerciale',
            'cpc' => 22.73,
        ]);
    }

    public function test_product_keyword_matcher_rejects_an_unrelated_business_domain(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'SEO Suite',
            'slug' => 'seo-suite',
            'website_url' => 'https://example.com',
            'country' => 'FR',
            'currency' => 'EUR',
            'positioning' => 'Plateforme SEO pour les mots-clés, les SERP et les backlinks.',
        ]);
        $crmKeyword = new Keyword(['keyword' => 'meilleur logiciel crm']);
        $seoKeyword = new Keyword(['keyword' => 'outil de référencement naturel']);
        $matcher = app(ProductKeywordMatcher::class);

        $this->assertFalse($matcher->matches($project, $crmKeyword));
        $this->assertTrue($matcher->matches($project, $seoKeyword));
    }

    public function test_product_keyword_matcher_rejects_synthetic_competitor_keywords(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-synthetic-competitors',
            'website_url' => 'https://example.com',
            'country' => 'FR',
            'currency' => 'EUR',
            'positioning' => 'Logiciel de facturation et comptabilite pour independants.',
            'competitors' => ['Pennylane', 'Abby', 'Freebe', 'Henrri'],
        ]);
        $matcher = app(ProductKeywordMatcher::class);

        $this->assertFalse($matcher->matches($project, new Keyword(['keyword' => 'logiciel facturation invoiceflow max tarif'])));
        $this->assertTrue($matcher->matches($project, new Keyword(['keyword' => 'logiciel facturation gratuit'])));
    }

    public function test_scraper_rejects_private_network_targets(): void
    {
        $project = SeoProject::query()->create(['name' => 'Local', 'slug' => 'local', 'website_url' => 'http://127.0.0.1', 'country' => 'FR', 'currency' => 'EUR']);

        $this->expectException(\RuntimeException::class);
        app(StaticSiteScraper::class)->scrape($project, 'http://127.0.0.1/private');
    }

    public function test_scraper_uses_the_rendered_browser_when_the_http_request_is_blocked(): void
    {
        $project = SeoProject::query()->create(['name' => 'Protected Tool', 'slug' => 'protected-tool', 'website_url' => 'https://example.com/blocked', 'country' => 'FR', 'currency' => 'EUR']);
        Http::fake([
            'https://example.com/robots.txt' => Http::response('', 200, ['Content-Type' => 'text/plain']),
            'https://example.com/blocked' => Http::response('<html><body>Access denied</body></html>', 403, ['Content-Type' => 'text/html']),
        ]);
        $this->mock(BrowserHtmlFetcher::class, function ($mock): void {
            $mock->shouldReceive('fetchPricingData')->once()->andReturn([
                'engine' => 'playwright',
                'http_status' => 200,
                'html_snapshots' => ['<html><head><title>Protected Tool</title></head><body><h1>Compte professionnel</h1><p>Protected Tool propose des services financiers et des moyens de paiement adaptés aux entreprises.</p></body></html>'],
                'json_payloads' => [],
            ]);
        });

        $source = app(StaticSiteScraper::class)->scrape($project, 'https://example.com/blocked', 'homepage');

        $this->assertSame('verified', $source->status);
        $this->assertSame(200, $source->http_status);
        $this->assertSame('playwright_rendered_dom', $source->extraction_method);
        $this->assertStringContainsString('services financiers', $source->content);
    }

    public function test_pricing_scraper_prefers_embedded_plan_cards_over_marketing_benefit_values(): void
    {
        $project = SeoProject::query()->create(['name' => 'Revolut', 'slug' => 'revolut-pricing', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        Http::fake([
            'https://example.com/robots.txt' => Http::response('', 200, ['Content-Type' => 'text/plain']),
            'https://example.com/pricing' => Http::response('<html>Forbidden</html>', 403, ['Content-Type' => 'text/html']),
        ]);
        $renderedHtml = <<<'HTML'
            <html><head><title>Comparez les abonnements</title>
            <script id="__NEXT_DATA__" type="application/json">{
              "props":{"blocks":[
                {"type":"offers","content":{"items":[
                  {"title":"Plus","description":"Des avantages d'une valeur de 115 € par an."},
                  {"title":"Premium","description":"Des avantages d'une valeur de 2 750 € par an."}
                ]}},
                {"type":"cards","content":{"items":[
                  {"title":"Standard","caption":"Gratuit","description":"Pour gérer ses finances au quotidien."},
                  {"title":"Plus","caption":"3,99 €/mois","description":"Pour les dépensiers intelligents."},
                  {"title":"Premium","caption":"10,99 €/mois","description":"Pour améliorer ses finances."},
                  {"title":"Metal","caption":"18,99 €/mois","description":"Pour les voyageurs."},
                  {"title":"Ultra","caption":"60 €/mois (offre promotionnelle)","description":"Pour bénéficier de tous les avantages."}
                ]}}
              ]}}
            </script></head><body>
              <section><h3>Plus</h3><p>Des avantages d'une valeur de 115 € par an.</p></section>
              <section><h3>Premium</h3><p>Des avantages d'une valeur de 2 750 € par an.</p></section>
            </body></html>
            HTML;
        $this->mock(BrowserHtmlFetcher::class, function ($mock) use ($renderedHtml): void {
            $mock->shouldReceive('fetchPricingData')->once()->andReturn([
                'engine' => 'playwright',
                'http_status' => 200,
                'html_snapshots' => [$renderedHtml],
                'json_payloads' => [],
            ]);
        });

        $source = app(StaticSiteScraper::class)->scrape($project, 'https://example.com/pricing', 'pricing');
        $plans = $project->plans()->where('is_active', true)->orderBy('position')->get();

        $this->assertSame(['Standard', 'Plus', 'Premium', 'Metal', 'Ultra'], $plans->pluck('name')->all());
        $this->assertSame([0.0, 3.99, 10.99, 18.99, 60.0], $plans->map(fn (Plan $plan) => (float) $plan->monthly_price)->all());
        $this->assertSame(['EUR'], $plans->pluck('currency')->unique()->values()->all());
        $this->assertStringNotContainsString('115 €', $source->content);
        $this->assertStringNotContainsString('2 750 €', $source->content);
    }

    public function test_embedded_pricing_cards_are_enriched_with_visible_plan_features(): void
    {
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'abby-pricing-enriched', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        Http::fake([
            'https://example.com/robots.txt' => Http::response('', 200, ['Content-Type' => 'text/plain']),
            'https://example.com/abby-pricing' => Http::response('<html>Forbidden</html>', 403, ['Content-Type' => 'text/html']),
        ]);
        $renderedHtml = <<<'HTML'
            <html><head><title>Abby tarifs</title>
            <script id="__NEXT_DATA__" type="application/json">{
              "props":{"pricing":{"items":[
                {"title":"Basique","caption":"0 EUR / mois","description":"100% gratuit, sans engagement"},
                {"title":"Start","caption":"5,85 EUR / mois HT","description":"14 jours offerts pour tester l'offre"},
                {"title":"Pro","caption":"9,75 EUR / mois HT","description":"14 jours offerts pour tester l'offre"}
              ]}}
            }</script></head><body>
              <section>
                <article class="pricing-card"><h2>Basique</h2><p>0 EUR / mois</p><ul>
                  <li>Facturation electronique</li>
                  <li>Devis &amp; factures illimites</li>
                  <li>Livre des recettes et achats</li>
                  <li>Estimation cotisations Urssaf</li>
                  <li>Alertes des seuils de TVA</li>
                </ul></article>
                <article class="pricing-card"><h2>Start</h2><p>5,85 EUR / mois HT</p><ul>
                  <li>En plus de l'offre Basique</li>
                  <li>Recevoir des paiements par CB</li>
                  <li>Declaration Urssaf</li>
                  <li>Envoi des factures par e-mail</li>
                </ul></article>
                <article class="pricing-card"><h2>Pro</h2><p>9,75 EUR / mois HT</p><ul>
                  <li>En plus de l'offre Start</li>
                  <li>Relances d'impayes</li>
                  <li>Signature electronique en ligne</li>
                  <li>Connexion au compte bancaire</li>
                </ul></article>
              </section>
            </body></html>
            HTML;
        $this->mock(BrowserHtmlFetcher::class, function ($mock) use ($renderedHtml): void {
            $mock->shouldReceive('fetchPricingData')->once()->andReturn([
                'engine' => 'playwright',
                'http_status' => 200,
                'html_snapshots' => [$renderedHtml],
                'json_payloads' => [],
            ]);
        });

        $source = app(StaticSiteScraper::class)->scrape($project, 'https://example.com/abby-pricing', 'pricing', 'Abby');
        $plans = $project->competitorPlans()->where('is_active', true)->orderBy('position')->get();

        $this->assertSame(['Basique', 'Start', 'Pro'], $plans->pluck('name')->all());
        $this->assertSame([
            'Facturation electronique',
            'Devis & factures illimites',
            'Livre des recettes et achats',
            'Estimation cotisations Urssaf',
            'Alertes des seuils de TVA',
        ], $plans->firstWhere('name', 'Basique')->features);
        $this->assertNotContains("En plus de l'offre Basique", $plans->firstWhere('name', 'Start')->features);
        $this->assertStringContainsString('Estimation cotisations Urssaf', $source->content);
        $this->assertStringContainsString('Alertes des seuils de TVA', $source->content);
    }

    public function test_competitor_pricing_scraper_stores_competitor_plans_separately(): void
    {
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-competitor-pricing', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $html = <<<'HTML'
            <html><head><title>Abby tarifs</title></head><body>
                <section>
                    <article class="pricing-card"><h2>Basique</h2><p>0 EUR / mois</p><ul><li>Creation de factures</li><li>Suivi des paiements</li></ul></article>
                    <article class="pricing-card"><h2>Pro</h2><p>12 EUR / mois</p><ul><li>Declarations automatisees</li><li>Support prioritaire</li></ul></article>
                </section>
            </body></html>
            HTML;
        Http::fake([
            'https://example.com/robots.txt' => Http::response('', 200, ['Content-Type' => 'text/plain']),
            'https://example.com/abby-pricing' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);
        $this->mock(BrowserHtmlFetcher::class, function ($mock) use ($html): void {
            $mock->shouldReceive('fetchPricingData')->once()->andReturn([
                'engine' => 'playwright',
                'http_status' => 200,
                'html_snapshots' => [$html],
                'json_payloads' => [],
            ]);
        });

        $source = app(StaticSiteScraper::class)->scrape($project, 'https://example.com/abby-pricing', 'pricing', 'Abby');
        $competitorPlans = $project->competitorPlans()->where('is_active', true)->orderBy('position')->get();

        $this->assertSame('Abby', $source->competitor_name);
        $this->assertSame(0, $project->plans()->where('is_active', true)->count());
        $this->assertSame(['Basique', 'Pro'], $competitorPlans->pluck('name')->all());
        $this->assertSame(['Abby'], $competitorPlans->pluck('competitor_name')->unique()->values()->all());
        $this->assertDatabaseHas('source_pages', ['seo_project_id' => $project->id, 'url' => 'https://example.com/abby-pricing', 'competitor_name' => 'Abby']);
        $this->assertDatabaseHas('plans', ['seo_project_id' => $project->id, 'name' => 'Basique', 'competitor_name' => 'Abby']);
    }

    public function test_pricing_scraper_uses_card_titles_and_creates_one_plan_per_offer(): void
    {
        $project = SeoProject::query()->create(['name' => 'Pricing Tool', 'slug' => 'pricing-tool', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'USD']);
        Http::fake([
            'https://example.com/robots.txt' => Http::response('', 200, ['Content-Type' => 'text/plain']),
            'https://example.com/pricing' => Http::response(<<<'HTML'
                <html><head><title>Tarifs officiels</title></head><body>
                <ul class="plans">
                    <li class="plan"><h2>SEO</h2><p>Pour les débutants et les projets individuels.</p><p>117.33 dollars par mois, paiement annuel, au lieu de $139.</p><ul><li>5 sites à surveiller et 500 mots clés.</li></ul></li>
                    <li class="plan"><h2>Pro+</h2><p>Pour les marques en pleine croissance.</p><p>$299/mois.</p><ul><li>15 sites à surveiller et données historiques.</li></ul></li>
                    <li class="plan"><h2>Toutes les fonctionnalités du plan SEO plus</h2><p>Contactez-nous pour en savoir plus.</p></li>
                </ul>
                </body></html>
                HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        app(StaticSiteScraper::class)->scrape($project, 'https://example.com/pricing', 'pricing');

        $this->assertSame(['SEO', 'Pro+'], $project->plans()->where('is_active', true)->orderBy('position')->pluck('name')->all());
        $this->assertSame(2, $project->plans()->where('is_active', true)->count());
        $this->assertDatabaseMissing('plans', ['seo_project_id' => $project->id, 'name' => 'Offre détectée 1']);
    }

    public function test_pricing_scraper_keeps_profile_variants_and_uses_one_ai_recap_without_dom_noise(): void
    {
        $project = SeoProject::query()->create(['name' => 'CleanPrice', 'slug' => 'clean-price', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        Setting::put('gemini_api_key', 'test-key-long-enough-for-pricing-ai', true);
        Setting::put('gemini_model', 'gemini-2.5-flash-lite');
        Http::fake([
            'https://example.com/robots.txt' => Http::response('', 200, ['Content-Type' => 'text/plain']),
            'https://example.com/pricing-variants' => Http::response(<<<'HTML'
                <html><head><title>Tarifs CleanPrice</title></head><body>
                <header><h2>Navigation à supprimer</h2></header>
                <section class="pricing-zone micro">
                    <article class="pricing-card"><span class="card-title">Essentiel</span><div>0 € / mois</div><p class="card-text">Indépendants qui démarrent</p><a>Démarrer</a><ul><li>Facturation illimitée</li><li>Suivi des paiements</li><li class="excluded">Déclarations fiscales</li></ul></article>
                    <article class="pricing-card hidden" aria-hidden="true"><span class="card-title">Essentiel</span><div>0 € / mois, facturé annuellement</div><p class="card-text">Indépendants qui démarrent</p></article>
                    <article class="pricing-card"><span class="card-title">Plus</span><div>Dès 9 € / mois HT, facturé annuellement</div><p class="card-text">TPE jusqu'à 10 personnes</p><button>Démarrer</button><ul><li>Relances automatiques</li><li>Synchronisation bancaire</li><li>Gestion des devis</li><li>Rapports mensuels</li><li>Fonction superflue numéro cinq</li></ul></article>
                </section>
                <section class="pricing-zone enterprise">
                    <article class="pricing-card"><span class="card-title">Plus</span><div>29 € / mois HT</div><p class="card-text">Entreprises de plus de 50 personnes</p><a>Prendre rendez-vous</a><ul><li>Gestion multi-équipes</li><li>Permissions avancées</li><li>Support prioritaire</li></ul></article>
                </section>
                <section class="comparative-matrix"><h2>Comparatif polluant</h2><p>Fonctionnalité 40 à ignorer pour 999 €.</p></section>
                <section class="faq"><h2>FAQ polluante</h2><article class="pricing-card"><h3>Puis-je me passer de comptable ?</h3><p>Un avis client et un faux prix de 199 €.</p></article></section>
                <footer>Avis clients à supprimer</footer>
                </body></html>
                HTML, 200, ['Content-Type' => 'text/html']),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['offers' => [
                    ['candidate_id' => 0, 'name' => 'Essentiel', 'variant' => 'Tous les indépendants', 'audience' => 'Indépendants qui démarrent', 'features' => ['Facturation illimitée', 'Suivi des paiements']],
                    ['candidate_id' => 1, 'name' => 'Plus', 'variant' => 'Selon la taille et la facturation', 'audience' => 'TPE et entreprises', 'features' => ['Relances automatiques', 'Synchronisation bancaire', 'Gestion des devis', 'Rapports mensuels']],
                ]])]]]]],
                'usageMetadata' => ['promptTokenCount' => 500, 'candidatesTokenCount' => 200, 'totalTokenCount' => 700],
            ]),
        ]);

        $source = app(StaticSiteScraper::class)->scrape($project, 'https://example.com/pricing-variants', 'pricing');
        $plans = $project->plans()->where('is_active', true)->orderBy('position')->get();

        $this->assertSame(['Essentiel', 'Plus'], $plans->pluck('name')->all());
        $this->assertCount(2, $plans);
        $this->assertSame(9.0, (float) $plans[1]->monthly_price);
        $this->assertSame(29.0, (float) $plans[1]->monthly_price_max);
        $this->assertSame(9.0, (float) $plans[1]->annual_effective_monthly);
        $this->assertSame(108.0, (float) $plans[1]->annual_total);
        $this->assertCount(2, $plans[1]->price_variants);
        $this->assertLessThanOrEqual(6, count($plans[1]->features));
        $this->assertStringNotContainsString('Démarrer', $source->content);
        $this->assertStringNotContainsString('FAQ polluante', $source->content);
        $this->assertStringNotContainsString('Fonctionnalité 40', $source->content);
        $this->assertStringContainsString('Prix : De 9 € à 29 € / mois', $source->content);
        $this->assertStringContainsString('Public / objectif : TPE et entreprises', $source->content);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/gemini-2.5-flash-lite:generateContent')
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'UNE formule commerciale principale')
            && data_get($request->data(), 'generationConfig.thinkingConfig.thinkingBudget') === 512
            && ! isset($request['generationConfig']['responseJsonSchema']['properties']['offers']['minItems']));
    }

    public function test_pricing_scraper_uses_structured_offer_names_to_reject_marketing_slogans_and_fees(): void
    {
        $project = SeoProject::query()->create(['name' => 'Bank Tool', 'slug' => 'bank-tool', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        Http::fake([
            'https://example.com/robots.txt' => Http::response('', 200, ['Content-Type' => 'text/plain']),
            'https://example.com/pricing' => Http::response(<<<'HTML'
                <html><head><title>Tarifs officiels</title>
                <script type="application/ld+json">{
                  "@context":"https://schema.org", "@type":"SoftwareApplication",
                  "offers":{"@type":"AggregateOffer","offers":[
                    {"@type":"Offer","price":"0","priceCurrency":"EUR","description":"Free — Les basiques pour se lancer."},
                    {"@type":"Offer","price":"9","priceCurrency":"EUR","description":"Start — Pour piloter son activité."},
                    {"@type":"Offer","price":"20","priceCurrency":"EUR","description":"Plus — Pour les activités en croissance."},
                    {"@type":"Offer","price":"60","priceCurrency":"EUR","description":"Business — Pour les équipes."}
                  ]}
                }</script></head><body>
                <section><h2>Des tarifs clairs et transparents</h2><p>Seulement 1 € / mois de frais sur une opération annexe.</p></section>
                <section><div><h3>Free</h3><p>0 € / mois HT</p></div><div><h3>Start</h3><p>9 € / mois HT</p></div><div><h3>Plus</h3><p>20 € / mois HT</p></div><div><h3>Business</h3><p>60 € / mois HT</p></div></section>
                <section><h3>Un service au juste prix</h3><p>150 € de frais exceptionnels.</p></section>
                </body></html>
                HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        $source = app(StaticSiteScraper::class)->scrape($project, 'https://example.com/pricing', 'pricing');
        $plans = $project->plans()->where('is_active', true)->orderBy('position')->get();

        $this->assertSame(['Free', 'Start', 'Plus', 'Business'], $plans->pluck('name')->all());
        $this->assertSame([0.0, 9.0, 20.0, 60.0], $plans->map(fn (Plan $plan) => (float) $plan->monthly_price)->all());
        $this->assertStringNotContainsString('Des tarifs clairs et transparents', $source->content);
        $this->assertStringNotContainsString('Un service au juste prix', $source->content);
        $this->assertStringNotContainsString('Variante / contexte : Plus', $source->content);
    }

    public function test_pricing_api_extractor_accepts_named_recurring_plans_and_rejects_unrelated_amounts(): void
    {
        $scraper = app(StaticSiteScraper::class);
        $extract = new \ReflectionMethod($scraper, 'extractApiPrices');
        $prices = $extract->invoke($scraper, [[
            'url' => 'https://example.com/api/pricing',
            'data' => [
                'plans' => [
                    ['planId' => 'basic', 'name' => 'Basique', 'monthlyPrice' => 14, 'currency' => 'EUR', 'description' => 'Pour démarrer'],
                    ['planId' => 'essential', 'name' => 'Essentiel', 'annualPrice' => 288, 'currency' => 'EUR'],
                ],
                'transactionFee' => ['name' => 'Frais de carte', 'amount' => 1, 'currency' => 'EUR'],
                'marketing' => ['name' => 'Des tarifs clairs et transparents', 'price' => 1, 'currency' => 'EUR'],
            ],
        ]]);

        $this->assertSame(['Basique', 'Essentiel'], array_column($prices, 'name'));
        $this->assertSame(14.0, $prices[0]['monthly_price']);
        $this->assertSame(288.0, $prices[1]['annual_total']);
    }

    public function test_research_dispatches_a_source_crawl_without_running_playwright_in_the_web_request(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'Async Tool', 'slug' => 'async-tool', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);

        Livewire::actingAs($admin)->test(Research::class)
            ->set('projectId', $project->id)
            ->set('type', 'pricing')
            ->set('url', 'https://example.com/pricing')
            ->call('crawl')
            ->assertSet('message', 'Collecte lancée en arrière-plan. Cette page se met à jour automatiquement.')
            ->assertSet('error', '')
            ->assertSet('activeSourceId', fn ($id) => is_int($id) && $id > 0);

        $this->assertDatabaseHas('source_pages', [
            'seo_project_id' => $project->id,
            'url' => 'https://example.com/pricing',
            'status' => 'processing',
        ]);
        $this->assertDatabaseHas('seo_projects', ['id' => $project->id, 'crawl_status' => 'processing']);
    }

    public function test_automation_preparation_waits_for_background_sources_before_unlocking_planning(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Async Automation',
            'slug' => 'async-automation',
            'website_url' => 'https://example.com',
            'pricing_url' => 'https://example.com/pricing',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'logiciel asynchrone']);

        $component = Livewire::actingAs($admin)->test(Automation::class)
            ->set('mode', 'existing')
            ->set('existingProjectId', $project->id)
            ->call('prepare')
            ->assertSet('sourcesCollecting', true)
            ->assertSet('workspaceReady', false)
            ->assertSee('2 source(s) en cours de collecte en arrière-plan');

        $this->assertSame(2, $project->sourcePages()->where('status', 'processing')->count());
        $project->sourcePages()->update(['status' => 'verified', 'verified_at' => now()]);

        $component->call('refreshPreparation')
            ->assertSet('sourcesCollecting', false)
            ->assertSet('workspaceReady', true)
            ->assertSee('Dossier prêt : 2 sources et 1 mots-clés disponibles.');
    }

    public function test_automation_preparation_queues_competitor_pricing_urls(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-automation-pricing',
            'website_url' => 'https://example.com',
            'pricing_url' => 'https://example.com/indy-pricing',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'logiciel de facturation']);

        Livewire::actingAs($admin)->test(Automation::class)
            ->set('mode', 'existing')
            ->set('existingProjectId', $project->id)
            ->set('competitorsText', "Abby\nFreebe")
            ->set('competitorPricingUrlsText', "Abby | https://example.com/abby-pricing\nFreebe | https://example.com/freebe-pricing")
            ->call('prepare')
            ->assertSet('sourcesCollecting', true)
            ->assertSet('workspaceReady', false)
            ->assertSee('4 source(s) en cours de collecte');

        $project->refresh();
        $this->assertSame([
            'Abby' => 'https://example.com/abby-pricing',
            'Freebe' => 'https://example.com/freebe-pricing',
        ], $project->competitor_pricing_urls);
        $this->assertSame(4, $project->sourcePages()->where('status', 'processing')->count());
        $this->assertDatabaseHas('source_pages', [
            'seo_project_id' => $project->id,
            'url' => 'https://example.com/abby-pricing',
            'type' => 'pricing',
            'competitor_name' => 'Abby',
            'status' => 'processing',
        ]);
        $this->assertDatabaseHas('source_pages', [
            'seo_project_id' => $project->id,
            'url' => 'https://example.com/freebe-pricing',
            'type' => 'pricing',
            'competitor_name' => 'Freebe',
            'status' => 'processing',
        ]);
    }

    public function test_automation_prepare_displays_validation_feedback(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)->test(Automation::class)
            ->call('prepare')
            ->assertHasErrors(['name' => 'required', 'websiteUrl' => 'required'])
            ->assertSee('Analyse bloquée');
    }

    public function test_automatic_flow_accepts_pasted_semrush_keywords_without_a_file(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Pasted Automation',
            'slug' => 'pasted-automation',
            'website_url' => 'https://example.com',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        $table = implode("\n", [
            "Mot clé\tIntention\tVolume\tTendance\tKD %\tCPC (EUR)",
            "logiciel devis facture\tI C\t2400\t—\t54\t6,02",
            "logiciel devis facture mac\tC\t210\t—\t19\t4,46",
        ]);

        Livewire::actingAs($admin)->test(Automation::class)
            ->set('mode', 'existing')
            ->set('existingProjectId', $project->id)
            ->set('pastedKeywords', $table)
            ->call('prepare')
            ->assertSet('pastedKeywords', '')
            ->assertSet('sourcesCollecting', true)
            ->assertSet('error', '');

        $this->assertSame(2, $project->keywords()->count());
        $this->assertDatabaseHas('keywords', [
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel devis facture mac',
            'keyword_difficulty' => 19,
        ]);
    }

    public function test_pricing_scraper_handles_fragmented_decimals_and_stale_zero_structured_offers(): void
    {
        $project = SeoProject::query()->create(['name' => 'Invoice Tool', 'slug' => 'invoice-tool', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        Http::fake([
            'https://example.com/robots.txt' => Http::response('', 200, ['Content-Type' => 'text/plain']),
            'https://example.com/pricing' => Http::response(<<<'HTML'
                <html><head><title>Tarifs</title><script type="application/ld+json">{
                  "@context":"https://schema.org","@type":"Product","offers":[
                    {"@type":"Offer","name":"Basique","price":"0","priceCurrency":"EUR","description":"Version gratuite"},
                    {"@type":"Offer","name":"Start (annuel)","price":"108","priceCurrency":"EUR","description":"soit 9€/mois"},
                    {"@type":"Offer","name":"Start (mensuel)","price":"11","priceCurrency":"EUR","description":"facturation mensuelle"}
                  ]
                }</script></head><body>
                <section><div class="plan-shell"><span>Basique</span><div><b>€</b><b>0</b></div><p>100% gratuit, sans engagement</p></div>
                <div class="plan-shell"><span>Start</span><div><b>€</b><b>5</b><span>,85</span><small>/mois HT</small><s>9€</s></div><p>Offre promotionnelle</p></div></section>
                <section class="comparative"><span>Basique</span><span>9,00 € /mois</span></section>
                </body></html>
                HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        $source = app(StaticSiteScraper::class)->scrape($project, 'https://example.com/pricing', 'pricing');
        $plans = $project->plans()->where('is_active', true)->orderBy('position')->get();

        $this->assertSame(['Basique', 'Start'], $plans->pluck('name')->all());
        $this->assertSame(0.0, (float) $plans[0]->monthly_price);
        $this->assertSame(0.0, (float) $plans[0]->monthly_price_max);
        $this->assertSame(5.85, (float) $plans[1]->monthly_price);
        $this->assertStringNotContainsString('De 0 € à 9 €', $source->content);
    }

    public function test_admin_access_is_logged(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/projects')->assertOk();
        $this->assertDatabaseHas('admin_access_logs', ['user_id' => $admin->id, 'path' => '/admin/projects', 'status_code' => 200]);
    }

    public function test_admin_can_select_all_filtered_rows_and_bulk_delete(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'Bulk Tool', 'slug' => 'bulk-tool', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $otherProject = SeoProject::query()->create(['name' => 'Other Tool', 'slug' => 'other-tool', 'website_url' => 'https://example.org', 'country' => 'FR', 'currency' => 'EUR']);
        foreach (['crm gratuit', 'crm pme', 'crm agence'] as $keyword) {
            Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => $keyword]);
        }
        Keyword::query()->create(['seo_project_id' => $otherProject->id, 'keyword' => 'à conserver']);

        Livewire::actingAs($admin)->test(KeywordsTable::class)
            ->set('projectId', $project->id)
            ->set('selectAll', true)
            ->assertCount('selectedIds', 3)
            ->call('deleteSelected')
            ->assertSet('selectedIds', []);

        $this->assertSame(0, $project->keywords()->count());
        $this->assertSame(1, $otherProject->keywords()->count());

        $firstArticle = Article::query()->create(['seo_project_id' => $project->id, 'title' => 'Premier article', 'slug' => 'premier-article', 'body' => 'Contenu']);
        $secondArticle = Article::query()->create(['seo_project_id' => $project->id, 'title' => 'Second article', 'slug' => 'second-article', 'body' => 'Contenu']);
        Livewire::actingAs($admin)->test(ArticlesTable::class)
            ->set('selectedIds', [$firstArticle->id])
            ->call('deleteSelected');

        $this->assertDatabaseMissing('articles', ['id' => $firstArticle->id]);
        $this->assertDatabaseHas('articles', ['id' => $secondArticle->id]);
    }

    public function test_articles_table_offers_a_preview_for_an_unpublished_article(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Preview Tool', 'slug' => 'preview-tool', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);
        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Un titre suffisamment long pour occuper deux lignes dans la bibliothèque éditoriale',
            'slug' => 'article-preview',
            'status' => 'review',
            'body' => '## Contenu de prévisualisation',
        ]);

        Livewire::actingAs($admin)->test(ArticlesTable::class)
            ->assertSee('Voir ↗')
            ->assertSee(route('admin.articles.preview', $article), false);

        $this->actingAs($admin)
            ->get(route('admin.articles.preview', $article))
            ->assertOk()
            ->assertSee($article->title);
    }

    public function test_articles_table_can_regenerate_an_article_from_row_actions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Regen Tool', 'slug' => 'regen-tool', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);
        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Article a regenerer',
            'slug' => 'article-a-regenerer',
            'status' => 'review',
            'type' => 'informational',
            'body' => '## Ancien contenu',
        ]);

        $this->mock(ArticleRegenerationWorkerLauncher::class, function ($mock) use ($article): void {
            $mock->shouldReceive('launch')
                ->once()
                ->with($article->id);
        });

        Livewire::actingAs($admin)->test(ArticlesTable::class)
            ->assertSee('Régénérer')
            ->call('regenerate', $article->id)
            ->assertSee('Régénération lancée en arrière-plan');

        $checks = $article->fresh()->quality_checks;
        $this->assertSame('queued', $checks['regeneration_status']);
        $this->assertSame($admin->id, $checks['regeneration_user_id']);
    }

    public function test_article_regeneration_worker_runs_regeneration_and_marks_completion(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Worker Regen Tool', 'slug' => 'worker-regen-tool', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);
        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Article worker',
            'slug' => 'article-worker',
            'status' => 'review',
            'type' => 'informational',
            'body' => 'Ancien contenu.',
            'quality_checks' => [
                'regeneration_status' => 'queued',
                'regeneration_user_id' => $admin->id,
            ],
        ]);

        $this->mock(GeminiContentGenerator::class, function ($mock) use ($article): void {
            $mock->shouldReceive('regenerateArticle')
                ->once()
                ->withArgs(fn (Article $passed, string $instructions): bool => $passed->is($article)
                    && str_contains($instructions, 'Regeneration manuelle'))
                ->andReturnUsing(function (Article $passed): Article {
                    $passed->forceFill([
                        'body' => 'Nouveau contenu via worker.',
                        'quality_checks' => ['human_review_required' => true],
                    ])->save();

                    return $passed->fresh();
                });
        });

        $this->artisan('article:regenerate-worker', ['articleId' => $article->id])
            ->assertExitCode(0);

        $article->refresh();
        $this->assertSame('Nouveau contenu via worker.', $article->body);
        $this->assertSame('completed', $article->quality_checks['regeneration_status']);
        $this->assertTrue($article->quality_checks['human_review_required']);
    }

    public function test_regenerating_an_article_replaces_content_without_changing_slug_or_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Stable Tool', 'slug' => 'stable-tool', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);
        $publishedAt = now()->subDay();
        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Article stable',
            'slug' => 'article-stable',
            'status' => 'published',
            'type' => 'informational',
            'primary_keyword' => 'article stable',
            'body' => 'Ancien contenu long.',
            'content_blocks' => [['type' => 'markdown', 'content' => 'Ancien contenu long.']],
            'published_at' => $publishedAt,
        ]);
        $generated = Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Article stable',
            'slug' => 'article-stable-regenerated',
            'status' => 'review',
            'type' => 'informational',
            'primary_keyword' => 'article stable',
            'body' => 'Nouveau contenu regenere.',
            'excerpt' => 'Nouveau resume.',
            'content_blocks' => [['type' => 'markdown', 'content' => 'Nouveau contenu regenere.']],
            'quality_checks' => ['human_review_required' => true],
            'source_ids' => [],
            'generated_by' => 'gemini-test',
            'verified_at' => now(),
        ]);

        $generator = new class(
            app(SeoContentStructure::class),
            app(EditorialDuplicateDetector::class),
            app(GeneratedContentSanitizer::class),
            app(CompetitorCatalog::class),
            $generated,
        ) extends GeminiContentGenerator {
            public array $call = [];

            public function __construct(
                SeoContentStructure $structures,
                EditorialDuplicateDetector $duplicates,
                GeneratedContentSanitizer $sanitizer,
                CompetitorCatalog $competitors,
                private readonly Article $generatedArticle,
            ) {
                parent::__construct($structures, $duplicates, $sanitizer, $competitors);
            }

            public function generate(SeoProject $project, string $type, ?Keyword $keyword, string $instructions = '', ?array $lockedBlueprint = null, ?string $lockedTitle = null, ?int $ignoreArticleId = null, ?string $model = null): Article
            {
                $this->call = compact('type', 'lockedTitle', 'ignoreArticleId');

                return $this->generatedArticle;
            }
        };

        $this->actingAs($admin);
        $updated = $generator->regenerateArticle($article, 'Test regeneration');

        $this->assertSame($article->id, $updated->id);
        $this->assertSame('article-stable', $updated->slug);
        $this->assertSame('published', $updated->status);
        $this->assertSame($publishedAt->toDateTimeString(), $updated->published_at->toDateTimeString());
        $this->assertSame('Nouveau contenu regenere.', $updated->body);
        $this->assertSame('gemini-test', $updated->generated_by);
        $this->assertDatabaseMissing('articles', ['id' => $generated->id]);
        $this->assertDatabaseHas('article_versions', [
            'article_id' => $article->id,
            'title' => 'Article stable',
            'body' => 'Ancien contenu long.',
            'change_note' => 'Regeneration IA depuis la bibliotheque',
        ]);
        $this->assertSame([
            'type' => 'informational',
            'lockedTitle' => 'Article stable',
            'ignoreArticleId' => $article->id,
        ], $generator->call);
    }

    public function test_regenerating_an_article_retries_capacity_errors_and_switches_to_flash(): void
    {
        Setting::put('gemini_model', 'gemini-2.5-flash-lite');
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Retry Regen Tool', 'slug' => 'retry-regen-tool', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);
        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Article retry',
            'slug' => 'article-retry',
            'status' => 'review',
            'type' => 'informational',
            'primary_keyword' => 'article retry',
            'body' => 'Ancien contenu.',
            'content_blocks' => [['type' => 'markdown', 'content' => 'Ancien contenu.']],
        ]);
        $generated = Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Article retry',
            'slug' => 'article-retry-temp',
            'status' => 'review',
            'type' => 'informational',
            'primary_keyword' => 'article retry',
            'body' => 'Nouveau contenu apres retry.',
            'content_blocks' => [['type' => 'markdown', 'content' => 'Nouveau contenu apres retry.']],
            'source_ids' => [],
            'quality_checks' => [],
            'verified_at' => now(),
        ]);

        $generator = new class(
            app(SeoContentStructure::class),
            app(EditorialDuplicateDetector::class),
            app(GeneratedContentSanitizer::class),
            app(CompetitorCatalog::class),
            $generated,
        ) extends GeminiContentGenerator {
            public int $attempts = 0;

            public array $models = [];

            public function __construct(
                SeoContentStructure $structures,
                EditorialDuplicateDetector $duplicates,
                GeneratedContentSanitizer $sanitizer,
                CompetitorCatalog $competitors,
                private readonly Article $generatedArticle,
            ) {
                parent::__construct($structures, $duplicates, $sanitizer, $competitors);
            }

            public function generate(SeoProject $project, string $type, ?Keyword $keyword, string $instructions = '', ?array $lockedBlueprint = null, ?string $lockedTitle = null, ?int $ignoreArticleId = null, ?string $model = null): Article
            {
                $this->attempts++;
                $this->models[] = $model;

                if ($this->attempts <= 3) {
                    throw new \RuntimeException('Gemini HTTP 503 : This model is currently experiencing high demand.');
                }

                return $this->generatedArticle;
            }

            protected function pauseBeforeRegenerationRetry(int $attempt): void
            {
            }
        };

        $this->actingAs($admin);
        $updated = $generator->regenerateArticle($article, 'Test retry');

        $this->assertSame('Nouveau contenu apres retry.', $updated->body);
        $this->assertSame(4, $generator->attempts);
        $this->assertSame([
            'gemini-2.5-flash-lite',
            'gemini-2.5-flash-lite',
            'gemini-2.5-flash-lite',
            'gemini-2.5-flash',
        ], $generator->models);
    }

    public function test_regenerating_an_article_retries_quality_rejections_with_corrective_prompt(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'Indy', 'slug' => 'indy-quality-retry', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);
        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Logiciel Facturation BTP : Les Outils Indispensables pour Artisans et Entreprises',
            'slug' => 'logiciel-facturation-btp-outils-indispensables',
            'status' => 'review',
            'type' => 'informational',
            'primary_keyword' => 'logiciel facturation BTP',
            'body' => 'Ancien contenu.',
            'content_blocks' => [['type' => 'markdown', 'content' => 'Ancien contenu.']],
        ]);
        $generated = Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => $article->title,
            'slug' => 'logiciel-facturation-btp-temp',
            'status' => 'review',
            'type' => 'informational',
            'primary_keyword' => 'logiciel facturation BTP',
            'body' => 'Nouveau contenu BTP corrige.',
            'content_blocks' => [['type' => 'markdown', 'content' => 'Nouveau contenu BTP corrige.']],
            'source_ids' => [],
            'quality_checks' => [],
            'verified_at' => now(),
        ]);

        $generator = new class(
            app(SeoContentStructure::class),
            app(EditorialDuplicateDetector::class),
            app(GeneratedContentSanitizer::class),
            app(CompetitorCatalog::class),
            $generated,
        ) extends GeminiContentGenerator {
            public int $attempts = 0;

            public string $secondInstructions = '';

            public function __construct(
                SeoContentStructure $structures,
                EditorialDuplicateDetector $duplicates,
                GeneratedContentSanitizer $sanitizer,
                CompetitorCatalog $competitors,
                private readonly Article $generatedArticle,
            ) {
                parent::__construct($structures, $duplicates, $sanitizer, $competitors);
            }

            public function generate(SeoProject $project, string $type, ?Keyword $keyword, string $instructions = '', ?array $lockedBlueprint = null, ?string $lockedTitle = null, ?int $ignoreArticleId = null, ?string $model = null): Article
            {
                $this->attempts++;
                if ($this->attempts === 1) {
                    throw new PlannedContentRejectedException('Concurrent inconnu ou fictif detecte dans le brouillon : Plomberie Pro.');
                }

                $this->secondInstructions = $instructions;

                return $this->generatedArticle;
            }

            protected function pauseBeforeRegenerationRetry(int $attempt): void
            {
            }
        };

        $this->actingAs($admin);
        $updated = $generator->regenerateArticle($article, 'Test rejet qualite');

        $this->assertSame('Nouveau contenu BTP corrige.', $updated->body);
        $this->assertSame(2, $generator->attempts);
        $this->assertStringContainsString('CORRECTION OBLIGATOIRE APRÈS REFUS QUALITÉ', $generator->secondInstructions);
        $this->assertStringContainsString('Plomberie Pro', $generator->secondInstructions);
        $this->assertStringContainsString('Entités autorisées uniquement', $generator->secondInstructions);
    }

    public function test_projects_table_can_bulk_delete_a_project_and_its_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'Projet à supprimer', 'slug' => 'projet-supprimer', 'website_url' => 'https://delete.example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $projectToKeep = SeoProject::query()->create(['name' => 'Projet à conserver', 'slug' => 'projet-conserver', 'website_url' => 'https://keep.example.com', 'country' => 'FR', 'currency' => 'EUR']);
        Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'mot clé supprimé']);
        SourcePage::query()->create(['seo_project_id' => $project->id, 'url' => 'https://delete.example.com/source', 'status' => 'verified', 'verified_at' => now()]);
        Article::query()->create(['seo_project_id' => $project->id, 'title' => 'Article supprimé', 'slug' => 'article-supprime', 'body' => 'Contenu']);

        Livewire::actingAs($admin)->test(ProjectsTable::class)
            ->set('search', 'Projet à supprimer')
            ->set('selectAll', true)
            ->assertSet('selectedIds', [$project->id])
            ->call('deleteSelected')
            ->assertSee('1 projet(s) et toutes leurs données associées supprimés.');

        $this->assertDatabaseMissing('seo_projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('keywords', ['seo_project_id' => $project->id]);
        $this->assertDatabaseMissing('source_pages', ['seo_project_id' => $project->id]);
        $this->assertDatabaseMissing('articles', ['seo_project_id' => $project->id]);
        $this->assertDatabaseHas('seo_projects', ['id' => $projectToKeep->id]);
    }

    public function test_an_automated_run_generates_the_requested_content(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'Tool Auto', 'slug' => 'tool-auto', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $source = SourcePage::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'homepage',
            'url' => 'https://example.com',
            'title' => 'Source officielle',
            'content' => 'Tool Auto propose une fonctionnalité vérifiée.',
            'status' => 'verified',
            'confidence_score' => 0.9,
            'verified_at' => now(),
        ]);
        EvidenceChunk::query()->create([
            'source_page_id' => $source->id,
            'category' => 'feature',
            'value' => 'Tool Auto propose une fonctionnalité vérifiée.',
            'source_excerpt' => 'Tool Auto propose une fonctionnalité vérifiée.',
            'confidence_score' => 0.9,
            'verified_at' => now(),
        ]);
        $firstKeyword = Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'avis Tool Auto',
            'search_volume' => 200,
            'keyword_difficulty' => 20,
            'intent' => 'Commerciale',
            'cluster' => 'Avis',
            'opportunity_score' => 85,
        ]);
        Setting::put('gemini_api_key', 'test-key-long-enough-for-validation', true);
        $longBody = <<<'MARKDOWN'
Réponse courte : l’avis Tool Auto convient aux PME qui veulent évaluer une adoption structurée, sous réserve des limites documentées. Ce guide affilié analyse les données officielles sans inventer de test. Chaque information factuelle est reliée à la source officielle [S1].

- Vérifier les données avant migration.
- Former une équipe pilote avant le déploiement.

## Verdict rapide et points clés
Tool Auto répond à certains besoins documentés, avec un verdict qui doit rester nuancé [S1].

| Critère | Évaluation documentée | Limite |
|---|---|---|
| Fonctionnalités | Vérifiées dans la source [S1] | Périmètre à contrôler |
| Adoption | Déploiement progressif conseillé [S1] | Formation nécessaire |

## Présentation : qu’est-ce que l’outil ?
Tool Auto est présenté à partir des informations disponibles [S1].

## À qui s’adresse l’outil — et à qui il ne convient pas
Les profils adaptés et les limites sont distingués à partir des preuves [S1].

## Fonctionnalités principales analysées
La fonctionnalité principale est documentée [S1].

## Prise en main et cas d’usage concrets
Les cas d’usage restent conditionnés aux éléments vérifiables [S1].

### Scénario illustratif
Une PME de 15 personnes peut simuler une réduction de 30 % du temps de saisie pour comparer ses options. Cette hypothèse n’est pas une performance observée de Tool Auto.

## Tarifs, formules et coût réel
Le modèle d’abonnement et la facturation doivent être vérifiés sur la grille officielle afin d’estimer le coût total de possession [S1].

## Avantages et limites
Les avantages ne doivent pas masquer chaque limite identifiée [S1].

## Intégrations, support et conditions commerciales
Ces informations sont non communiquées lorsque les preuves manquent [S1].

## Alternatives selon le profil et le budget
Une alternative doit être évaluée avec des sources dédiées [S1].

## FAQ
### Tool Auto convient-il aux PME ?
La réponse dépend du besoin documenté [S1].
### Quel est son prix ?
Le modèle de facturation doit être contrôlé sur la grille officielle [S1].
### Existe-t-il un essai ?
Cette information est non communiquée [S1].
### Quelles sont ses limites ?
Les limites dépendent du cas d’usage [S1].
### Comment choisir une alternative ?
Il faut comparer des données vérifiées [S1].

## Verdict final et méthodologie de vérification
Le verdict final repose sur les sources officielles et exige une relecture humaine [S1].
MARKDOWN;
        $sectionFiller = "- Vérifier les données et les limites avant toute décision.\n\n".collect(range(1, 30))->map(
            fn (int $index): string => "Point d’analyse {$index} : l’avis Tool Auto doit être évalué avec une limite réaliste, un conseil de déploiement et une information vérifiée dans la source officielle [S1]."
        )->implode(' ');
        $longBody = preg_replace('/^(## .+)$/mu', "$1\n\n{$sectionFiller}", $longBody) ?: $longBody;
        $longBody .= "\n\n".collect(range(1, 190))->map(
            fn (int $index): string => "Complément {$index} : cette analyse approfondit la décision avec une limite explicite et une donnée vérifiée dans la source officielle [S1]."
        )->implode(' ');
        $geminiResponse = fn (string $body): array => [
            'candidates' => [['content' => ['parts' => [['text' => json_encode([
                'title' => 'Avis Tool Auto : le test',
                'slug' => 'avis-tool-auto-test',
                'meta_title' => 'Avis Tool Auto',
                'meta_description' => 'Notre avis sourcé sur Tool Auto.',
                'body' => $body,
                'brief_title' => 'Brief avis Tool Auto',
                'angle' => 'Analyse factuelle pour les PME',
                'audience' => 'PME',
                'outline' => ['Présentation', 'Fonctionnalités', 'Verdict'],
            ])]]]]],
        ];
        $idea = fn (string $title, string $topic, string $angle, string $promise, array $outline): array => [
            'title' => $title,
            'primary_keyword' => 'avis Tool Auto',
            'entity' => 'tool-auto',
            'topic' => $topic,
            'intent' => 'commercial',
            'angle' => $angle,
            'audience' => 'pme',
            'problem' => $promise,
            'expected_outcome' => $promise,
            'funnel_stage' => 'consideration',
            'unique_promise' => $promise,
            'excluded_topics' => ['tarifs détaillés', 'comparatif général'],
            'outline' => $outline,
            'content_type' => 'tool_review',
        ];
        $ideaResponse = ['candidates' => [['content' => ['parts' => [['text' => json_encode(['ideas' => [
            $idea('Maîtriser Tool Auto : évaluer son adoption en PME', 'adoption-pme', 'audit-adoption-pme', 'Évaluer les prérequis humains et techniques avant de déployer Tool Auto dans une PME.', ['Préparer les données', 'Cartographier les équipes', 'Configurer les droits', 'Former les utilisateurs', 'Mesurer l’adoption']),
            $idea('Maîtriser Tool Auto : organiser la migration', 'migration-donnees', 'migration-sans-perte', 'Préparer une migration de données vers Tool Auto sans interrompre le travail quotidien.', ['Auditer les données', 'Définir le périmètre', 'Nettoyer les doublons', 'Tester l’import', 'Contrôler la bascule']),
            $idea('Maîtriser Tool Auto : piloter le déploiement', 'deploiement', 'deploiement-par-equipe', 'Déployer Tool Auto équipe par équipe avec des critères de validation opérationnels.', ['Choisir une équipe pilote', 'Définir les rôles', 'Configurer le socle', 'Recueillir les retours', 'Étendre le déploiement']),
        ]])]]]]]];
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($ideaResponse)
                ->push($geminiResponse($longBody))
                ->push($geminiResponse($longBody))
                ->push($geminiResponse($longBody)),
        ]);

        $component = Livewire::actingAs($admin)->test(Automation::class)
            ->assertSet('projectId', $project->id)
            ->assertSet('workspaceReady', true)
            ->set('projectId', $project->id)
            ->set('workspaceReady', true)
            ->set('contentCount', 1)
            ->call('startRun')
            ->assertDispatched('planning-started')
            ->assertNotDispatched('batch-started')
            ->call('processPlanningStep')
            ->assertDispatched('planning-finished')
            ->assertSee('1/1 angles validés')
            ->call('launchRun')
            ->assertDispatched('batch-started')
            ->assertSee('Production automatique en cours');

        for ($step = 0; $step < 3; $step++) {
            $component->call('processNext')->assertDispatched('batch-item-finished');
        }
        $this->assertDatabaseHas('content_runs', ['seo_project_id' => $project->id, 'status' => 'completed', 'completed_count' => 1]);
        $this->assertDatabaseHas('articles', ['seo_project_id' => $project->id, 'status' => 'published', 'title' => 'Maîtriser Tool Auto : évaluer son adoption en PME']);
        $this->assertDatabaseHas('content_briefs', ['seo_project_id' => $project->id, 'angle' => 'audit-adoption-pme']);
        $this->assertDatabaseHas('editorial_plans', ['seo_project_id' => $project->id, 'status' => 'completed', 'accepted_count' => 1]);
        Http::assertSentCount(4);
        Http::assertSent(fn ($request) => $request['generationConfig']['maxOutputTokens'] === 5120
            && $request['generationConfig']['thinkingConfig']['thinkingBudget'] === 768
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'DIRECTIVES SEO, UX & AFFILIATION')
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'PLAN GLOBAL DU DOCUMENT')
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'H1 verrouillé')
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'CURRENT_YEAR = '.now()->format('Y'))
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'CURRENT_DATE est réservé au header et au footer')
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'Écris les marques autorisées avec leur casse officielle')
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'Ne réutilise jamais mot pour mot une phrase')
            && $request['generationConfig']['stopSequences'] === ['Transparence affiliée', '© 2026 BusinessKit']);
    }

    public function test_automation_displays_the_used_keyword_difficulty_next_to_generated_titles(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-kd-badge', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $keyword = Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'logiciel devis facture mac',
            'search_volume' => 210,
            'keyword_difficulty' => 19,
            'opportunity_score' => 88,
        ]);
        $plan = EditorialPlan::query()->create([
            'seo_project_id' => $project->id,
            'user_id' => $admin->id,
            'name' => 'Plan KD',
            'requested_count' => 1,
            'accepted_count' => 1,
            'status' => 'generating',
        ]);
        $idea = EditorialIdea::query()->create([
            'editorial_plan_id' => $plan->id,
            'keyword_id' => $keyword->id,
            'title' => 'Les meilleurs logiciels de devis et facture sur Mac',
            'primary_keyword' => $keyword->keyword,
            'entity_key' => 'indy',
            'topic_key' => 'logiciel-facturation-mac',
            'intent' => 'commercial',
            'angle' => 'compatibilite-macos',
            'audience' => 'independants-mac',
            'problem' => 'Choisir un logiciel compatible avec macOS.',
            'expected_outcome' => 'Comparer les options adaptées.',
            'funnel_stage' => 'consideration',
            'unique_promise' => 'Sélectionner une solution adaptée à macOS.',
            'excluded_topics' => [],
            'outline' => ['Compatibilité', 'Comparaison', 'FAQ'],
            'fingerprint' => 'indy|mac|commercial|compatibilite',
            'content_type' => 'best_tools',
            'status' => 'accepted',
            'seo_score' => 88,
            'position' => 1,
        ]);
        $run = ContentRun::query()->create([
            'seo_project_id' => $project->id,
            'user_id' => $admin->id,
            'editorial_plan_id' => $plan->id,
            'name' => 'Campagne KD',
            'requested_count' => 1,
            'status' => 'pending',
        ]);
        $run->items()->create([
            'keyword_id' => $keyword->id,
            'editorial_idea_id' => $idea->id,
            'content_type' => 'best_tools',
            'status' => 'pending',
        ]);

        $component = Livewire::actingAs($admin)->test(Automation::class)->assertSee('KD 19');

        $this->assertGreaterThanOrEqual(2, substr_count($component->html(), 'KD 19'));
    }

    public function test_new_articles_use_three_calls_while_started_legacy_articles_keep_their_saved_partition(): void
    {
        $generator = app(GeminiContentGenerator::class);

        $this->assertSame(3, $generator->partCount('tool_review'));
        $this->assertSame(3, $generator->partCount('pricing'));
        $this->assertSame(3, $generator->partCount('informational'));

        $legacyFirstPart = "## Verdict rapide et points clés\n\nTexte sauvegardé.\n\n## Présentation : qu’est-ce que l’outil ?\n\nTexte sauvegardé.";
        $this->assertSame(6, $generator->partCount('tool_review', previousParts: [$legacyFirstPart]));
    }

    public function test_failed_campaign_items_can_be_retried_without_recreating_the_campaign(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'Retry Tool', 'slug' => 'retry-tool', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $keyword = Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'avis Retry Tool',
            'search_volume' => 100,
            'keyword_difficulty' => 20,
            'opportunity_score' => 60,
        ]);
        $run = ContentRun::query()->create([
            'seo_project_id' => $project->id,
            'user_id' => $admin->id,
            'name' => 'Campagne à relancer',
            'requested_count' => 1,
            'failed_count' => 1,
            'status' => 'completed_with_errors',
            'completed_at' => now(),
        ]);
        $item = $run->items()->create([
            'keyword_id' => $keyword->id,
            'content_type' => 'tool_review',
            'status' => 'failed',
            'error_message' => 'Ancienne erreur de génération',
            'completed_at' => now(),
        ]);

        Livewire::actingAs($admin)->test(Automation::class)
            ->call('retryFailedRun', $run->id)
            ->assertSet('activeRunId', $run->id)
            ->assertSet('workspaceReady', true)
            ->assertDispatched('batch-started');

        $this->assertDatabaseHas('content_runs', [
            'id' => $run->id,
            'status' => 'pending',
            'failed_count' => 0,
        ]);
        $this->assertDatabaseHas('content_run_items', [
            'id' => $item->id,
            'status' => 'pending',
            'error_message' => null,
            'completed_at' => null,
        ]);
    }

    public function test_an_active_campaign_can_be_stopped_without_losing_generated_parts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'Stop Tool', 'slug' => 'stop-tool', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $plan = EditorialPlan::query()->create([
            'seo_project_id' => $project->id,
            'user_id' => $admin->id,
            'name' => 'Plan à arrêter',
            'requested_count' => 2,
            'accepted_count' => 2,
            'status' => 'generating',
            'locked_at' => now(),
        ]);
        $run = ContentRun::query()->create([
            'seo_project_id' => $project->id,
            'user_id' => $admin->id,
            'editorial_plan_id' => $plan->id,
            'name' => 'Campagne à arrêter',
            'requested_count' => 2,
            'status' => 'processing',
            'started_at' => now(),
        ]);
        $processing = $run->items()->create([
            'content_type' => 'informational',
            'status' => 'processing',
            'generation_parts' => ['## Partie conservée\n\nContenu déjà généré.'],
            'generation_step' => 1,
            'started_at' => now(),
        ]);
        $pending = $run->items()->create(['content_type' => 'informational', 'status' => 'pending']);

        Livewire::actingAs($admin)->test(Automation::class)
            ->assertSet('activeRunId', $run->id)
            ->assertSeeHtml('wire:poll.5s.keep-alive="processNext"')
            ->assertSee('La génération avance automatiquement')
            ->call('stopRun')
            ->assertDispatched('batch-stopped')
            ->assertSee('Campagne arrêtée');

        $this->assertDatabaseHas('content_runs', ['id' => $run->id, 'status' => 'completed_with_errors', 'failed_count' => 2]);
        $this->assertDatabaseHas('content_run_items', ['id' => $processing->id, 'status' => 'failed', 'generation_step' => 1]);
        $this->assertDatabaseHas('content_run_items', ['id' => $pending->id, 'status' => 'failed']);
        $this->assertSame(['## Partie conservée\n\nContenu déjà généré.'], $processing->fresh()->generation_parts);
        $this->assertSame('locked', $plan->fresh()->status);
    }

    public function test_flash_lite_capacity_retries_indefinitely_and_another_error_stops_the_campaign(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'Capacity Tool', 'slug' => 'capacity-tool', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $source = SourcePage::query()->create([
            'seo_project_id' => $project->id,
            'url' => 'https://example.com/features',
            'type' => 'features',
            'title' => 'Fonctionnalités officielles',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        EvidenceChunk::query()->create([
            'source_page_id' => $source->id,
            'category' => 'feature',
            'value' => 'Fonction vérifiée',
            'source_excerpt' => 'Une fonction officielle est documentée.',
            'confidence_score' => .9,
            'verified_at' => now(),
        ]);
        $keyword = Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'guide Capacity Tool',
            'opportunity_score' => 80,
        ]);
        $run = ContentRun::query()->create([
            'seo_project_id' => $project->id,
            'user_id' => $admin->id,
            'name' => 'Campagne capacité',
            'requested_count' => 1,
            'status' => 'pending',
        ]);
        $item = $run->items()->create([
            'keyword_id' => $keyword->id,
            'content_type' => 'informational',
            'status' => 'pending',
        ]);
        Setting::put('gemini_api_key', 'test-key-long-enough-for-validation', true);
        $capacityResponse = ['error' => ['message' => 'This model is currently experiencing high demand. Spikes in demand are usually temporary. Please try again later.']];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push($capacityResponse, 503)
            ->push($capacityResponse, 503)
            ->push($capacityResponse, 503)
            ->push(['error' => ['message' => 'Invalid request payload.']], 400)]);

        $component = Livewire::actingAs($admin)->test(Automation::class)
            ->assertSet('activeRunId', $run->id);

        $component->call('processNext')->assertDispatched('batch-retry-later');
        $this->assertSame(1, $item->fresh()->api_attempts);
        $component->call('processNext')->assertDispatched('batch-retry-later');
        $this->assertSame(2, $item->fresh()->api_attempts);
        $component->call('processNext')->assertDispatched('batch-retry-later');

        $this->assertDatabaseHas('content_runs', ['id' => $run->id, 'status' => 'pending', 'failed_count' => 0]);
        $this->assertDatabaseHas('content_run_items', ['id' => $item->id, 'status' => 'pending', 'api_attempts' => 3]);

        $component->call('processNext')->assertDispatched('batch-stopped');

        $this->assertDatabaseHas('content_runs', ['id' => $run->id, 'status' => 'completed_with_errors', 'failed_count' => 1]);
        $this->assertDatabaseHas('content_run_items', ['id' => $item->id, 'status' => 'failed', 'api_attempts' => 3]);
    }

    public function test_planning_retries_flash_lite_capacity_errors_automatically_without_a_manual_resume(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create([
            'name' => 'HubSpot', 'slug' => 'hubspot-auto-retry', 'website_url' => 'https://example.com',
            'country' => 'FR', 'currency' => 'EUR',
        ]);
        $source = SourcePage::query()->create([
            'seo_project_id' => $project->id, 'url' => 'https://example.com/features', 'type' => 'features',
            'title' => 'Fonctionnalités CRM', 'status' => 'verified', 'verified_at' => now(),
        ]);
        EvidenceChunk::query()->create([
            'source_page_id' => $source->id, 'category' => 'feature',
            'value' => 'Pipeline commercial et gestion des prospects.',
            'source_excerpt' => 'HubSpot documente un pipeline commercial et la gestion des prospects.',
            'confidence_score' => .95, 'verified_at' => now(),
        ]);
        Keyword::query()->create([
            'seo_project_id' => $project->id, 'keyword' => 'hubspot crm pour pme',
            'intent' => 'Informationnelle', 'opportunity_score' => 90,
        ]);
        Setting::put('gemini_api_key', 'test-key-long-enough-for-validation', true);
        Setting::put('gemini_model', 'gemini-2.5-flash-lite');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'error' => ['message' => 'This model is currently experiencing high demand.'],
        ], 503)]);

        Livewire::actingAs($admin)->test(Automation::class)
            ->set('projectId', $project->id)
            ->set('workspaceReady', true)
            ->set('contentCount', 1)
            ->call('startRun')
            ->assertDispatched('planning-started')
            ->assertSeeHtml('wire:poll.5s.keep-alive="processPlanningStep"')
            ->assertDontSee('Reprendre la planification')
            ->call('processPlanningStep')
            ->assertDispatched('planning-retry-later')
            ->assertSee('Nouvelle tentative automatique dans 5 secondes');

        $this->assertDatabaseHas('editorial_plans', [
            'seo_project_id' => $project->id,
            'status' => 'planning',
            'attempts' => 0,
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/gemini-2.5-flash-lite:generateContent')
            && $request['generationConfig']['thinkingConfig']['thinkingBudget'] === 1024
            && ! isset($request['generationConfig']['responseJsonSchema']['properties']['ideas']['minItems'])
            && ! isset($request['generationConfig']['responseJsonSchema']['properties']['ideas']['maxItems'])
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'CURRENT_YEAR = '.now()->format('Y'))
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'PHASE FONDATIONS')
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'strategy_tier')
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'new_for_planning')
            && str_contains((string) data_get($request->data(), 'contents.0.parts.0.text'), 'ORTHOGRAPHE DES MARQUES'));
    }

    public function test_editorial_fingerprint_blocks_keyword_variants_but_allows_a_distinct_angle(): void
    {
        $project = SeoProject::query()->create(['name' => 'HubSpot', 'slug' => 'hubspot', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $existingKeyword = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'gestion de la relation client', 'intent' => 'Informationnelle']);
        $variantKeyword = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'logiciel de gestion de relation client', 'intent' => 'Commerciale']);
        $pipelineKeyword = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'créer un pipeline commercial HubSpot', 'intent' => 'Informationnelle']);
        $clientManagementKeyword = new Keyword(['keyword' => 'logiciel de gestion des clients', 'intent' => 'Informationnelle']);
        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'keyword_id' => $existingKeyword->id,
            'type' => 'informational',
            'title' => 'Maîtriser HubSpot CRM',
            'slug' => 'maitriser-hubspot-crm',
            'status' => 'review',
            'primary_keyword' => $existingKeyword->keyword,
            'body' => "## Utiliser le CRM\nGuide général.\n## FAQ\n### Comment démarrer ?\nRéponse.",
        ]);
        $detector = app(EditorialDuplicateDetector::class);
        $detector->hydrateArticleFingerprint($article);

        $duplicate = $detector->analyzeBefore($project, $variantKeyword, 'informational');
        $distinct = $detector->analyzeBefore($project, $pipelineKeyword, 'informational');
        $clientManagementBlueprint = $detector->blueprint($project, $clientManagementKeyword, 'informational');

        $this->assertSame('block', $duplicate['decision']);
        $this->assertGreaterThanOrEqual(85, $duplicate['score']);
        $this->assertSame($article->id, $duplicate['article']->id);
        $this->assertSame('allow', $distinct['decision']);
        $this->assertLessThan(50, $distinct['score']);
        $this->assertSame('hubspot-crm|gestion-relation-client|informational|centraliser-interactions-clients|general|centraliser-et-suivre-les-interactions-clients', $clientManagementBlueprint['fingerprint']);
    }

    public function test_an_automation_batch_keeps_only_one_keyword_per_editorial_fingerprint(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Setting::put('gemini_api_key', 'test-key-long-enough-for-automation', true);
        $project = SeoProject::query()->create(['name' => 'HubSpot', 'slug' => 'hubspot', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $source = SourcePage::query()->create([
            'seo_project_id' => $project->id,
            'url' => 'https://example.com/crm',
            'type' => 'homepage',
            'title' => 'HubSpot CRM',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        EvidenceChunk::query()->create([
            'source_page_id' => $source->id,
            'category' => 'feature',
            'value' => 'CRM commercial avec pipeline et gestion des prospects',
            'source_excerpt' => 'CRM commercial avec pipeline et gestion des prospects',
            'confidence_score' => 0.95,
            'verified_at' => now(),
        ]);
        $first = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'gestion de la relation client', 'intent' => 'Informationnelle', 'opportunity_score' => 95]);
        Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'logiciel de gestion relation client', 'intent' => 'Commerciale', 'opportunity_score' => 90]);
        $pipeline = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'créer un pipeline commercial HubSpot', 'intent' => 'Informationnelle', 'opportunity_score' => 85]);
        $relances = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'automatiser les relances HubSpot', 'intent' => 'Informationnelle', 'opportunity_score' => 80]);
        $reporting = Keyword::query()->create(['seo_project_id' => $project->id, 'keyword' => 'rapport commercial HubSpot', 'intent' => 'Informationnelle', 'opportunity_score' => 75]);

        $idea = fn (string $title, string $topic, string $angle, string $outcome, array $outline, Keyword $keyword): array => [
            'source_keyword_id' => $keyword->id,
            'title' => $title,
            'primary_keyword' => $keyword->keyword,
            'entity' => 'hubspot-crm',
            'topic' => $topic,
            'intent' => 'informational',
            'angle' => $angle,
            'audience' => 'equipes-commerciales',
            'problem' => $outcome,
            'expected_outcome' => $outcome,
            'funnel_stage' => 'consideration',
            'unique_promise' => $outcome,
            'excluded_topics' => ['tarifs', 'comparatif général'],
            'outline' => $outline,
            'content_type' => 'informational',
        ];
        $ideas = [
            $idea('Centraliser les interactions clients dans HubSpot', 'gestion-clients', 'centraliser-interactions', 'Centraliser toutes les interactions clients et identifier le prochain suivi commercial.', ['Importer les contacts', 'Normaliser les propriétés', 'Associer les entreprises', 'Journaliser les échanges', 'Contrôler les doublons'], $first),
            $idea('Optimiser la relation client dans HubSpot', 'gestion-relations-clients', 'centraliser-interactions', 'Centraliser toutes les interactions clients et identifier le prochain suivi commercial.', ['Importer les contacts', 'Normaliser les propriétés', 'Associer les entreprises', 'Journaliser les échanges', 'Contrôler les doublons'], $first),
            $idea('Créer un pipeline commercial dans HubSpot', 'pipeline-commercial', 'configurer-etapes-vente', 'Créer un pipeline mesurable qui reflète les étapes réelles du cycle de vente.', ['Cartographier le cycle', 'Créer les étapes', 'Définir les règles', 'Automatiser les tâches', 'Mesurer les conversions'], $pipeline),
            $idea('Automatiser les relances dans HubSpot', 'relances-prospects', 'relances-selon-maturite', 'Déclencher des relances adaptées au niveau de maturité de chaque prospect.', ['Segmenter les prospects', 'Définir les délais', 'Créer les workflows', 'Gérer les exceptions', 'Mesurer les réponses'], $relances),
            $idea('Configurer les rapports commerciaux HubSpot', 'reporting-commercial', 'tableau-bord-direction', 'Construire un tableau de bord qui relie activité commerciale et conversion.', ['Choisir les indicateurs', 'Fiabiliser les propriétés', 'Créer les rapports', 'Assembler le tableau', 'Organiser la revue'], $reporting),
        ];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['ideas' => $ideas])]]]]],
        ])]);

        $component = Livewire::actingAs($admin)->test(Automation::class)
            ->set('projectId', $project->id)
            ->set('workspaceReady', true)
            ->set('contentCount', 2)
            ->call('startRun')
            ->assertDispatched('planning-started')
            ->call('processPlanningStep')
            ->assertDispatched('planning-finished')
            ->assertHasNoErrors()
            ->assertNotDispatched('batch-started');

        $plan = $project->editorialPlans()->latest('id')->firstOrFail();
        $this->assertSame('locked', $plan->status);
        $this->assertSame(2, $plan->accepted_count);
        $this->assertSame(1, $plan->duplicate_count);
        $this->assertSame(2, $plan->ideas()->where('status', 'accepted')->count());
        $this->assertSame(1, $plan->ideas()->where('status', 'reserve')->count());
        $this->assertSame(0, $project->contentRuns()->count());

        $component->call('launchRun')->assertDispatched('batch-started');
        $run = $project->contentRuns()->latest('id')->firstOrFail();
        $this->assertCount(2, $run->items);
    }

    public function test_a_rejected_planned_idea_is_replaced_without_reducing_the_target(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'HubSpot', 'slug' => 'hubspot-replacement', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $plan = EditorialPlan::query()->create([
            'seo_project_id' => $project->id, 'user_id' => $admin->id, 'name' => 'Plan avec réserve',
            'requested_count' => 1, 'accepted_count' => 1, 'status' => 'locked', 'locked_at' => now(),
        ]);
        $ideaData = [
            'primary_keyword' => 'hubspot crm', 'entity_key' => 'hubspot-crm', 'intent' => 'informational',
            'audience' => 'pme', 'problem' => 'Résoudre un problème commercial précis.',
            'expected_outcome' => 'Obtenir un résultat commercial vérifiable.', 'funnel_stage' => 'consideration',
            'unique_promise' => 'Fournir une méthode opérationnelle complète aux équipes commerciales.',
            'excluded_topics' => [], 'outline' => ['Un', 'Deux', 'Trois', 'Quatre', 'Cinq'],
            'content_type' => 'informational', 'seo_score' => 80, 'similarity_score' => 20, 'source_coverage' => 90,
        ];
        $rejected = $plan->ideas()->create($ideaData + [
            'title' => 'Premier angle', 'topic_key' => 'pipeline', 'angle' => 'configuration-pipeline',
            'fingerprint' => 'hubspot|pipeline|info|configuration', 'status' => 'accepted', 'position' => 1,
        ]);
        $reserve = $plan->ideas()->create($ideaData + [
            'title' => 'Angle de réserve', 'topic_key' => 'reporting', 'angle' => 'reporting-direction',
            'fingerprint' => 'hubspot|reporting|info|direction', 'status' => 'reserve', 'position' => 2, 'seo_score' => 75,
        ]);

        $replacement = app(EditorialPlanBuilder::class)->replacementFor($plan, $rejected);

        $this->assertSame($reserve->id, $replacement->id);
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('accepted', $reserve->fresh()->status);
        $this->assertSame($rejected->id, $reserve->fresh()->replacement_for_id);
        $this->assertSame(1, $plan->fresh()->accepted_count);
    }

    public function test_generation_cannot_start_from_an_incomplete_editorial_plan(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'Incomplete', 'slug' => 'incomplete', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $plan = EditorialPlan::query()->create([
            'seo_project_id' => $project->id, 'user_id' => $admin->id, 'name' => 'Plan incomplet',
            'requested_count' => 5, 'accepted_count' => 4, 'status' => 'planning',
        ]);

        Livewire::actingAs($admin)->test(Automation::class)
            ->set('activePlanId', $plan->id)
            ->call('launchRun')
            ->assertSee('reste verrouillé');

        $this->assertDatabaseCount('content_runs', 0);
    }

    public function test_generation_never_enqueues_more_items_than_the_requested_count(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'HubSpot', 'slug' => 'hubspot-limit', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $plan = EditorialPlan::query()->create([
            'seo_project_id' => $project->id, 'user_id' => $admin->id, 'name' => 'Plan limité',
            'requested_count' => 2, 'accepted_count' => 2, 'status' => 'locked', 'locked_at' => now(),
        ]);
        foreach (range(1, 3) as $position) {
            $plan->ideas()->create([
                'title' => "Angle {$position}", 'primary_keyword' => "crm angle {$position}", 'entity_key' => 'hubspot-crm',
                'topic_key' => "sujet-{$position}", 'intent' => 'informational', 'angle' => "angle-{$position}",
                'audience' => 'pme', 'problem' => 'Problème métier suffisamment précis.',
                'expected_outcome' => 'Résultat opérationnel suffisamment précis.', 'funnel_stage' => 'consideration',
                'unique_promise' => 'Promesse éditoriale suffisamment précise pour le test.', 'excluded_topics' => [],
                'outline' => ['Un', 'Deux', 'Trois', 'Quatre', 'Cinq'], 'fingerprint' => "fingerprint-{$position}",
                'content_type' => 'informational', 'status' => 'accepted', 'seo_score' => 90 - $position,
                'position' => $position,
            ]);
        }

        Livewire::actingAs($admin)->test(Automation::class)
            ->set('activePlanId', $plan->id)
            ->call('launchRun');

        $run = ContentRun::query()->firstOrFail();
        $this->assertSame(2, $run->items()->count());
        $this->assertSame(2, $plan->ideas()->where('status', 'accepted')->count());
        $this->assertSame(1, $plan->ideas()->where('status', 'reserve')->count());
    }

    public function test_an_admin_can_publish_an_article_even_when_similarity_requires_a_warning(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'HubSpot', 'slug' => 'hubspot-publish', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $body = "Réponse courte sur le choix d’un CRM pour PME.\n\n## Critères de choix\n\n".str_repeat('Comparez les processus, le budget et les besoins de vos équipes commerciales. ', 8);
        Article::query()->create([
            'seo_project_id' => $project->id, 'type' => 'best_tools', 'title' => 'Choisir un CRM pour une PME',
            'slug' => 'choisir-crm-pme-reference', 'status' => 'published', 'body' => $body,
            'primary_keyword' => 'logiciel crm pme', 'search_intent' => 'commercial',
            'content_angle' => 'criteres-selection-crm', 'editorial_audience' => 'dirigeants-pme',
            'unique_promise' => 'Choisir objectivement un CRM adapté à une petite entreprise.', 'published_at' => now(),
        ]);
        $article = Article::query()->create([
            'seo_project_id' => $project->id, 'type' => 'best_tools', 'title' => 'Guide CRM pour les PME',
            'slug' => 'guide-crm-pme', 'status' => 'review', 'body' => $body,
            'primary_keyword' => 'logiciel crm pme', 'search_intent' => 'commercial',
            'content_angle' => 'criteres-selection-crm', 'editorial_audience' => 'dirigeants-pme',
            'unique_promise' => 'Choisir objectivement un CRM adapté à une petite entreprise.',
            'content_blocks' => [['type' => 'markdown', 'content' => $body]],
        ]);

        Livewire::actingAs($admin)->test(ArticleEditor::class, ['article' => $article])
            ->set('status', 'published')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Article publié');

        $this->assertSame('published', $article->fresh()->status);
        $this->assertNotNull($article->fresh()->published_at);
        $this->assertContains($article->fresh()->duplicate_status, ['potential', 'needs_differentiation']);
    }

    public function test_article_editor_allows_year_slugs_but_blocks_real_duplicate_numeric_suffixes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-year-slugs', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $body = "Introduction utile.\n\n## Analyse\n\n".str_repeat('Ce paragraphe donne assez de contexte pour valider un article de test. ', 4);

        Livewire::actingAs($admin)->test(ArticleEditor::class)
            ->set('projectId', $project->id)
            ->set('title', 'Indy vs Abby Freebe 2026')
            ->set('slug', 'indy-abby-freebe-2026')
            ->set('body', $body)
            ->set('type', 'comparison')
            ->set('status', 'review')
            ->call('save')
            ->assertHasNoErrors(['slug']);

        Article::query()->create([
            'seo_project_id' => $project->id,
            'title' => 'Indy Abby Freebe',
            'slug' => 'indy-abby-freebe',
            'body' => $body,
            'status' => 'review',
        ]);

        Livewire::actingAs($admin)->test(ArticleEditor::class)
            ->set('projectId', $project->id)
            ->set('title', 'Indy Abby Freebe variante')
            ->set('slug', 'indy-abby-freebe-2')
            ->set('body', $body)
            ->set('type', 'comparison')
            ->set('status', 'review')
            ->call('save')
            ->assertHasErrors(['slug']);
    }

    public function test_merging_a_published_duplicate_archives_it_and_creates_a_redirect(): void
    {
        $project = SeoProject::query()->create(['name' => 'HubSpot', 'slug' => 'hubspot', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $canonical = Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'informational',
            'title' => 'Guide HubSpot CRM',
            'slug' => 'maitriser-hubspot-crm',
            'status' => 'review',
            'primary_keyword' => 'hubspot crm',
            'body' => "Introduction.\n\n## Pipeline commercial\nSection courte.",
            'content_blocks' => [['type' => 'markdown', 'content' => 'Ancien contenu']],
        ]);
        $duplicate = Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'informational',
            'title' => 'Maîtriser HubSpot pour sa relation client',
            'slug' => 'maitriser-hubspot-relation-client-2',
            'status' => 'published',
            'primary_keyword' => 'gestion relation client',
            'body' => "Introduction bis.\n\n## Pipeline commercial\n".str_repeat('Section plus complète. ', 20),
            'content_blocks' => [['type' => 'markdown', 'content' => 'Doublon']],
            'published_at' => now(),
        ]);

        app(EditorialConsolidationService::class)->merge($duplicate, $canonical, 96);

        $canonical->refresh();
        $duplicate->refresh();
        $this->assertSame('published', $canonical->status);
        $this->assertSame('maitriser-hubspot-crm', $canonical->slug);
        $this->assertStringContainsString('Section plus complète', $canonical->body);
        $this->assertSame('archived', $duplicate->status);
        $this->assertSame($canonical->id, $duplicate->canonical_article_id);
        $this->assertDatabaseHas('redirects', [
            'from_path' => '/blog/maitriser-hubspot-relation-client-2',
            'to_path' => '/blog/maitriser-hubspot-crm',
            'status_code' => 301,
        ]);
        $this->get('/blog/maitriser-hubspot-relation-client-2')->assertRedirect('/blog/maitriser-hubspot-crm');
    }

    public function test_internal_linking_excludes_cannibalizing_topics_and_prioritizes_complementary_pages(): void
    {
        $project = SeoProject::query()->create(['name' => 'HubSpot', 'slug' => 'hubspot-links', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $common = [
            'seo_project_id' => $project->id,
            'status' => 'published',
            'published_at' => now(),
            'entity_key' => 'hubspot-crm',
            'search_intent' => 'informational',
            'editorial_audience' => 'courtiers-assurance',
        ];
        $pillar = Article::query()->create($common + [
            'type' => 'informational',
            'title' => 'HubSpot CRM pour les courtiers en assurance',
            'slug' => 'hubspot-crm-assurance-pilier',
            'primary_keyword' => 'hubspot assurance',
            'topic_key' => 'crm-assurance',
            'content_angle' => 'gestion-client-courtier',
            'unique_promise' => 'Configurer la relation client pour un cabinet de courtage.',
            'body' => "Les tarifs HubSpot et le CRM assurance aident à décider.\n\n## Configurer les contrats\n\nMéthode métier.",
        ]);
        $cannibalizing = Article::query()->create($common + [
            'type' => 'informational',
            'title' => 'CRM assurance avec HubSpot',
            'slug' => 'crm-assurance-doublon',
            'primary_keyword' => 'crm assurance',
            'topic_key' => 'crm-assurance',
            'content_angle' => 'gestion-client-courtier',
            'unique_promise' => 'Configurer la relation client pour un cabinet de courtage.',
            'body' => "## Configurer les contrats\n\nMéthode métier presque identique.",
        ]);
        $pricing = Article::query()->create($common + [
            'type' => 'pricing',
            'title' => 'Tarifs HubSpot CRM',
            'slug' => 'tarifs-hubspot-crm',
            'primary_keyword' => 'tarifs HubSpot',
            'topic_key' => 'tarifs-crm',
            'content_angle' => 'cout-total-possession',
            'editorial_audience' => 'directions-financieres',
            'unique_promise' => 'Comparer le coût total des plans HubSpot.',
            'body' => "## Comparer les plans\n\nAnalyse des coûts.",
        ]);
        $comparison = Article::query()->create($common + [
            'type' => 'comparison',
            'title' => 'HubSpot ou Salesforce pour une assurance',
            'slug' => 'hubspot-salesforce-assurance',
            'primary_keyword' => 'comparatif CRM assurance',
            'topic_key' => 'comparaison-crm',
            'content_angle' => 'aide-decision',
            'unique_promise' => 'Comparer deux CRM pour un cabinet de courtage.',
            'body' => "## Critères de choix\n\nComparaison fonctionnelle.",
        ]);
        $automation = Article::query()->create($common + [
            'type' => 'informational',
            'title' => 'Automatiser les relances de contrats',
            'slug' => 'automatiser-relances-contrats',
            'primary_keyword' => 'automatiser relances assurance',
            'topic_key' => 'relances-contrats',
            'content_angle' => 'workflow-echeances',
            'unique_promise' => 'Créer un workflow distinct pour les échéances de contrats.',
            'body' => "## Programmer les échéances\n\nMéthode de relance.",
        ]);
        Article::query()->create($common + [
            'type' => 'informational',
            'title' => 'Configurer les rapports assurance',
            'slug' => 'rapports-crm-assurance',
            'primary_keyword' => 'reporting CRM assurance',
            'topic_key' => 'reporting-assurance',
            'content_angle' => 'tableaux-bord-direction',
            'unique_promise' => 'Mesurer les renouvellements et les délais de traitement.',
            'body' => "## Suivre les indicateurs\n\nTableaux de bord métier.",
        ]);

        $created = app(InternalLinkService::class)->refresh($pillar);

        $this->assertSame(3, $created);
        $this->assertSame(3, $pillar->internalLinks()->count());
        $this->assertDatabaseMissing('internal_links', ['source_article_id' => $pillar->id, 'target_article_id' => $cannibalizing->id]);
        $this->assertDatabaseHas('internal_links', ['source_article_id' => $pillar->id, 'target_article_id' => $pricing->id]);
        $this->assertTrue($pillar->internalLinks()->with('target')->get()->every(
            fn ($link): bool => $link->anchor_text !== $link->target->title,
        ));
    }

    public function test_redirecting_a_duplicate_preserves_the_pillar_and_rewires_the_old_url(): void
    {
        $project = SeoProject::query()->create(['name' => 'HubSpot', 'slug' => 'hubspot-pillar', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $pillar = Article::query()->create([
            'seo_project_id' => $project->id, 'type' => 'informational', 'title' => 'Article pilier assurance',
            'slug' => 'article-pilier-assurance', 'status' => 'published', 'body' => 'Contenu pilier validé.', 'published_at' => now(),
        ]);
        $duplicate = Article::query()->create([
            'seo_project_id' => $project->id, 'type' => 'informational', 'title' => 'Ancien article assurance',
            'slug' => 'ancien-article-assurance', 'status' => 'published', 'body' => 'Ancien contenu.', 'published_at' => now(),
        ]);

        app(EditorialConsolidationService::class)->redirectDuplicate($duplicate, $pillar, 91);

        $this->assertSame('Contenu pilier validé.', $pillar->fresh()->body);
        $this->assertSame('Article pilier assurance', $pillar->fresh()->title);
        $this->assertSame('archived', $duplicate->fresh()->status);
        $this->assertSame($pillar->id, $duplicate->fresh()->canonical_article_id);
        $this->assertDatabaseHas('redirects', [
            'from_path' => '/blog/ancien-article-assurance',
            'to_path' => '/blog/article-pilier-assurance',
            'status_code' => 301,
            'active' => true,
        ]);
    }

    public function test_a_non_admin_cannot_open_the_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_import_raw_questions_list_without_semrush_headers(): void
    {
        $project = SeoProject::query()->create(['name' => 'Indy', 'slug' => 'indy-test-questions', 'website_url' => 'https://example.com', 'country' => 'FR', 'currency' => 'EUR']);
        $importer = app(\App\Services\SemrushCsvImporter::class);

        $questionsText = "Comment faire une facture d'acompte avec Indy ?\nComment calculer les cotisations Urssaf en micro ?\nPourquoi ouvrir un compte bancaire pro ?";
        $count = $importer->importText($project, $questionsText);

        $this->assertSame(3, $count);
        $this->assertDatabaseHas('keywords', [
            'seo_project_id' => $project->id,
            'keyword' => "Comment faire une facture d'acompte avec Indy ?",
        ]);
    }

    public function test_resolve_block_returns_null_for_indy_association_articles(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy',
            'website_url' => 'https://www.indy.fr',
            'country' => 'FR',
            'currency' => 'EUR'
        ]);
        
        $articleNormal = Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'informational',
            'title' => 'Comptabilité pour micro-entreprise',
            'slug' => 'comptabilite-micro-entreprise',
            'status' => 'published',
            'primary_keyword' => 'micro-entreprise compta',
            'body' => 'Contenu normal.'
        ]);

        $articleAssociation = Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'informational',
            'title' => 'Guide de la comptabilité pour une association loi 1901',
            'slug' => 'comptabilite-association-loi-1901',
            'status' => 'published',
            'primary_keyword' => 'comptabilité association',
            'body' => 'Contenu association.'
        ]);

        $service = app(\App\Services\AffiliateBlockService::class);
        
        $blockNormal = $service->resolveBlock($articleNormal, 'after_intro');
        $blockAssociation = $service->resolveBlock($articleAssociation, 'after_intro');

        $this->assertNotNull($blockNormal);
        $this->assertNull($blockAssociation);
    }

    public function test_competitor_catalog_supports_banking_competitors_contextually(): void
    {
        $project = SeoProject::query()->create([
            'name' => 'Indy',
            'slug' => 'indy-test-banking-comps',
            'website_url' => 'https://www.indy.fr',
            'country' => 'FR',
            'currency' => 'EUR',
            'competitors' => ['Pennylane', 'Abby', 'Freebe']
        ]);

        $catalog = app(CompetitorCatalog::class);

        $compsDefault = $catalog->competitorsFor($project);
        $this->assertContains('Pennylane', $compsDefault);
        $this->assertNotContains('Société Générale', $compsDefault);

        $compsBanking = $catalog->competitorsFor($project, 'meilleur compte pro Société Générale ou Qonto');
        $this->assertContains('Pennylane', $compsBanking);
        $this->assertContains('Société Générale', $compsBanking);
        $this->assertContains('Qonto', $compsBanking);

        $mentions = $catalog->mentionedCompetitors($project, 'quel est le meilleur compte pro : Qonto, Shine ou Société Générale ?');
        $this->assertContains('Société Générale', $mentions);
        $this->assertContains('Qonto', $mentions);
        $this->assertContains('Shine', $mentions);
        
        $unknown = $catalog->unknownCompetitorMentions($project, 'quel est le meilleur compte pro : Qonto, Shine ou Société Générale ?');
        $this->assertNotContains('Société Générale', $unknown);
    }
}
