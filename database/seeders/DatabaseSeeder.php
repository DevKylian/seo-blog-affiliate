<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\EvidenceChunk;
use App\Models\Keyword;
use App\Models\Plan;
use App\Models\PriceSnapshot;
use App\Models\SeoProject;
use App\Models\SourcePage;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrateur',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_admin' => true,
            ],
        );

        User::factory(12)->create();

        $project = SeoProject::query()->create([
            'name' => 'MailPilot Démo',
            'slug' => 'mailpilot-demo',
            'website_url' => 'https://example.com/mailpilot',
            'pricing_url' => 'https://example.com/mailpilot/pricing',
            'affiliate_url' => 'https://example.com/mailpilot?ref=blogseo',
            'country' => 'FR',
            'currency' => 'EUR',
            'crawl_status' => 'completed',
            'last_crawled_at' => now()->subDay(),
            'description' => 'Projet fictif fourni pour découvrir le workflow du CMS sans présenter ces données comme réelles.',
            'positioning' => 'Plateforme d’e-mailing fictive pour PME — données de démonstration.',
            'features' => ['Éditeur de campagnes', 'Segmentation', 'Automatisations'],
            'strengths' => ['Interface simple', 'Scénarios automatisés'],
            'limitations' => ['Données fictives à remplacer avant publication réelle'],
            'best_for' => ['PME', 'Associations'],
        ]);

        $source = SourcePage::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'pricing',
            'url' => 'https://example.com/mailpilot/pricing',
            'title' => 'MailPilot Tarifs — source de démonstration',
            'excerpt' => 'Offre Pro fictive à 29 EUR par mois avec 14 jours d’essai.',
            'content' => 'Données fictives destinées à démontrer le fonctionnement de la base de preuves.',
            'content_hash' => hash('sha256', 'demo'),
            'http_status' => 200,
            'status' => 'verified',
            'extraction_method' => 'demo_seed',
            'confidence_score' => 1,
            'verified_at' => now()->subDay(),
        ]);
        EvidenceChunk::query()->create([
            'source_page_id' => $source->id,
            'category' => 'pricing',
            'value' => 'Offre Pro fictive à 29 EUR par mois avec 14 jours d’essai.',
            'source_excerpt' => 'Offre Pro fictive à 29 EUR par mois avec 14 jours d’essai.',
            'position' => 1,
            'confidence_score' => 1,
            'verified_at' => now()->subDay(),
        ]);
        $plan = Plan::query()->create([
            'seo_project_id' => $project->id,
            'source_page_id' => $source->id,
            'name' => 'Pro (démo)',
            'position' => 1,
            'raw_price' => 'Donnée de démonstration : 29 EUR/mois.',
            'currency' => 'EUR',
            'monthly_price' => 29,
            'billing_period' => 'month',
            'price_unit' => 'per_workspace',
            'free_trial_days' => 14,
            'confidence_score' => 1,
            'verified_at' => now()->subDay(),
        ]);
        PriceSnapshot::query()->create(['plan_id' => $plan->id, 'monthly_price' => 29, 'currency' => 'EUR', 'raw_price' => $plan->raw_price, 'verified_at' => now()->subDay()]);

        $keyword = Keyword::query()->create([
            'seo_project_id' => $project->id,
            'keyword' => 'avis mailpilot démo',
            'search_volume' => 120,
            'keyword_difficulty' => 24,
            'intent' => 'Commerciale',
            'cpc' => 2.40,
            'country' => 'FR',
            'cluster' => 'Avis',
            'opportunity_score' => 78,
        ]);
        $category = Category::query()->create(['name' => 'Outils emailing', 'slug' => 'outils-emailing', 'description' => 'Analyses et comparatifs de plateformes emailing.']);
        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'keyword_id' => $keyword->id,
            'author_id' => User::query()->where('email', 'admin@example.com')->value('id'),
            'type' => 'tool_review',
            'title' => 'MailPilot Démo : exemple de fiche affiliée sourcée',
            'slug' => 'mailpilot-demo-exemple-fiche-sourcee',
            'excerpt' => 'Un article de démonstration montrant les blocs dynamiques et les preuves collectées.',
            'body' => "## Une démonstration du système\n\nMailPilot est un produit fictif utilisé pour présenter le CMS. Le prix affiché provient du bloc tarifaire dynamique.\n\n## Verdict\n\nRemplacez ce projet par un véritable outil et collectez ses pages officielles avant toute utilisation éditoriale.",
            'content_blocks' => [
                ['type' => 'markdown', 'content' => "## Une démonstration du système\n\nMailPilot est un produit fictif utilisé pour présenter le CMS. Le prix affiché provient du bloc tarifaire dynamique.\n\n## Verdict\n\nRemplacez ce projet par un véritable outil et collectez ses pages officielles avant toute utilisation éditoriale."],
                ['type' => 'pricing_table', 'project_id' => $project->id, 'display' => 'monthly_and_yearly'],
                ['type' => 'affiliate_disclosure'],
                ['type' => 'last_verified', 'date' => now()->subDay()->toDateString()],
            ],
            'meta_title' => 'MailPilot Démo : fiche affiliée sourcée',
            'meta_description' => 'Exemple de contenu en blocs avec tarif dynamique, preuves et transparence affiliée.',
            'primary_keyword' => $keyword->keyword,
            'search_intent' => 'Commerciale',
            'status' => 'published',
            'source_ids' => [$source->id],
            'quality_checks' => ['has_sources' => true, 'affiliate_disclosure' => true, 'human_review_required' => true],
            'generated_by' => 'demo_seed',
            'verified_at' => now()->subDay(),
            'published_at' => now()->subHours(3),
        ]);
        $article->categories()->attach($category);
        $article->sources()->attach($source, ['citation_label' => 'S1']);
        $article->tools()->attach($project, ['role' => 'featured']);
    }
}
