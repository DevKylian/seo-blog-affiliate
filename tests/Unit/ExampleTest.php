<?php

namespace Tests\Unit;

use App\Livewire\Automation;
use App\Exceptions\PlannedContentRejectedException;
use App\Models\ContentRun;
use App\Models\Keyword;
use App\Models\SeoProject;
use App\Services\BackgroundArtisanLauncher;
use App\Services\CompetitorCatalog;
use App\Services\ContentRunWorkerLauncher;
use App\Services\EditorialDuplicateDetector;
use App\Services\GeminiContentGenerator;
use App\Services\GeneratedContentSanitizer;
use App\Services\RuntimeBinaryLocator;
use App\Services\SeoContentStructure;
use App\Services\SeoSlugGenerator;
use App\Services\TopicNormalizer;
use Tests\TestCase;

final class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    public function test_source_markers_are_removed_from_generated_markdown(): void
    {
        $body = <<<'MD'
## Comparatif

Indy documente cette fonction [S1]. Abby confirme une limite [S2][S3]. Freebe ajoute une nuance [S2, S5].

| Outil | Preuve |
| --- | --- |
| Indy | Fonction vérifiée [S1] |
MD;

        $clean = (new GeneratedContentSanitizer)->stripSourceMarkers($body);

        $this->assertStringNotContainsString('[S1]', $clean);
        $this->assertStringNotContainsString('[S2]', $clean);
        $this->assertStringNotContainsString('[S3]', $clean);
        $this->assertStringNotContainsString('[S2, S5]', $clean);
        $this->assertStringContainsString('Indy documente cette fonction.', $clean);
        $this->assertStringContainsString('| Indy | Fonction vérifiée |', $clean);
    }

    public function test_keyword_matching_accepts_normal_french_accents(): void
    {
        $body = <<<'MD'
Réponse courte : la facture électronique aide les indépendants à fiabiliser leur gestion administrative dès la première émission.

## Comprendre le besoin
Ce contenu explique la méthode, les limites, les tarifs, les outils et les erreurs fréquentes avec une approche pédagogique.
MD;

        $audit = (new SeoContentStructure)->audit($body, 'informational', 'facture electronique', true);

        $this->assertTrue($audit['checks']['keyword_in_opening']);
    }

    public function test_background_worker_resolves_the_cli_binary_when_http_runs_under_php_fpm(): void
    {
        $binaries = new RuntimeBinaryLocator;
        $launcher = new ContentRunWorkerLauncher(new BackgroundArtisanLauncher($binaries), $binaries);
        $fpmBinary = PHP_BINARY.'-fpm';

        $this->assertSame(realpath(PHP_BINARY), $launcher->resolveCliBinary($fpmBinary));
        $this->assertStringNotContainsString('fpm', mb_strtolower(basename($launcher->resolveCliBinary($fpmBinary))));
    }

    public function test_background_worker_commands_are_portable_on_windows_macos_and_linux(): void
    {
        $launcher = new BackgroundArtisanLauncher(new RuntimeBinaryLocator);
        $windows = $launcher->buildDetachedCommand(
            'content:run-worker',
            42,
            'C:\\Program Files\\PHP\\php.exe',
            'C:\\Sites\\Blog SEO\\artisan',
            'C:\\Sites\\Blog SEO\\storage\\logs\\content.log',
            'Windows',
        );
        $unix = $launcher->buildDetachedCommand(
            'content:run-worker',
            42,
            '/usr/bin/php',
            '/var/www/blog seo/artisan',
            '/var/www/blog seo/storage/logs/content.log',
            'Linux',
            '/usr/bin/nohup',
        );

        $this->assertStringStartsWith('cmd /D /C start "" /B powershell -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand ', $windows);
        $this->assertStringEndsWith(' > NUL 2>&1', $windows);
        preg_match('/-EncodedCommand ([A-Za-z0-9+\/=]+)/', $windows, $encodedCommand);
        $windowsScript = mb_convert_encoding(base64_decode($encodedCommand[1]), 'UTF-8', 'UTF-16LE');
        $this->assertStringContainsString('Start-Process', $windowsScript);
        $this->assertStringContainsString("-WindowStyle Hidden", $windowsScript);
        $this->assertStringContainsString("-FilePath 'C:\\Program Files\\PHP\\php.exe'", $windowsScript);
        $this->assertStringContainsString("-ArgumentList '\"C:\\Sites\\Blog SEO\\artisan\" content:run-worker 42'", $windowsScript);
        $this->assertStringContainsString("-RedirectStandardOutput 'C:\\Sites\\Blog SEO\\storage\\logs\\content.log'", $windowsScript);
        $this->assertStringContainsString("-RedirectStandardError 'C:\\Sites\\Blog SEO\\storage\\logs\\content.err.log'", $windowsScript);
        $this->assertStringNotContainsString('/dev/null', $windows);
        $this->assertStringContainsString("'/usr/bin/nohup'", $unix);
        $this->assertStringContainsString("'/var/www/blog seo/artisan'", $unix);
        $this->assertStringContainsString('< /dev/null &', $unix);
        $this->assertStringNotContainsString('NUL', $unix);
    }

    public function test_runtime_locator_accepts_explicit_cross_platform_binary_paths(): void
    {
        $binaries = new RuntimeBinaryLocator;

        $this->assertSame(realpath(PHP_BINARY), $binaries->resolvePhp(null, PHP_BINARY));
        $this->assertSame(realpath(PHP_BINARY), $binaries->resolveNode(PHP_BINARY));
        $this->assertSame(realpath(PHP_BINARY), $binaries->resolveBrowser(PHP_BINARY));
    }

    public function test_every_content_type_has_a_long_enforced_seo_structure(): void
    {
        $service = new SeoContentStructure;

        foreach (['tool_review', 'pricing', 'comparison', 'best_tools', 'alternatives', 'informational'] as $type) {
            $structure = $service->for($type);

            $this->assertGreaterThanOrEqual(2200, $structure['target_min']);
            $this->assertGreaterThanOrEqual(10, count($structure['sections']));
            $this->assertStringContainsString('STRUCTURE ÉDITORIALE OBLIGATOIRE', $service->prompt($type));

            $audit = $service->audit('Texte trop court sans structure.', $type, 'mot clé', true);
            $this->assertFalse($audit['passed']);
            $this->assertFalse($audit['checks']['minimum_length']);
        }
    }

    public function test_seo_audit_rejects_a_useless_yes_only_table(): void
    {
        $service = new SeoContentStructure;
        $body = 'Réponse courte : cet outil permet de répondre au besoin avec une recommandation nuancée, une limite réaliste et des conseils de déploiement. '
            .str_repeat('Cette phrase apporte une information vérifiable et explique un compromis concret. ', 12)
            ."\n\n| Critère | Offre A | Offre B |\n|---|---|---|\n| Fonction | Oui | Oui |\n| Support | Oui | Oui |\n\n- Nettoyer les données avant la migration.\n\nScénario illustratif : une PME de 15 personnes simule une réduction de 30 % du temps de saisie ; il ne s’agit pas d’un résultat observé.";

        $audit = $service->audit($body, 'tool_review', null, true);

        $this->assertTrue($audit['checks']['decision_table']);
        $this->assertFalse($audit['checks']['useful_decision_table']);
        $this->assertTrue($audit['checks']['realistic_weakness']);
        $this->assertTrue($audit['checks']['implementation_advice']);
        $this->assertTrue($audit['checks']['labeled_scenario_metric']);
        $this->assertTrue($audit['checks']['scannable_lists']);
        $this->assertTrue($audit['checks']['comparison_limits_column']);

        $comparisonAudit = $service->audit($body, 'comparison', null, true);
        $this->assertFalse($comparisonAudit['checks']['comparison_limits_column']);
    }

    public function test_multi_product_faq_requires_forty_percent_non_affiliate_questions(): void
    {
        $service = new SeoContentStructure;
        $biasedFaq = <<<'MARKDOWN'
## FAQ sur les alternatives
### HubSpot est-il adapté aux PME ?
Réponse.
### Quel est le prix de HubSpot ?
Réponse.
### Comment migrer vers HubSpot ?
Réponse.
### HubSpot propose-t-il une offre gratuite ?
Réponse.
### Comment comparer deux alternatives entre elles ?
Réponse.
MARKDOWN;
        $balancedFaq = <<<'MARKDOWN'
## FAQ sur les alternatives
### HubSpot est-il adapté aux PME ?
Réponse.
### Quel est le prix de HubSpot ?
Réponse.
### Comment migrer vers HubSpot ?
Réponse.
### Comment comparer deux alternatives entre elles ?
Réponse.
### Quelle méthode employer pour une migration CRM globale ?
Réponse.
MARKDOWN;

        $this->assertFalse($service->audit($biasedFaq, 'alternatives', null, true, 'HubSpot')['checks']['faq_balanced']);
        $this->assertTrue($service->audit($balancedFaq, 'alternatives', null, true, 'HubSpot')['checks']['faq_balanced']);
    }

    public function test_ecommerce_alternatives_mix_hybrid_and_traditional_crm_tools(): void
    {
        $service = new SeoContentStructure;
        $completeScope = 'Klaviyo couvre le volet marketing automation tandis que Pipedrive représente le CRM commercial traditionnel.';
        $traditionalOnly = 'Pipedrive et Salesforce sont deux CRM commerciaux traditionnels.';

        $this->assertTrue($service->audit($completeScope, 'alternatives', 'crm e-commerce b2c', true, 'HubSpot')['checks']['ecommerce_hybrid_scope']);
        $this->assertFalse($service->audit($traditionalOnly, 'alternatives', 'crm e-commerce b2c', true, 'HubSpot')['checks']['ecommerce_hybrid_scope']);
    }

    public function test_generated_seo_slugs_are_short_and_follow_the_editorial_subject(): void
    {
        $generator = new SeoSlugGenerator;
        $project = new SeoProject(['name' => 'HubSpot']);
        $cases = [
            ['Optimiser la gestion de la relation client dans le retail', ['topic' => 'gestion-relation-client', 'angle' => 'unifier-donnees', 'audience' => 'retail'], 'hubspot-crm-retail'],
            ['CRM et facturation : gagner en efficacité', ['topic' => 'crm-facturation', 'angle' => 'automatiser-devis-factures'], 'hubspot-automatiser-devis-factures'],
            ['Les avantages du CRM en mode SaaS pour les PME', ['topic' => 'saas-crm', 'audience' => 'pme'], 'avantages-crm-saas-pme'],
            ['Gestion du CRM : les bonnes pratiques pour la qualité des données', ['topic' => 'data-management', 'angle' => 'gouvernance-donnees'], 'hubspot-qualite-donnees-crm'],
            ['Choisir le bon CRM pour votre activité de conseil', ['topic' => 'services-b2b', 'angle' => 'cycles-vente-longs', 'audience' => 'consultants'], 'hubspot-crm-activite-conseil'],
            ['Synchroniser la facturation avec le CRM', ['topic' => 'gestion-administrative', 'angle' => 'synchroniser-factures'], 'hubspot-synchroniser-crm-facturation'],
        ];

        foreach ($cases as [$title, $blueprint, $expected]) {
            $slug = $generator->generate($project, $blueprint, $title);
            $this->assertSame($expected, $slug);
            $this->assertLessThanOrEqual(5, count(explode('-', $slug)));
        }
    }

    public function test_generated_content_keeps_only_the_requested_structure(): void
    {
        $sanitizer = new GeneratedContentSanitizer;
        $body = <<<'MARKDOWN'
## Réponse courte et définition
Réponse utile.

## FAQ
### Question une ?
Réponse.

## Anatomie d'un second article
Cette section parasite recommence le plan.

## Conclusion et prochaines étapes
Conclusion utile.

*Transparence affiliée* : cette mention serait dupliquée par le CMS.
MARKDOWN;

        $cleaned = $sanitizer->keepRequestedSections($body, [
            'Réponse courte et définition',
            'FAQ',
            'Conclusion et prochaines étapes',
        ]);

        $this->assertStringNotContainsString('second article', $cleaned);
        $this->assertStringNotContainsString('Transparence affiliée', $cleaned);
        $this->assertSame(3, preg_match_all('/^##\s+/mu', $cleaned));
    }

    public function test_distinct_invoice_angles_receive_distinct_descriptive_slugs(): void
    {
        $generator = new SeoSlugGenerator;
        $project = new SeoProject(['name' => 'Indy']);
        $blueprint = ['topic' => 'crm-facturation', 'angle' => 'facturation', 'audience' => 'pme'];

        $acquitted = $generator->generate($project, $blueprint, 'Facture acquittée : confirmer un paiement reçu');
        $deposit = $generator->generate($project, $blueprint, 'Facture d’acompte : sécuriser votre trésorerie');
        $comparison = $generator->generate($project, $blueprint, 'Indy vs Odoo : quel logiciel de facturation choisir ?');

        $this->assertSame('facture-acquittee-confirmer-paiement-recu', $acquitted);
        $this->assertSame('facture-dacompte-securiser-tresorerie', $deposit);
        $this->assertSame('indy-odoo-logiciel-facturation-choisir', $comparison);
        $this->assertCount(3, array_unique([$acquitted, $deposit, $comparison]));
    }

    public function test_progress_only_counts_articles_actually_delivered(): void
    {
        $run = new ContentRun([
            'requested_count' => 5,
            'completed_count' => 0,
            'failed_count' => 5,
        ]);

        $this->assertSame(0, $run->progress_percentage);

        $run->completed_count = 2;
        $this->assertSame(40, $run->progress_percentage);
    }

    public function test_seo_audit_rejects_a_second_plan_and_repeated_scenarios(): void
    {
        $structure = new SeoContentStructure;
        $body = collect(range(1, 12))->map(fn (int $index) => "## Section {$index}\nContenu utile.")->implode("\n\n")
            ."\n\n## FAQ\n### Question ?\nRéponse."
            ."\n\n## Conclusion\nConclusion."
            ."\n\nHypothèse de simulation : 10 personnes.\n\nScénario illustratif : 20 personnes.";

        $audit = $structure->audit($body, 'informational', null, true, 'HubSpot', true);

        $this->assertFalse($audit['checks']['bounded_h2']);
        $this->assertFalse($audit['checks']['single_labeled_scenario']);
    }

    public function test_generated_content_keeps_one_table_one_scenario_and_one_pricing_note(): void
    {
        $sanitizer = new GeneratedContentSanitizer;
        $body = <<<'MARKDOWN'
## Exemples et scénarios concrets
### Scénario illustratif
Une équipe de 10 personnes traite 200 leads.

| Solution | Coût | Limites |
|---|---|---|
| A | Par siège | Complexité |
| B | Par volume | Limites |

*Note sur les coûts : une première note courte.*

## Tableau récapitulatif ou matrice de décision
| Offre | Coût | Limites |
|---|---|---|
| Starter | Par siège | Automatisation limitée |
| Pro | Par siège | Paramétrage avancé |

Le modèle de tarification complet repose sur une facturation par siège et doit être vérifié sur la grille officielle.

### Hypothèse de simulation
Une deuxième simulation inutile concerne 20 personnes.
MARKDOWN;

        $cleaned = $sanitizer->sanitize($body);

        $this->assertSame(1, preg_match_all('/^\|[^\r\n]*\|\R^\|[\s:|\-]+\|/mu', $cleaned));
        $this->assertSame(1, preg_match_all('/hypothèse de simulation|scénario illustratif/iu', $cleaned));
        $this->assertStringNotContainsString('première note courte', $cleaned);
        $this->assertStringContainsString('modèle de tarification complet', $cleaned);
    }

    public function test_two_scenario_labels_on_the_same_heading_count_as_one_scenario(): void
    {
        $audit = (new SeoContentStructure)->audit(
            "## Exemples et scénarios concrets\n\n### Scénario illustratif : Hypothèse de simulation\n\nUne équipe de 10 personnes traite 200 leads.",
            'informational',
            null,
            true,
        );

        $this->assertTrue($audit['checks']['single_labeled_scenario']);
    }

    public function test_a_numbered_title_creates_and_enforces_the_exact_listicle_section(): void
    {
        $structure = new SeoContentStructure;
        $title = 'Les 10 fonctionnalités essentielles d’un CRM pour les TPE et comment HubSpot y répond';
        $sections = $structure->sectionsFor('informational', $title);

        $this->assertSame('Les 10 fonctionnalités essentielles d’un CRM', $sections[2]);

        $items = collect(range(1, 10))
            ->map(fn (int $number): string => "### {$number}. Fonctionnalité {$number}\n\nExplication concrète et utile.")
            ->implode("\n\n");
        $complete = "## {$sections[2]}\n\n{$items}";
        $incomplete = preg_replace('/### 10\..*\z/su', '', $complete) ?: $complete;

        $this->assertTrue($structure->hasPromisedList($complete, $title));
        $this->assertFalse($structure->hasPromisedList($incomplete, $title));
    }

    public function test_dense_prose_and_redundant_list_introductions_are_cleaned_for_mobile_reading(): void
    {
        $dense = implode(' ', array_fill(0, 6, 'Cette phrase apporte une explication précise avec plusieurs informations concrètes pour les petites entreprises.'));
        $body = "## Pourquoi ce sujet est important\n\n{$dense}\n\n## Checklist opérationnelle\n\nPremière transition utile. Deuxième transition utile. Ce troisième paragraphe répète inutilement toute la liste.\n\n- Définir les objectifs\n- Former les utilisateurs";

        $cleaned = (new GeneratedContentSanitizer)->sanitize($body);

        $this->assertStringNotContainsString('troisième paragraphe', $cleaned);
        $this->assertStringContainsString('- Définir les objectifs', $cleaned);
        foreach (preg_split('/\n{2,}/u', $cleaned) ?: [] as $block) {
            if (preg_match('/^(?:#|-)/u', trim($block)) === 1) {
                continue;
            }
            preg_match_all('/[\p{L}\p{N}]+/u', $block, $words);
            $this->assertLessThanOrEqual(90, count($words[0] ?? []));
        }
    }

    public function test_definition_tofu_template_keeps_the_checklist_and_removes_the_duplicate_method(): void
    {
        $structure = new SeoContentStructure;
        $sections = $structure->sectionsFor(
            'informational',
            "Qu'est-ce qu'un CRM ? Définition et notions essentielles",
            'informational',
            'awareness',
            'définition crm',
        );

        $this->assertCount(9, $sections);
        $this->assertContains('Checklist opérationnelle', $sections);
        $this->assertNotContains('Méthode détaillée étape par étape', $sections);
    }

    public function test_a_started_chronological_sequence_must_include_a_second_and_final_step(): void
    {
        $structure = new SeoContentStructure;
        $abandoned = "## Checklist opérationnelle\n\n### Étape 1 : définir le besoin\n\nLa première étape consiste à cadrer le projet.";
        $complete = $abandoned."\n\n### Étape 2 : préparer les données\n\nNettoyez les données.\n\n### Étape finale : valider le déploiement\n\nContrôlez le résultat.";

        $this->assertFalse($structure->hasCompleteChronologicalSequences($abandoned));
        $this->assertTrue($structure->hasCompleteChronologicalSequences($complete));
    }

    public function test_incomplete_gemini_output_is_recoverable_but_an_unrelated_error_is_not(): void
    {
        $method = new \ReflectionMethod(Automation::class, 'isRecoverableGenerationError');
        $component = new Automation;

        $this->assertTrue($method->invoke($component, new \RuntimeException('Réponse Gemini incomplète : partie trop courte.')));
        $this->assertTrue($method->invoke($component, new \RuntimeException('Gemini n’a pas retourné un contenu structuré exploitable.')));
        $this->assertTrue($method->invoke($component, new \RuntimeException('Gemini HTTP 503 : high demand.')));
        $this->assertFalse($method->invoke($component, new \RuntimeException('Invalid request payload.')));
        $this->assertTrue($method->invoke($component, new \RuntimeException('Connection timed out.')));
    }

    public function test_checklist_and_resource_items_are_never_truncated_by_post_processing(): void
    {
        $body = <<<'MARKDOWN'
## Checklist opérationnelle

Une transition utile.

- **Cohérence des données :** Examiner la qualité des données importées et corriger tous les doublons avant la migration en production. Cette seconde phrase répète inutilement le conseil.

## Outils et ressources utiles

- **Documentation :** Consulter la documentation officielle pour préparer rapidement la configuration et accompagner les utilisateurs pendant leur prise en main quotidienne et durable.
MARKDOWN;

        $cleaned = (new GeneratedContentSanitizer)->sanitize($body);

        $this->assertStringContainsString('Cette seconde phrase répète inutilement le conseil.', $cleaned);
        $this->assertStringContainsString('pendant leur prise en main quotidienne et durable.', $cleaned);
    }

    public function test_rejected_tools_prompt_explicitly_forbids_every_tool_already_selected(): void
    {
        $structurePrompt = (new SeoContentStructure)->prompt('best_tools');
        $this->assertStringContainsString('EXCLUSION MUTUELLE DES ENTITÉS', $structurePrompt);

        $generator = (new \ReflectionClass(GeminiContentGenerator::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(GeminiContentGenerator::class, 'mutualExclusionDirective');
        $directive = $method->invoke($generator, ['Outils écartés ou informations insuffisantes'], [<<<'MARKDOWN'
## Sélection rapide des meilleures solutions

Notre Top 3 retient HubSpot, Zoho CRM et Salesforce pour leurs profils distincts.

## Analyse détaillée de chaque outil retenu

HubSpot, Zoho CRM et Salesforce sont analysés avec leurs limites.
MARKDOWN]);

        $this->assertStringContainsString('LISTE INTERDITE : HubSpot, Salesforce, Zoho CRM', $directive);
        $this->assertStringContainsString('Ne classe aucun de ces outils', $directive);
    }

    public function test_vertical_crm_audit_requires_a_concrete_custom_object_model(): void
    {
        $structure = new SeoContentStructure;
        $withoutModel = $structure->audit('Un CRM centralise les contacts.', 'informational', 'crm assurance', true, 'HubSpot', true, 'CRM pour assurance');
        $withModel = $structure->audit('Utilisez des objets personnalisés pour séparer le Contact des objets Contrat et Sinistre.', 'informational', 'crm assurance', true, 'HubSpot', true, 'CRM pour assurance');

        $this->assertFalse($withoutModel['checks']['vertical_custom_objects']);
        $this->assertTrue($withModel['checks']['vertical_custom_objects']);
    }

    public function test_btp_software_audit_rejects_generalist_only_scope_and_unproved_chantier_claims(): void
    {
        $structure = new SeoContentStructure;
        $body = <<<'MARKDOWN'
Réponse courte : un logiciel facturation BTP doit couvrir les devis, les factures et les contraintes chantier avec des preuves.

| Outil | Type | Gestion chantier intégrée | Limites |
|---|---|---|---|
| Indy | Généraliste | Oui | Frais cachés possibles |
| Abby | Généraliste | Oui | À vérifier |
| Freebe | Généraliste | Oui | À vérifier |
| Pennylane | Généraliste | Oui | À vérifier |

Les besoins BTP incluent acompte, situation de travaux, retenue de garantie, TVA bâtiment et suivi chantier.
Cette page promet une economie annuelle de 6500 euros sans avertissement de simulation.
MARKDOWN;

        $audit = $structure->audit($body, 'best_tools', 'logiciel facturation BTP', true, 'Indy', true, 'Meilleur logiciel facturation BTP');

        $this->assertFalse($audit['checks']['safe_cost_language']);
        $this->assertFalse($audit['checks']['btp_specialized_scope']);
        $this->assertFalse($audit['checks']['btp_no_unproved_chantier_claims']);
        $this->assertFalse($audit['checks']['btp_simulation_disclaimer']);
    }

    public function test_btp_software_audit_accepts_specialists_and_prudent_generalist_labels(): void
    {
        $structure = new SeoContentStructure;
        $body = <<<'MARKDOWN'
Réponse courte : un logiciel facturation BTP doit être choisi selon les besoins chantier et les preuves disponibles.

| Outil | Type | Idéal pour | Fonctions BTP clés | Limites |
|---|---|---|---|---|
| Obat | Spécialisé BTP | Artisans | Devis, situations de travaux, suivi chantier | Prix à vérifier |
| Tolteck | Spécialisé BTP | Artisans bâtiment | Ouvrages, matériaux, factures | Moins complet qu'un ERP |
| ProGBat | Spécialisé BTP | TPE bâtiment | Acompte, TVA bâtiment, devis | Limites à vérifier |
| EBP Bâtiment | Spécialisé BTP | PME structurées | Retenue de garantie, métrés, rentabilité chantier | Plus lourd |
| Indy | Généraliste adaptable | Indépendants | Facturation simple, pas un outil chantier dédié | Fonctions BTP avancées à vérifier |
| Abby | Généraliste adaptable | Micro-entrepreneurs | Adaptable, mais pas spécialisé BTP | Fonctions chantier à vérifier |

Simulation fictive à visée pédagogique : les chiffres suivants ne sont pas une promesse de résultat. Une entreprise peut simuler une économie annuelle de 6500 euros pour comparer le coût total.
MARKDOWN;

        $audit = $structure->audit($body, 'best_tools', 'logiciel devis facture batiment', true, 'Indy', true, 'Meilleur logiciel devis facture batiment');

        $this->assertTrue($audit['checks']['safe_cost_language']);
        $this->assertTrue($audit['checks']['btp_specialized_scope']);
        $this->assertTrue($audit['checks']['btp_generalists_labeled_adaptable']);
        $this->assertTrue($audit['checks']['btp_no_unproved_chantier_claims']);
        $this->assertTrue($audit['checks']['btp_trade_criteria']);
        $this->assertTrue($audit['checks']['btp_simulation_disclaimer']);
    }

    public function test_btp_generation_prompt_adds_vertical_guardrails_only_for_btp_queries(): void
    {
        $structure = new SeoContentStructure;

        $this->assertStringContainsString('RÈGLE VERTICALE BTP', $structure->prompt('best_tools', 'Meilleur logiciel facturation BTP', 'logiciel facturation BTP'));
        $this->assertStringNotContainsString('RÈGLE VERTICALE BTP', $structure->prompt('best_tools', 'Meilleur logiciel facturation gratuit', 'logiciel facturation gratuit'));
    }

    public function test_btp_generated_draft_is_rejected_before_article_creation_when_scope_is_generalist_only(): void
    {
        $generator = new GeminiContentGenerator(
            new SeoContentStructure,
            new EditorialDuplicateDetector(new TopicNormalizer, new SeoSlugGenerator),
            new GeneratedContentSanitizer,
            new CompetitorCatalog,
        );
        $method = new \ReflectionMethod(GeminiContentGenerator::class, 'assertBtpStrategicFit');
        $project = new SeoProject(['name' => 'Indy']);
        $keyword = new Keyword(['keyword' => 'logiciel facturation BTP']);
        $data = [
            'title' => 'Meilleur logiciel facturation BTP',
            'brief_title' => 'Meilleur logiciel facturation BTP',
            'compared_products' => ['Indy', 'Abby', 'Freebe', 'Pennylane'],
            'body' => 'Indy propose une gestion de chantier intégrée. Abby et Freebe sont aussi adaptés. Les frais cachés doivent être surveillés.',
        ];

        $this->expectException(PlannedContentRejectedException::class);

        $method->invoke($generator, $data, 'best_tools', $project, $keyword);
    }

    public function test_footer_bloat_is_removed_from_the_conclusion(): void
    {
        $body = <<<'MARKDOWN'
## Conclusion et prochaines étapes

Premier paragraphe de synthèse.

Deuxième paragraphe avec le conseil final.

### Prochaines étapes pour réussir

- Refaire toute la checklist.

### Anticiper les évolutions

Un développement supplémentaire inutile.
MARKDOWN;

        $cleaned = (new GeneratedContentSanitizer)->sanitize($body);
        $audit = (new SeoContentStructure)->audit($cleaned, 'informational', null, false, null, true);

        $this->assertStringNotContainsString('### Prochaines étapes', $cleaned);
        $this->assertStringNotContainsString('Anticiper les évolutions', $cleaned);
        $this->assertTrue($audit['checks']['concise_conclusion']);
    }

    public function test_generic_faq_questions_cannot_repeat_a_semantic_neighbor(): void
    {
        $generator = new GeminiContentGenerator(
            new SeoContentStructure,
            new EditorialDuplicateDetector(new TopicNormalizer, new SeoSlugGenerator),
            new GeneratedContentSanitizer,
            new CompetitorCatalog,
        );
        $method = new \ReflectionMethod($generator, 'faqOverlapsExisting');
        $body = "## FAQ\n\n### Comment préparer la migration des données vers HubSpot ?\n\nRéponse.";

        $this->assertTrue($method->invoke($generator, $body, ["Quels sont les défis d'une migration CRM ?"]));
        $this->assertFalse($method->invoke($generator, $body, ['Peut-on connecter un comparateur via API ?']));
    }

    public function test_a_short_two_paragraph_conclusion_is_valid_as_a_standalone_final_part(): void
    {
        $generator = new GeminiContentGenerator(
            new SeoContentStructure,
            new EditorialDuplicateDetector(new TopicNormalizer, new SeoSlugGenerator),
            new GeneratedContentSanitizer,
            new CompetitorCatalog,
        );
        $method = new \ReflectionMethod($generator, 'assertGeneratedPart');
        $paragraph = 'Cette conclusion synthétise le choix technique et rappelle les limites vérifiées de la solution pour le profil étudié. ';
        $body = "## Conclusion et méthodologie\n\n".str_repeat($paragraph, 3)."\n\n".str_repeat($paragraph, 3);

        $method->invoke($generator, $body, ['Conclusion et méthodologie'], 80, false, false, false, null, []);

        $this->addToAssertionCount(1);
    }
}
