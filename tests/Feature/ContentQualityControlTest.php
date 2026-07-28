<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\EvidenceChunk;
use App\Models\Plan;
use App\Models\SeoProject;
use App\Models\SourcePage;
use App\Models\User;
use App\Services\ContentClaimService;
use App\Services\ContentRefreshPlanner;
use App\Services\PrePublishAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ContentQualityControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_service_creates_price_and_free_plan_claims_from_verified_sources(): void
    {
        $project = $this->project();
        $source = $this->source($project, 'pricing', 'https://www.indy.fr/tarifs');
        EvidenceChunk::query()->create([
            'source_page_id' => $source->id,
            'category' => 'pricing',
            'value' => 'Offre Essentiel : 0 € / mois avec devis et factures illimités.',
            'source_excerpt' => 'Offre Essentiel : 0 € / mois avec devis et factures illimités.',
            'confidence_score' => 0.82,
            'verified_at' => now(),
        ]);
        Plan::query()->create([
            'seo_project_id' => $project->id,
            'source_page_id' => $source->id,
            'name' => 'Essentiel',
            'raw_price' => '0 € / mois',
            'currency' => 'EUR',
            'monthly_price' => 0,
            'features' => ['Devis et factures illimités'],
            'confidence_score' => 0.86,
            'verified_at' => now(),
        ]);

        $claims = app(ContentClaimService::class)->syncProject($project);

        $this->assertTrue($claims->contains('claim_type', 'price'));
        $this->assertTrue($claims->contains('claim_type', 'free_plan'));
        $this->assertDatabaseHas('content_claims', [
            'seo_project_id' => $project->id,
            'claim_type' => 'free_plan',
            'status' => 'verified',
        ]);
    }

    public function test_pre_publish_audit_blocks_unknown_or_fictional_competitors(): void
    {
        $project = $this->project();
        $source = $this->source($project, 'other', 'https://www.indy.fr/facturation');
        EvidenceChunk::query()->create([
            'source_page_id' => $source->id,
            'category' => 'feature',
            'value' => 'Indy propose des devis et factures pour indépendants.',
            'source_excerpt' => 'Indy propose des devis et factures pour indépendants.',
            'confidence_score' => 0.80,
            'verified_at' => now(),
        ]);
        $article = $this->article($project, [
            'type' => 'comparison',
            'title' => 'Indy vs InvoiceFlow Max : comparatif 2026',
            'body' => $this->body('InvoiceFlow Max promet une automatisation avancée sans source officielle.'),
        ]);
        $article->sources()->sync([$source->id]);

        $audit = app(PrePublishAuditService::class)->audit($article);

        $this->assertSame('blocked', $audit->status);
        $this->assertTrue(collect($audit->blocking_reasons)->contains(fn (string $reason): bool => str_contains($reason, 'InvoiceFlow')));
        $this->assertSame('blocked', $article->fresh()->prepublish_status);
    }

    public function test_pre_publish_audit_detects_source_markers_inside_public_blocks(): void
    {
        $project = $this->project();
        $source = $this->source($project, 'other', 'https://www.indy.fr/facturation');
        $article = $this->article($project, [
            'body' => $this->body('Le body est propre et ne contient pas de marqueur visible.'),
            'content_blocks' => [
                ['type' => 'markdown', 'content' => $this->body('Une preuve visible reste dans le rendu public [S2, S5].')],
            ],
        ]);
        $article->sources()->sync([$source->id]);

        $audit = app(PrePublishAuditService::class)->audit($article);

        $this->assertSame('blocked', $audit->status);
        $this->assertTrue(collect($audit->blocking_reasons)->contains(fn (string $reason): bool => str_contains($reason, 'marqueurs de source')));
    }


    public function test_refresh_planner_queues_stale_pricing_sources(): void
    {
        $project = $this->project();
        $source = $this->source($project, 'pricing', 'https://www.indy.fr/tarifs', now()->subDays(20));
        EvidenceChunk::query()->create([
            'source_page_id' => $source->id,
            'category' => 'pricing',
            'value' => 'Offre Plus : dès 9 € / mois.',
            'source_excerpt' => 'Offre Plus : dès 9 € / mois.',
            'confidence_score' => 0.80,
            'verified_at' => now()->subDays(20),
        ]);

        $result = app(ContentRefreshPlanner::class)->plan($project);

        $this->assertGreaterThanOrEqual(1, $result['created']);
        $this->assertDatabaseHas('content_refresh_tasks', [
            'seo_project_id' => $project->id,
            'source_page_id' => $source->id,
            'reason' => 'pricing_source_stale',
            'status' => 'queued',
        ]);
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
            'competitors' => ['Abby', 'Freebe', 'Pennylane'],
        ]);
    }

    private function source(SeoProject $project, string $type, string $url, mixed $verifiedAt = null): SourcePage
    {
        return SourcePage::query()->create([
            'seo_project_id' => $project->id,
            'type' => $type,
            'url' => $url,
            'title' => 'Source officielle',
            'content' => 'Contenu officiel vérifié.',
            'status' => 'verified',
            'confidence_score' => 0.82,
            'verified_at' => $verifiedAt ?: now(),
        ]);
    }

    private function article(SeoProject $project, array $overrides = []): Article
    {
        return Article::query()->create([
            'seo_project_id' => $project->id,
            'type' => 'informational',
            'title' => 'Logiciel de facturation : guide pratique',
            'slug' => 'article-'.uniqid(),
            'status' => 'review',
            'primary_keyword' => 'logiciel de facturation',
            'search_intent' => 'commercial',
            'body' => $this->body('Indy aide les indépendants à mieux gérer leurs devis, factures, paiements et relances.'),
            'verified_at' => now(),
            ...$overrides,
        ]);
    }

    private function body(string $extra): string
    {
        return <<<MD
Réponse courte : ce guide explique comment choisir un logiciel de facturation avec des critères vérifiables.

## Réponse courte et définition

{$extra}

## Pourquoi ce sujet est important

La facturation demande des mentions obligatoires, une numérotation fiable, une gestion de TVA claire et un suivi des paiements.

## Méthode détaillée étape par étape

Étape 1 : listez vos besoins de devis, factures et relances.

Étape 2 : vérifiez les prix, limites, exports et intégrations disponibles.

Étape finale : choisissez l’outil qui couvre vos contraintes sans inventer de promesse.

## Exemples et scénarios concrets

Scénario illustratif : une micro-entreprise compare le prix, la facturation électronique et le livre des recettes.

## Tableau récapitulatif ou matrice de décision

| Critère | Indy | Point de vigilance |
|---|---|---|
| Factures | À vérifier selon source | Vérifier les conditions officielles |
| Relances | Disponible selon source | Vérifier les limites du plan |

## Erreurs fréquentes et comment les éviter

- Ne publiez pas un prix sans source officielle récente.
- Ne comparez pas un concurrent sans preuve tarifaire.

## Checklist opérationnelle

- Vérifiez les tarifs sur les sites officiels.
- Contrôlez les limites avant de recommander un outil.

## Outils et ressources utiles

- Utilisez une source officielle pour les prix.
- Ajoutez un lien interne vers l’article pilier.

## FAQ

### Quel logiciel choisir ?

Le meilleur choix dépend du statut, du volume de factures et des besoins comptables.

### Un plan gratuit suffit-il ?

Il peut suffire si les limites correspondent à votre usage réel.

### Les tarifs changent-ils ?

Oui, les tarifs doivent être revérifiés régulièrement.

### Faut-il comparer les concurrents ?

Oui, mais seulement avec des informations officielles et récentes.

### La facturation électronique est-elle importante ?

Oui, elle impose de vérifier les sources réglementaires.

## Conclusion et prochaines étapes

Vérifiez les sources officielles, comparez les limites et gardez une trace de la date de vérification.
MD;
    }
}
