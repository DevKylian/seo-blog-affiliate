<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleAudit;
use App\Models\ContentClaim;
use App\Models\SeoProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class PrePublishAuditService
{
    public function __construct(
        private readonly SeoContentStructure $structures,
        private readonly CompetitorCatalog $competitors,
        private readonly GeneratedContentSanitizer $sanitizer,
        private readonly ContentClaimService $claims,
    ) {}

    /**
     * @param  array{auto_publish?: bool}  $context
     */
    public function audit(Article $article, array $context = []): ArticleAudit
    {
        $article->loadMissing([
            'project.sourcePages.evidenceChunks',
            'project.pricingPlans',
            'sources.evidenceChunks',
            'keyword',
            'contentCluster',
            'tools',
            'internalLinks',
        ]);

        $project = $article->project;
        $type = $this->safeType((string) $article->type);
        $body = $this->sanitizer->stripSourceMarkers((string) $article->body);
        $rawBody = (string) $article->body;
        $rawRenderedContent = $rawBody.' '.collect($article->content_blocks ?: [])
            ->map(fn ($block): string => is_array($block) ? (string) ($block['content'] ?? '') : '')
            ->implode(' ');
        $contextText = implode(' ', array_filter([
            $article->title,
            $article->primary_keyword,
            $article->keyword?->keyword,
            $article->content_angle,
            $article->editorial_audience,
            mb_substr($body, 0, 1200),
        ]));
        $fullRiskText = implode(' ', array_filter([
            $article->title,
            $article->primary_keyword,
            $article->keyword?->keyword,
            $article->content_angle,
            $article->editorial_audience,
            $body,
            $rawRenderedContent,
        ]));
        $strictPricingProof = in_array($type, ['pricing', 'comparison'], true);
        $commercialReview = in_array($type, ['pricing', 'comparison', 'best_tools', 'alternatives', 'tool_review'], true);
        $regulatoryRisk = $this->isRegulatoryRisk($fullRiskText);

        $sourceCount = $article->sources->where('status', 'verified')->count();
        if ($sourceCount === 0 && is_array($article->source_ids)) {
            $sourceCount = count(array_filter($article->source_ids));
        }

        $syncedClaims = $project ? $this->claims->syncProject($project) : collect();
        $articleClaims = $article->exists ? $this->claims->claimsForArticle((int) $article->id) : collect();
        /** @var Collection<int, ContentClaim> $claimSet */
        $claimSet = $articleClaims->isNotEmpty() ? $articleClaims : $syncedClaims;

        $blockers = [];
        $recommendations = [];
        $checks = [];
        $categoryScores = [];

        $structureAudit = $this->structures->audit(
            $body,
            $type,
            $article->primary_keyword ?: $article->keyword?->keyword,
            $sourceCount > 0,
            $project?->name,
            true,
            (string) $article->title,
        );
        $structureChecks = collect($structureAudit['checks'] ?? []);
        $failedStructure = $structureChecks->filter(fn ($passed): bool => $passed !== true);
        $categoryScores['structure'] = max(0, 100 - ($failedStructure->count() * 4.5));
        if ($failedStructure->has('sources_attached')) {
            $recommendations[] = 'Aucune source vérifiée n’est attachée : ajoutez des preuves avant diffusion large.';
        }
        if ($failedStructure->has('useful_decision_table')) {
            $recommendations[] = 'Le tableau doit vraiment aider à décider : moins de cellules génériques, plus de critères différenciants.';
        }
        if ($failedStructure->has('fresh_verification_date')) {
            $blockers[] = 'La date de vérification affichée n’est pas fraîche ou n’est pas cohérente avec l’année système.';
        }

        $checks['structure'] = $structureAudit['checks'] ?? [];
        $checks['word_count'] = $structureAudit['word_count'] ?? 0;
        $checks['h2_count'] = $structureAudit['h2_count'] ?? 0;
        $checks['source_count'] = $sourceCount;
        $checks['claim_count'] = $claimSet->count();

        $verifiedClaims = $claimSet->where('status', 'verified');
        $priceClaims = $verifiedClaims->whereIn('claim_type', ['price', 'free_plan', 'trial']);
        $legalClaims = $verifiedClaims->where('claim_type', 'legal');
        $limitationClaims = $verifiedClaims->where('claim_type', 'limitation');
        $staleClaims = $verifiedClaims
            ->filter(fn (ContentClaim $claim): bool => $claim->next_refresh_at !== null && $claim->next_refresh_at->isPast());
        $stalePricingSources = $project
            ? $project->sourcePages
                ->where('type', 'pricing')
                ->where('status', 'verified')
                ->filter(fn ($source): bool => ! $source->verified_at || $source->verified_at->lt(now()->subDays(14)))
            : collect();

        $sourceScore = 100;
        if ($sourceCount < 2) {
            $sourceScore -= 25;
            $recommendations[] = 'Ajoutez au moins deux sources vérifiées pour éviter un article mono-source.';
        }
        if ($claimSet->count() < 6) {
            $sourceScore -= 20;
            $recommendations[] = 'La base de claims est trop légère : ajoutez plus de preuves exploitables avant publication.';
        }
        if ($stalePricingSources->isNotEmpty()) {
            $sourceScore -= 25;
            $blockers[] = 'Des sources tarifaires ont plus de 14 jours : relancez la collecte avant publication.';
        }
        if ($staleClaims->isNotEmpty()) {
            $sourceScore -= 15;
            $recommendations[] = 'Certains claims doivent être rafraîchis avant de pousser ce contenu plus loin.';
        }
        $categoryScores['sources'] = max(0, $sourceScore);

        $hasPricingLanguage = $this->hasPricingLanguage($body.' '.$article->title);
        $hasFreePlanLanguage = preg_match('/\b(?:plan|offre|formule)?\s*(?:gratuit|gratuite|0\s*(?:€|eur))\b/iu', $body) === 1;
        $unknownCompetitors = $project ? $this->competitors->unknownCompetitorMentions($project, $article->title.' '.$body) : [];
        $visibleSourceMarkers = $this->sanitizer->hasSourceMarkers($rawRenderedContent);
        $tooManyToVerify = preg_match_all('/(?:à vérifier|a verifier|non vérifié|non verifie)/iu', $body);

        $factualityScore = 100;
        if ($visibleSourceMarkers) {
            $factualityScore -= 30;
            $blockers[] = 'Des marqueurs de source visibles restent dans le contenu.';
        }
        if ($unknownCompetitors !== []) {
            $factualityScore -= 45;
            $blockers[] = 'Concurrent ou entité fictive détectée : '.implode(', ', $unknownCompetitors).'.';
        }
        if ($hasPricingLanguage && $priceClaims->count() === 0) {
            $factualityScore -= 40;
            $recommendations[] = 'Le contenu parle de prix, gratuité ou essai sans claim tarifaire vérifié.';
        }
        if ($hasFreePlanLanguage && $priceClaims->where('claim_type', 'free_plan')->isEmpty()) {
            $factualityScore -= 30;
            $recommendations[] = 'Une offre gratuite est mentionnée sans claim “free_plan” vérifié.';
        }
        if ($limitationClaims->isEmpty() && in_array($type, ['comparison', 'best_tools', 'alternatives', 'tool_review'], true)) {
            $factualityScore -= 10;
            $recommendations[] = 'Ajoutez au moins une limite ou condition prouvée pour éviter le ton promotionnel.';
        }
        if ($tooManyToVerify >= 3) {
            $factualityScore -= 20;
            $blockers[] = 'Trop de cellules “à vérifier” : le contenu n’est pas assez exploitable pour être publié.';
        }
        $categoryScores['factuality'] = max(0, $factualityScore);

        $duplicateScore = (float) ($article->duplicate_score ?? 0);
        $differentiationScore = 100;
        if (in_array($article->duplicate_status, ['potential', 'needs_differentiation'], true) && $duplicateScore >= 70) {
            $differentiationScore -= 45;
            if ($context['auto_publish'] ?? false) {
                $blockers[] = "Cannibalisation probable : similarité éditoriale de {$duplicateScore} % avec un autre article.";
            } else {
                $recommendations[] = "Cannibalisation probable : similarité éditoriale de {$duplicateScore} % avec un autre article.";
            }
        } elseif ($duplicateScore >= 50) {
            $differentiationScore -= 20;
            $recommendations[] = 'Angle proche d’un contenu existant : renforcez la promesse unique avant publication.';
        }
        if ($this->isGenericAngle($article, $body)) {
            $differentiationScore -= 18;
            $recommendations[] = 'L’angle reste trop générique : ajoutez une intention, un profil ou un cas métier plus précis.';
        }
        $categoryScores['differentiation'] = max(0, $differentiationScore);

        $affiliateScore = $this->auditAffiliateBalance($project, $article, $body, $type, $blockers, $recommendations);
        $categoryScores['affiliate_bias'] = $affiliateScore;

        $highRisk = $commercialReview || $regulatoryRisk;
        $complianceScore = 100;
        if ($regulatoryRisk && ! $this->hasOfficialSource($article, $project)) {
            $complianceScore -= 45;
            $recommendations[] = 'Sujet fiscal, légal, comptable ou réglementaire sans source officielle attachée.';
        }
        if ($regulatoryRisk && $legalClaims->isEmpty() && preg_match('/\b(?:obligatoire|légal|legal|loi|urssaf|tva|imp[oô]ts?|dgfip)\b/iu', $body) === 1) {
            $complianceScore -= 25;
            $recommendations[] = 'Le contenu contient une affirmation réglementaire sans claim légal vérifié.';
        }
        if ($this->hasUnlabeledSimulation($body)) {
            $complianceScore -= 20;
            $blockers[] = 'Une simulation chiffrée doit être explicitement indiquée comme fictive ou illustrative.';
        }
        $accentWarnings = $this->missingFrenchAccents($body);
        if ($accentWarnings >= 8) {
            $complianceScore -= 20;
            $blockers[] = 'Le français contient trop de mots non accentués : régénérez ou corrigez avant publication.';
        } elseif ($accentWarnings >= 4) {
            $complianceScore -= 8;
            $recommendations[] = 'Quelques accents français semblent manquer dans le texte.';
        }
        $categoryScores['compliance'] = max(0, $complianceScore);

        $semantic = $this->semanticDensity($article, $body);
        $readabilityScore = min(100, max(35, 45 + ($semantic['covered'] * 8)));
        if ($semantic['covered'] < $semantic['minimum']) {
            $readabilityScore -= 18;
            $recommendations[] = 'Densité sémantique faible : ajoutez plus de vocabulaire métier précis.';
        }
        if (($structureAudit['word_count'] ?? 0) < 900 && $verifiedClaims->count() < 8) {
            $readabilityScore -= 15;
            $recommendations[] = 'Le contenu est court et manque de preuves : enrichissez avec des cas, limites et critères métier.';
        }
        $categoryScores['readability'] = max(0, $readabilityScore);

        $publicationScore = 100;
        if (($context['auto_publish'] ?? false) && $this->needsHumanReview($article, $highRisk)) {
            $publicationScore -= 55;
            $blockers[] = 'Auto-publication interdite pour ce contenu à risque : validation humaine requise.';
        }
        if ($article->canonical_article_id && $article->duplicate_status === 'merged') {
            $publicationScore -= 30;
            $blockers[] = 'Un article canonique est associé : publiez ou retravaillez la version canonique plutôt que ce doublon.';
        } elseif ($article->canonical_article_id && in_array($article->duplicate_status, ['potential', 'needs_differentiation'], true)) {
            $publicationScore -= 18;
            if ($context['auto_publish'] ?? false) {
                $blockers[] = 'Un article canonique est associé : validation humaine requise avant publication.';
            } else {
                $recommendations[] = 'Un article canonique est associé : vérifiez l’angle avant publication.';
            }
        }
        $categoryScores['publication_risk'] = max(0, $publicationScore);

        $checks['high_risk'] = $highRisk;
        $checks['semantic_density'] = $semantic;
        $checks['stale_claims'] = $staleClaims->count();
        $checks['stale_pricing_sources'] = $stalePricingSources->pluck('url')->values()->all();
        $checks['unknown_competitors'] = $unknownCompetitors;
        $checks['price_claim_count'] = $priceClaims->count();
        $checks['legal_claim_count'] = $legalClaims->count();
        $checks['official_source'] = $this->hasOfficialSource($article, $project);

        $blockers = array_values(array_unique(array_filter($blockers)));
        $recommendations = array_values(array_unique(array_filter($recommendations)));
        $score = round(array_sum($categoryScores) / max(1, count($categoryScores)), 2);
        if ($blockers !== []) {
            $score = min($score, 69.0);
        }
        $status = $blockers !== [] ? 'blocked' : ($score >= 82 ? 'passed' : 'needs_review');

        $audit = ArticleAudit::query()->create([
            'article_id' => $article->id,
            'score' => $score,
            'status' => $status,
            'category_scores' => collect($categoryScores)->map(fn ($value) => round((float) $value, 2))->all(),
            'checks' => $checks,
            'blocking_reasons' => $blockers,
            'recommendations' => $recommendations,
            'audited_at' => now(),
        ]);

        $qualityChecks = $article->quality_checks ?? [];
        $qualityChecks['prepublish'] = [
            'audit_id' => $audit->id,
            'status' => $status,
            'score' => $score,
            'audited_at' => $audit->audited_at->toDateTimeString(),
            'blockers' => $blockers,
            'recommendations' => array_slice($recommendations, 0, 8),
        ];

        $article->forceFill([
            'prepublish_status' => $status,
            'prepublish_score' => $score,
            'prepublish_audited_at' => $audit->audited_at,
            'quality_checks' => $qualityChecks,
        ])->save();

        return $audit;
    }

    private function safeType(string $type): string
    {
        return in_array($type, ['tool_review', 'pricing', 'comparison', 'best_tools', 'alternatives', 'informational', 'question'], true)
            ? $type
            : 'informational';
    }

    private function hasPricingLanguage(string $value): bool
    {
        return preg_match('/(?:€|\beur\b|\bprix\b|\btarifs?\b|\babonnement\b|\boffres?\b|\bgratuit(?:e)?\b|\bessai gratuit\b)/iu', $value) === 1;
    }

    private function isGenericAngle(Article $article, string $body): bool
    {
        $value = $this->key(implode(' ', [
            $article->title,
            $article->content_angle,
            $article->unique_promise,
            mb_substr($body, 0, 300),
        ]));

        return preg_match('/\b(?:guide complet|guide ultime|outil indispensable|optimisez votre gestion|simplifier votre gestion|presentation generale|choisir le bon outil|tout savoir)\b/u', $value) === 1
            && ! preg_match('/\b(?:btp|auto entrepreneur|freelance|tva|urssaf|batiment|mac|garage|facturation electronique|pme|tpe|comparatif|tarif)\b/u', $value);
    }

    /**
     * @param  array<int, string>  $blockers
     * @param  array<int, string>  $recommendations
     */
    private function auditAffiliateBalance(?SeoProject $project, Article $article, string $body, string $type, array &$blockers, array &$recommendations): float
    {
        if (! $project) {
            return 70;
        }

        $score = 100;
        $multiProduct = in_array($type, ['comparison', 'best_tools', 'alternatives'], true);
        $mentionedCompetitors = $this->competitors->mentionedCompetitors($project, $body.' '.$article->title);
        $competitorPlans = $project->pricingPlans
            ->where('is_active', true)
            ->filter(fn ($plan): bool => trim((string) $plan->competitor_name) !== '')
            ->groupBy(fn ($plan): string => $this->compactKey((string) $plan->competitor_name));
        $mentionedWithPlans = collect($mentionedCompetitors)
            ->filter(fn (string $name): bool => $competitorPlans->has($this->compactKey($name)))
            ->values();

        if ($type === 'comparison' && $mentionedWithPlans->isEmpty()) {
            $score -= 45;
            $blockers[] = 'Comparatif ou sélection sans prix concurrents vérifiés : ajoutez les URLs officielles des concurrents.';
        } elseif ($multiProduct && $mentionedCompetitors !== [] && $mentionedWithPlans->isEmpty()) {
            $score -= 24;
            $recommendations[] = 'Les concurrents cités doivent recevoir des sources tarifaires officielles avant diffusion large.';
        } elseif ($multiProduct && $mentionedWithPlans->count() < max(1, min(2, count($mentionedCompetitors)))) {
            $score -= 18;
            $recommendations[] = 'Certains concurrents cités n’ont pas encore de plans tarifaires vérifiés.';
        }

        if ($type === 'best_tools' && $mentionedWithPlans->count() < 2) {
            $score -= 20;
            $recommendations[] = 'Une page “meilleurs outils” doit s’appuyer sur plusieurs concurrents réellement sourcés.';
        }

        $primaryMentions = $this->nameMentions($body, $project->name);
        $competitorMentions = collect($mentionedCompetitors)->sum(fn (string $name): int => $this->nameMentions($body, $name));
        if ($multiProduct && $competitorMentions > 0 && $primaryMentions > ($competitorMentions * 2) + 4) {
            $score -= 18;
            $recommendations[] = 'Le produit affilié domine trop le texte par rapport aux concurrents.';
        }

        $ctaCount = preg_match_all('/(?:\/go\/|créer mon compte|creer mon compte|automatiser ma gestion|découvrir|decouvrir|tester|essayer|commencer)/iu', $body);
        if ($ctaCount > 5) {
            $score -= 16;
            $recommendations[] = 'Réduisez le nombre de CTA pour garder une lecture neutre et experte.';
        }

        return max(0, $score);
    }

    private function isRegulatoryRisk(string $context): bool
    {
        return preg_match('/\b(?:tva|urssaf|imp[oô]ts?|fiscal|juridique|l[eé]gal|obligatoire|dgfip|facturation [ée]lectronique|comptabilit[ée]|d[ée]claration|liasse|micro[- ]entreprise)\b/iu', $context) === 1;
    }

    private function needsHumanReview(Article $article, bool $highRisk): bool
    {
        return $highRisk
            || (float) ($article->duplicate_score ?? 0) >= 50
            || in_array($article->prepublish_status, ['blocked', 'needs_review'], true);
    }

    private function hasOfficialSource(Article $article, ?SeoProject $project): bool
    {
        $sources = $article->sources;
        if ($sources->isEmpty() && $project) {
            $sources = $project->sourcePages;
        }

        return $sources->contains(function ($source): bool {
            $host = mb_strtolower((string) parse_url((string) $source->url, PHP_URL_HOST));

            return $host !== '' && preg_match('/(?:^|\.)((?:entreprendre\.)?service-public\.fr|impots\.gouv\.fr|bofip\.impots\.gouv\.fr|economie\.gouv\.fr|urssaf\.fr|autoentrepreneur\.urssaf\.fr|inpi\.fr)$/u', $host) === 1;
        });
    }

    private function hasUnlabeledSimulation(string $body): bool
    {
        $hasMetricPromise = preg_match('/\b(?:gain|économie|economie|réduire|reduire|gagner|économiser|economiser|roi)\b.{0,160}(?:\d+(?:[,.]\d+)?\s*(?:%|€|eur|heures?|jours?))/isu', $body) === 1;
        if (! $hasMetricPromise) {
            return false;
        }

        return preg_match('/(?:simulation fictive|hypothèse de simulation|hypothese de simulation|scénario illustratif|scenario illustratif|exemple fictif|à visée pédagogique|a visee pedagogique)/iu', $body) !== 1;
    }

    private function missingFrenchAccents(string $body): int
    {
        preg_match_all('/\b(?:donnees?|verifie(?:e|es)?|fonctionnalites?|a verifier|couts?|electronique|generer|generation|methodologie|redaction|comptabilite|tresorerie|declaration|dedie(?:e|es)?|specialise(?:e|es)?|batiment|reglementaire|deja|echeance|creer|gerer|eviter)\b/i', $body, $matches);

        return count($matches[0] ?? []);
    }

    /** @return array{covered:int, expected:int, minimum:int, terms:string[]} */
    private function semanticDensity(Article $article, string $body): array
    {
        $terms = $this->semanticTerms($article);
        $text = $this->key($body);
        $covered = collect($terms)
            ->filter(fn (string $term): bool => str_contains($text, $this->key($term)))
            ->count();

        return [
            'covered' => $covered,
            'expected' => count($terms),
            'minimum' => min(5, max(3, (int) ceil(count($terms) * 0.38))),
            'terms' => $terms,
        ];
    }

    /** @return string[] */
    private function semanticTerms(Article $article): array
    {
        $context = $this->key($article->title.' '.$article->primary_keyword.' '.$article->topic_key.' '.$article->body);

        if (preg_match('/\b(?:btp|batiment|chantier|travaux|artisan)\b/u', $context) === 1) {
            return ['chantier', 'situation de travaux', 'retenue de garantie', 'acompte', 'tva bâtiment', 'métrés', 'bibliothèque d’ouvrages', 'rentabilité chantier', 'DGD', 'devis'];
        }
        if (preg_match('/\b(?:tva|urssaf|comptabilite|impot|fiscal|declaration)\b/u', $context) === 1) {
            return ['TVA', 'URSSAF', 'déclaration', 'échéance', 'livre des recettes', 'charges', 'régime fiscal', 'justificatif', 'trésorerie', 'seuil'];
        }
        if (preg_match('/\b(?:facture|facturation|devis)\b/u', $context) === 1) {
            return ['facture', 'devis', 'TVA', 'numérotation', 'mentions obligatoires', 'relance', 'paiement', 'facturation électronique', 'livre des recettes', 'acompte'];
        }
        if (preg_match('/\b(?:crm|prospect|pipeline|commercial)\b/u', $context) === 1) {
            return ['pipeline', 'prospect', 'relance', 'opportunité', 'segmentation', 'reporting', 'taux de conversion', 'cycle de vente', 'automatisation', 'intégration'];
        }

        return ['méthode', 'critères', 'limites', 'exemple', 'tableau', 'FAQ', 'sources', 'cas d’usage'];
    }

    private function nameMentions(string $body, string $name): int
    {
        $needle = $this->key($name);
        if ($needle === '') {
            return 0;
        }

        return preg_match_all('/(?<![a-z0-9])'.preg_quote($needle, '/').'(?![a-z0-9])/u', $this->key($body));
    }

    private function compactKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/u', '', $this->key($value)) ?: '';
    }

    private function key(string $value): string
    {
        $value = Str::ascii(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?: '');
    }
}
