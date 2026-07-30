<?php

namespace App\Services;

use App\Exceptions\DuplicateContentException;
use App\Exceptions\PlannedContentRejectedException;
use App\Models\Article;
use App\Models\ContentBrief;
use App\Models\EditorialIdea;
use App\Models\Keyword;
use App\Models\SeoProject;
use App\Models\Setting;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GeminiContentGenerator
{
    public function __construct(
        private readonly SeoContentStructure $structures,
        private readonly EditorialDuplicateDetector $duplicates,
        private readonly GeneratedContentSanitizer $sanitizer,
        private readonly CompetitorCatalog $competitors,
    ) {}

    public function generate(SeoProject $project, string $type, ?Keyword $keyword, string $instructions = '', ?array $lockedBlueprint = null, ?string $lockedTitle = null, ?int $ignoreArticleId = null, ?string $model = null): Article
    {
        $this->allowLongGeneration();
        $preflight = $lockedBlueprint
            ? $this->duplicates->analyzeBlueprint($project, $lockedBlueprint, implode(' ', [
                $lockedTitle, $lockedBlueprint['primary_keyword'] ?? '', $lockedBlueprint['unique_promise'] ?? '',
                implode(' ', $lockedBlueprint['outline'] ?? []),
            ]), $ignoreArticleId)
            : $this->duplicates->analyzeBefore($project, $keyword, $type, $ignoreArticleId);
        $blueprint = $lockedBlueprint ? $this->duplicates->normalizeBlueprint($lockedBlueprint) : $preflight['blueprint'];
        $unknownCompetitors = $this->competitors->unknownCompetitorMentions($project, implode(' ', [
            $lockedTitle,
            $keyword?->keyword,
            $blueprint['primary_keyword'] ?? '',
            $blueprint['topic'] ?? '',
            $blueprint['unique_promise'] ?? '',
            implode(' ', $blueprint['outline'] ?? []),
        ]));
        if ($unknownCompetitors !== []) {
            throw new PlannedContentRejectedException('Génération refusée : concurrent inconnu ou fictif ('.implode(', ', $unknownCompetitors).').');
        }
        if ($preflight['article'] && in_array($preflight['decision'], ['block', 'merge_or_reangle'], true)) {
            throw new DuplicateContentException($preflight['article'], $preflight['score'], $preflight['decision']);
        }

        $sources = $project->sourcePages()
            ->where('status', 'verified')
            ->with(['evidenceChunks' => fn ($query) => $query->orderByDesc('confidence_score')->limit(40)])
            ->get();

        if ($sources->isEmpty()) {
            throw new RuntimeException('Collectez au moins une source vérifiée avant de générer un contenu.');
        }

        $evidence = [];
        foreach ($sources as $sourceIndex => $source) {
            $reference = 'Source '.($sourceIndex + 1);
            $chunks = $source->evidenceChunks->pluck('source_excerpt')->take(40)->implode("\n- ");
            $sourceProduct = $source->competitor_name ?: $project->name;
            $evidence[] = "{$reference} - {$source->title}\nProduit source: {$sourceProduct}\nURL: {$source->url}\nVérifié: {$source->verified_at?->toDateString()}\n- {$chunks}";
        }

        $evidenceText = implode("\n\n", $evidence);
        $prompt = $this->prompt($project, $type, $keyword, $instructions, $evidenceText, $blueprint, $preflight, $lockedTitle);
        $data = $this->generateData($prompt, 0.35, null, 16384, $model);

        return $this->persistGeneratedData($project, $type, $keyword, $data, $blueprint, $sources, $lockedTitle, $ignoreArticleId);
    }

    public function partCount(
        string $type,
        ?string $title = null,
        ?string $intent = null,
        ?string $funnelStage = null,
        ?string $keyword = null,
        array $previousParts = [],
    ): int {
        $sections = $this->structures->sectionsFor($type, $title, $intent, $funnelStage, $keyword);

        return count($this->partGroups($sections, $previousParts));
    }

    public function generatePartFromIdea(SeoProject $project, EditorialIdea $idea, string $instructions, int $step, int $attempt = 0, array $previousParts = []): string
    {
        if (! in_array($idea->status, ['accepted', 'generating'], true)) {
            throw new RuntimeException('Cette idée ne fait pas partie du plan éditorial verrouillé.');
        }

        $this->assertIdeaCompetitorsAllowed($project, $idea);

        $structure = $this->structures->for($idea->content_type);
        $documentSections = $this->structures->sectionsFor(
            $idea->content_type,
            $idea->title,
            $idea->intent,
            $idea->funnel_stage,
            $idea->primary_keyword,
        );
        $groups = $this->partGroups($documentSections, $previousParts);
        if (! isset($groups[$step])) {
            throw new RuntimeException('Étape de rédaction inconnue.');
        }

        if ($step === 0) {
            $preflight = $this->duplicates->analyzeBlueprint($project, $idea->blueprint(), implode(' ', [
                $idea->title, $idea->primary_keyword, $idea->unique_promise, implode(' ', $idea->outline ?? []),
            ]));
            if ($preflight['article'] && in_array($preflight['decision'], ['block', 'merge_or_reangle'], true)) {
                throw new DuplicateContentException($preflight['article'], $preflight['score'], $preflight['decision']);
            }
        }

        [$sources, $evidence] = $this->compactEvidence($project, implode(' ', [
            $idea->title,
            $idea->primary_keyword,
            $idea->topic,
            $idea->angle,
            $idea->unique_promise,
            implode(' ', $groups[$step] ?? []),
        ]));
        if ($sources->isEmpty()) {
            throw new RuntimeException('Collectez au moins une source vérifiée avant de générer un contenu.');
        }

        $sections = $groups[$step];
        $verificationDate = $this->verificationDateLabel();
        $currentYear = now()->format('Y');
        $partNumber = $step + 1;
        $partCount = count($groups);
        $conclusionOnly = count($sections) === 1
            && preg_match('/conclusion|verdict final|recommandation finale/iu', $sections[0]) === 1;
        $minimumPartWords = $conclusionOnly
            ? 80
            : max(420, (int) ceil(
                $structure['minimum_words'] * (count($sections) / count($structure['sections'])) + 120
            ));
        $maximumPartWords = $conclusionOnly ? 150 : $minimumPartWords + 280;
        $sectionList = collect($sections)->map(fn (string $section) => '## '.$section)->implode("\n");
        $globalSectionList = collect($documentSections)
            ->map(fn (string $section, int $index) => ($index + 1).'. '.$section)
            ->implode("\n");
        $blueprint = json_encode($idea->blueprint(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $editorialDirectives = $this->bodyEditorialDirectives($idea->content_type, $project->name, $project);
        $verticalCrmDirective = $this->verticalCrmDirective(implode(' ', [
            $idea->title,
            $idea->primary_keyword,
            $idea->topic,
            $idea->angle,
            $idea->audience,
            $idea->unique_promise,
        ]));
        $btpDirective = $this->structures->btpGenerationDirective(implode(' ', [
            $idea->title,
            $idea->primary_keyword,
            $idea->topic,
            $idea->angle,
            $idea->audience,
            $idea->unique_promise,
        ]));
        $continuity = $this->continuityContext($previousParts);
        $entityExclusionRule = $this->mutualExclusionDirective($project, $sections, $previousParts);
        $internalLinkDirective = $this->internalLinkDirective($project, $idea->blueprint(), $idea->content_type, $step);
        $factoryDirective = $this->factoryDirective($idea, $verificationDate);
        $canonicalBrandDirective = $this->canonicalBrandDirective($project);
        $pricingFormattingRule = collect($sections)->contains(fn (string $section) => preg_match('/tarifs?|prix|coûts?|offres?|formules?/iu', $section) === 1)
            ? <<<'RULE'
FORMATAGE TARIFAIRE STRICT
- Présente les offres dans un tableau Markdown propre ou une liste structurée : nom, prix/période, public ou objectif, puis jusqu'a 6 fonctionnalites cles maximum.
- Ne concatène jamais le nom, le montant, la devise et la période. Chaque donnée possède sa colonne ou son libellé séparé.
- N’écris jamais « Démarrer », « Comparer les offres », « Prendre rendez-vous », « En savoir plus » ou un autre texte de bouton.
- N’invente aucun montant. Si la preuve structurée indique « Prix sur demande », conserve exactement cette formulation.
- Préserve chaque variante prouvée lorsque le tarif dépend du profil, de la taille, du volume ou du mode de facturation.
- Aucun préambule promotionnel avant le tableau ou la liste des offres.
RULE
            : '';
        $containsFaq = collect($sections)->contains(fn (string $section) => preg_match('/faq|questions fréquentes/iu', $section) === 1);
        $existingFaqQuestions = $containsFaq
            ? $this->existingFaqQuestions($project, $idea->blueprint(), $idea->closest_article_id)
            : [];
        $containsScenario = collect($sections)->contains(fn (string $section) => preg_match('/exemples?|scénarios?|cas d’usage/iu', $section) === 1);
        $tableGroupIndex = collect($groups)->search(fn (array $group) => collect($group)->contains(
            fn (string $section) => preg_match('/tableau|matrice|comparatif.*coup/iu', $section) === 1
        ));
        if ($tableGroupIndex === false) {
            $tableGroupIndex = 0;
        }
        $containsTable = $step === $tableGroupIndex;
        $listiclePromise = $this->structures->listiclePromise($idea->title);
        $containsPromisedList = $listiclePromise && in_array($listiclePromise['heading'], $sections, true);
        $isLast = $partNumber === $partCount;
        $openingRule = $step === 0
            ? "Dans les 150 premiers mots, écris « Réponse courte » et utilise le mot-clé principal dans une formulation française naturelle, avec les accents normaux si la forme française les exige."
            : 'Commence directement par le premier H2 sans répéter la réponse courte ni les sections précédentes.';
        $faqRule = $containsFaq
            ? 'La FAQ contient au moins 5 questions distinctes en H3 avec une réponse utile pour chacune. '.$this->faqExclusionDirective($existingFaqQuestions)
            : 'Ne crée aucune FAQ dans cette partie.';
        $scenarioRule = $containsScenario
            ? 'Ajoute exactement un passage intitulé « Hypothèse de simulation » avec une métrique plausible clairement présentée comme illustrative.'
            : 'N’utilise ni « Hypothèse de simulation » ni « Scénario illustratif » dans cette partie.';
        $tableRule = $containsTable
            ? 'Crée exactement un tableau Markdown décisionnel de 3 colonnes minimum et 2 lignes, avec des différences concrètes et une colonne « Limites » si plusieurs outils sont comparés.'
            : 'Aucun tableau Markdown ou HTML dans cette partie.';
        $listicleRule = $containsPromisedList
            ? "PROMESSE CHIFFRÉE BLOQUANTE : sous le H2 « {$listiclePromise['heading']} », crée exactement {$listiclePromise['count']} items distincts en H3 numérotés ou en liste numérotée de 1 à {$listiclePromise['count']}. Chaque item apporte une fonction, étape ou option concrète ; aucun élément ne doit être noyé dans un paragraphe."
            : 'Ne recrée pas la liste chiffrée promise par le titre dans cette partie : elle possède une section dédiée.';
        $endingRule = $isLast
            ? 'Termine par la seule conclusion finale demandée : 1 ou 2 paragraphes concis maximum, sans H3, H4 ni liste. Arrête immédiatement la réponse après le deuxième paragraphe.'
            : 'Ne rédige aucune conclusion dans cette partie.';
        $retryRule = $attempt > 0
            ? "TENTATIVE DE CORRECTION {$attempt} : la réponse précédente était incomplète ou indisponible. Termine toutes les sections et toutes les phrases, en restant strictement entre {$minimumPartWords} et {$maximumPartWords} mots. N’allonge pas la réponse pour compenser l’échec précédent."
            : "Reste strictement entre {$minimumPartWords} et {$maximumPartWords} mots utiles et termine chaque phrase.";

        $prompt = <<<PROMPT
Tu rédiges la partie {$partNumber}/{$partCount} d’un article SEO long en français sur {$project->name}.
VARIABLES SYSTÈME RÉSERVÉES AUX MÉTADONNÉES : CURRENT_DATE = {$verificationDate} ; CURRENT_YEAR = {$currentYear}.
H1 verrouillé : {$idea->title}
Mot-clé principal : {$idea->primary_keyword}
Type : {$structure['label']}
Longueur attendue : {$minimumPartWords} à {$maximumPartWords} mots utiles, jamais davantage.
Consignes : {$instructions}

{$this->frenchLanguageDirective()}

EMPREINTE VERROUILLÉE
{$blueprint}

PLAN GLOBAL DU DOCUMENT — NE PAS LE RÉPÉTER
{$globalSectionList}

CONTINUITÉ AVEC LES PARTIES DÉJÀ ENREGISTRÉES
{$continuity}

{$editorialDirectives}
{$verticalCrmDirective}
{$btpDirective}
{$entityExclusionRule}
{$pricingFormattingRule}
{$internalLinkDirective}
{$factoryDirective}

Rédige uniquement ces H2, dans cet ordre exact :
{$sectionList}

{$openingRule}
{$faqRule}
{$scenarioRule}
{$tableRule}
{$listicleRule}
{$endingRule}
{$retryRule}

RÈGLES BLOQUANTES
- Retourne uniquement un objet JSON avec la clé body contenant du Markdown, sans H1.
- Arrête la rédaction dès que les H2 demandés sont terminés. Ne dépasse jamais {$maximumPartWords} mots dans cette partie.
- CURRENT_DATE est réservé au header et au footer injectés par le CMS. N’écris aucune date de vérification, formule « vérifié le », « en date du », « informations disponibles au » ou « mis à jour le » dans le body, la FAQ ou la conclusion.
- Si le sujet ou un H2 exige une année d’actualité, utilise uniquement CURRENT_YEAR ({$currentYear}). N’utilise aucune année issue de ta mémoire ou de ta date de coupure de connaissances.
- {$canonicalBrandDirective}
- INTÉGRATION NATURELLE DES MOTS-CLÉS : N'insère jamais un mot-clé de force s'il casse la syntaxe. Adapte la grammaire, sépare les mots, ou ajoute des prépositions pour garder un ton 100% humain et fluide (ex: évite "Si vous cherchez un logiciel interpréteur comptable"). Ne fais jamais de phrases bidons de remplissage SEO.
- Chaque H2 doit être développé avec des paragraphes précis, des listes concrètes et des H3 lorsque pertinent.
- Aucun paragraphe ne dépasse 90 mots ou 5 phrases. Insère une ligne vide entre les paragraphes.
- Pour Checklist et Outils/Ressources : 1 à 2 phrases d’introduction maximum, puis directement des H3 ou une liste. Chaque puce contient UNE phrase impérative de 20 mots maximum. Aucun paragraphe explicatif, aucune justification et aucune répétition d’une section précédente.
- COMPLÉTUDE DES PUCES : chaque puce est une phrase grammaticale autonome et terminée. Elle ne finit jamais par un déterminant, une préposition, une conjonction, un mot coupé ou un groupe nominal inachevé. Avant de retourner le JSON, relis silencieusement la dernière phrase et chaque puce ; réécris intégralement tout élément incomplet dans cette même réponse.
- MARGE DE SORTIE : termine le dernier élément utile avant d’approcher la limite de sortie. Ne commence jamais une phrase, une puce ou un H3 que tu ne peux pas achever ; n’abrège jamais une phrase pour respecter la longueur maximale.
- EXCLUSION MUTUELLE : n’annonce jamais la même marche à suivre chronologique dans deux H2. Si le plan contient une Checklist mais aucune Méthode, la procédure complète appartient uniquement à la Checklist.
- COMPLÉTUDE CHRONOLOGIQUE : si un sous-chapitre commence par « Étape 1 », « La première étape consiste à » ou « Premièrement », rédige obligatoirement une Étape 2 puis une étape finale dans ce même H2. Interdiction d’abandonner une séquence commencée.
- Toute affirmation factuelle sur le produit doit rester vérifiable dans les preuves fournies. CITE EXPLICITEMENT tes sources dans le corps du texte en utilisant les marqueurs [S1], [S2], etc. à la fin des phrases correspondantes.
- Utilise exclusivement les preuves. N’invente ni prix, ni fonctionnalité, ni résultat observé.
- SIMULATION ET ROI : Ne mélange jamais le délai d'attente (calendaire) avec le temps de travail effectif. Base-toi uniquement sur des heures de travail administratif. CALCUL STRICT : Si tu multiplies (Heures x Mois x Taux Horaire), pose le calcul pas à pas et vérifie le résultat final mathématiquement (ex: 3.5h * 12 mois * 62.5€ = 2625€). Ne donne jamais un total erroné.
- UPSELL ET CTA : Si le sujet aborde une fonctionnalité premium (comme la signature électronique), contextualise-la avec précision (ex: "Indy l'intègre dès son plan Plus à 9 €/mois"). Tes appels à l'action doivent être tranchants et orientés fonctionnalité (ex: "Faites signer vos devis légalement en 2 clics avec Indy").
- Mentionne au moins une limite réaliste et un conseil de déploiement dans les sections pertinentes.
- TABLEAUX ET LISTES : Ne crée jamais de ligne ou de colonne pour écrire « Non spécifié », « Inconnu » ou « Non communiqué ». Si l'information manque, supprime simplement ce critère de la comparaison.
- Pour un prix absent dans le texte, explique le modèle vérifiable sans écrire « tarif non communiqué ».
- Ne répète aucun H2, aucune introduction, aucune FAQ et aucune conclusion.
- EXCLUSION MUTUELLE DES ENTITÉS : un logiciel retenu, recommandé, classé ou analysé dans la sélection principale ne peut jamais apparaître dans « Outils écartés ou informations insuffisantes ». Cette section utilise exclusivement des concurrents distincts. Effectue ce contrôle logique avant de retourner le JSON.
- Ne réutilise jamais mot pour mot une phrase, un conseil de déploiement, une explication, une liste, une recommandation ou un exemple déjà présent dans le contexte de continuité. Si l’idée a déjà été traitée, omets-la ; si une précision réellement nouvelle est nécessaire, formule-la avec un apport distinct.
- N’ajoute aucun encart affilié : le CMS le gère.

PREUVES
{$evidence}
PROMPT;

        $outputTokens = min(6144, 5120 + (min($attempt, 4) * 256));
        // Flash-Lite reste utilisé par défaut. Après plusieurs refus de capacité
        // consécutifs sur la même partie, Flash sert uniquement de secours afin
        // que la campagne ne boucle pas indéfiniment sur un modèle saturé.
        // api_attempts est remis à zéro dès que la partie est enregistrée : la
        // partie suivante repart donc automatiquement sur Flash-Lite.
        $partModel = $this->contentModelForAttempt($attempt);
        $part = $this->generateData($prompt, $attempt > 0 ? 0.32 : 0.22, $this->bodySchema(), $outputTokens, $partModel, 768);

        $body = $this->sanitizer->keepRequestedSections((string) $part['body'], $sections);
        $this->assertGeneratedPart($body, $sections, $minimumPartWords, $containsFaq, $containsScenario, $containsTable, $containsPromisedList ? $idea->title : null, $existingFaqQuestions);

        return $body;
    }

    /** @param string[] $parts */
    public function finalizeFromIdeaParts(SeoProject $project, EditorialIdea $idea, string $instructions, array $parts): Article
    {
        $this->assertIdeaCompetitorsAllowed($project, $idea);

        [$sources] = $this->compactEvidence($project);
        $body = $this->sanitizer->sanitize(implode("\n\n", array_filter($parts)));
        $comparedProducts = $this->extractComparedProducts($project, $idea->title, $body);
        $data = [
            'title' => $idea->title,
            'slug' => Str::slug($idea->title),
            'meta_title' => $idea->title,
            'meta_description' => $this->safeMetaDescription($idea->unique_promise),
            'body' => $body,
            'brief_title' => $idea->title,
            'angle' => $idea->angle,
            'audience' => $idea->audience,
            'outline' => $idea->outline,
            'product_keyword_fit' => true,
            'product_keyword_fit_reason' => 'Angle validé pendant la planification éditoriale.',
            'compared_products' => $comparedProducts,
        ];

        return $this->persistGeneratedData($project, $idea->content_type, $idea->keyword, $data, $idea->blueprint(), $sources, $idea->title);
    }

    public function regenerateArticle(Article $article, string $instructions = ''): Article
    {
        $article->loadMissing(['project', 'keyword', 'brief']);
        $project = $article->project;
        if (! $project) {
            throw new RuntimeException('Projet introuvable pour cet article.');
        }

        $keyword = $article->keyword ?: ($article->primary_keyword
            ? new Keyword(['keyword' => $article->primary_keyword, 'intent' => $article->search_intent])
            : null);
        $blueprint = $this->regenerationBlueprint($article, $project, $keyword);
        $previousStatus = $article->status;
        $previousPublishedAt = $article->published_at;
        $previousScheduledAt = $article->scheduled_at;
        $previousSlug = $article->slug;

        $baseRegenerationInstructions = trim($instructions."\nRégénération admin : conserve le même angle SEO, le même titre et remplace le contenu par une version plus précise et vérifiée.");
        $regenerationInstructions = $baseRegenerationInstructions;
        $generated = null;
        $maxAttempts = 7;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $generated = $this->generate(
                    $project,
                    $article->type,
                    $keyword,
                    $regenerationInstructions,
                    $blueprint,
                    $article->title,
                    $article->id,
                    $this->contentModelForAttempt($attempt),
                );
                break;
            } catch (Throwable $exception) {
                if (! $this->isRecoverableRegenerationError($exception)) {
                    throw $exception;
                }

                if ($attempt === $maxAttempts - 1) {
                    throw $this->finalRegenerationException($exception, $maxAttempts);
                }

                if ($exception instanceof PlannedContentRejectedException) {
                    $regenerationInstructions = $baseRegenerationInstructions."\n\n".$this->regenerationRejectionCorrectionDirective($exception, $project);
                }
                $this->pauseBeforeRegenerationRetry($attempt);
            }
        }

        if (! $generated instanceof Article) {
            throw new RuntimeException('La régénération n’a pas produit d’article exploitable.');
        }

        return DB::transaction(function () use ($article, $generated, $previousStatus, $previousPublishedAt, $previousScheduledAt, $previousSlug): Article {
            $article->refresh();
            $generated->loadMissing('sources');

            $article->versions()->create([
                'user_id' => auth()->id(),
                'version' => ($article->versions()->max('version') ?? 0) + 1,
                'title' => $article->title,
                'body' => $article->body,
                'content_blocks' => $article->content_blocks,
                'change_note' => 'Regeneration IA depuis la bibliotheque',
            ]);

            $article->update([
                'content_brief_id' => $generated->content_brief_id,
                'keyword_id' => $generated->keyword_id,
                'content_cluster_id' => $generated->content_cluster_id,
                'type' => $generated->type,
                'intent_type' => $generated->intent_type ?: ($article->intent_type ?: 'information'),
                'affiliate_priority' => $generated->affiliate_priority ?? $article->affiliate_priority ?? 0,
                'title' => $generated->title,
                'slug' => $previousSlug,
                'status' => $previousStatus,
                'primary_keyword' => $generated->primary_keyword,
                'entity_key' => $generated->entity_key,
                'topic_key' => $generated->topic_key,
                'content_angle' => $generated->content_angle,
                'editorial_audience' => $generated->editorial_audience,
                'funnel_stage' => $generated->funnel_stage,
                'topic_fingerprint' => $generated->topic_fingerprint,
                'unique_promise' => $generated->unique_promise,
                'editorial_problem' => $generated->editorial_problem,
                'expected_outcome' => $generated->expected_outcome,
                'excluded_topics' => $generated->excluded_topics,
                'canonical_article_id' => $generated->canonical_article_id,
                'duplicate_score' => $generated->duplicate_score,
                'duplicate_status' => $generated->duplicate_status,
                'meta_title' => $generated->meta_title,
                'meta_description' => $generated->meta_description,
                'body' => $generated->body,
                'excerpt' => $generated->excerpt,
                'search_intent' => $generated->search_intent,
                'author_id' => auth()->id() ?: $article->author_id,
                'content_blocks' => $generated->content_blocks,
                'source_ids' => $generated->source_ids,
                'quality_checks' => $generated->quality_checks,
                'generated_by' => $generated->generated_by,
                'verified_at' => $generated->verified_at,
                'published_at' => $previousStatus === 'published' ? ($previousPublishedAt ?? now()) : null,
                'scheduled_at' => $previousStatus === 'scheduled' ? $previousScheduledAt : null,
            ]);

            $article->sources()->sync($generated->sources->values()->mapWithKeys(fn ($source, $index) => [
                $source->id => ['citation_label' => 'S'.($index + 1)],
            ])->all());

            $generated->delete();
            $article->refresh();
            if ($article->status === 'published') {
                app(InternalLinkService::class)->refreshProject($article->seo_project_id);
            } else {
                app(InternalLinkService::class)->refresh($article);
            }

            return $article;
        });
    }

    private function regenerationBlueprint(Article $article, SeoProject $project, ?Keyword $keyword): array
    {
        $brief = $article->brief;
        $blueprint = [
            'entity' => $article->entity_key ?: Str::slug($project->name),
            'topic' => $article->topic_key ?: null,
            'intent' => $article->search_intent ?: $brief?->search_intent,
            'audience' => $article->editorial_audience ?: $brief?->audience ?: 'general',
            'angle' => $article->content_angle ?: $brief?->angle ?: 'guide-pratique',
            'funnel_stage' => $article->funnel_stage ?: $brief?->funnel_stage ?: 'consideration',
            'primary_keyword' => $article->primary_keyword ?: $keyword?->keyword ?: $article->title,
            'unique_promise' => $article->unique_promise ?: $brief?->unique_promise ?: $article->title,
            'problem' => $article->editorial_problem ?: $brief?->editorial_problem ?: $article->title,
            'expected_outcome' => $article->expected_outcome ?: $brief?->expected_outcome ?: 'produire une version plus précise et vérifiée',
            'excluded_topics' => $article->excluded_topics ?: ($brief?->excluded_topics ?? []),
            'outline' => $brief?->outline ?? [],
        ];

        if (! $blueprint['topic']) {
            $base = $this->duplicates->blueprint($project, $keyword, $article->type);
            $blueprint = array_merge($base, array_filter($blueprint, fn ($value): bool => $value !== null && $value !== ''));
        }

        return $this->duplicates->normalizeBlueprint($blueprint);
    }

    protected function pauseBeforeRegenerationRetry(int $attempt): void
    {
        usleep(min(8000, 1000 * (2 ** min(3, $attempt))) * 1000);
    }

    private function isRecoverableRegenerationError(Throwable $exception): bool
    {
        return $exception instanceof PlannedContentRejectedException
            || $this->isCapacityError($exception)
            || $this->isTimeoutError($exception);
    }

    private function finalRegenerationException(Throwable $exception, int $maxAttempts): RuntimeException
    {
        if ($exception instanceof PlannedContentRejectedException) {
            return new RuntimeException('La régénération a été refusée après '.$maxAttempts.' essais automatiques : '.$exception->getMessage(), 0, $exception);
        }

        return new RuntimeException('Gemini est toujours saturé après '.$maxAttempts.' essais automatiques. Réessayez dans quelques minutes.', 0, $exception);
    }

    private function regenerationRejectionCorrectionDirective(PlannedContentRejectedException $exception, SeoProject $project): string
    {
        $allowed = implode(', ', $this->competitors->allowedEntities($project));

        return <<<TEXT
CORRECTION OBLIGATOIRE APRÈS REFUS QUALITÉ
Le brouillon précédent a été refusé pour cette raison exacte : {$exception->getMessage()}
- Réécris intégralement le brouillon en corrigeant cette raison, sans conserver la formulation fautive.
- Entités autorisées uniquement : {$allowed}. Toute autre marque, société, artisan fictif, client fictif ou version logicielle inventée est interdite.
- N'utilise aucun nom propre d'exemple comme "Plomberie Pro", "BatiMax", "Devis Express", "Facture Expert" ou équivalent. Utilise des formulations génériques : "une entreprise de plomberie", "un artisan couvreur", "une PME du bâtiment".
- Pour une page BTP, distingue toujours les outils spécialisés BTP des généralistes adaptables, et n'attribue jamais une fonction chantier avancée sans preuve directe.
- Remplace toute idée de "frais cachés" ou "coûts cachés" par "frais additionnels éventuels", "modules payants", "options" ou "limites de plan".
- Avant de retourner le JSON, vérifie silencieusement que le texte ne contient aucun nom propre hors liste et aucune promesse chantier non prouvée.
TEXT;
    }

    private function isCapacityError(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'high demand')
            || str_contains($message, 'high traffic')
            || str_contains($message, 'too many requests')
            || str_contains($message, 'resource exhausted')
            || str_contains($message, 'rate limit')
            || preg_match('/(?:gemini\s+)?http\s+(?:429|503)\b/u', $message) === 1
            || preg_match('/statut http (?:429|503)\b/u', $message) === 1;
    }

    private function isTimeoutError(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return $exception instanceof ConnectionException
            || str_contains($message, 'curl error 28')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'délai d’attente')
            || str_contains($message, 'connection reset');
    }

    private function persistGeneratedData(SeoProject $project, string $type, ?Keyword $keyword, array $data, array $blueprint, $sources, ?string $lockedTitle, ?int $ignoreArticleId = null): Article
    {
        $this->assertStrategicFit($data, $type, $project, $keyword);
        $verificationDate = now();
        $data['body'] = $this->sanitizer->sanitize((string) $data['body']);
        $data['meta_description'] = $this->safeMetaDescription((string) ($data['meta_description'] ?? $data['title']));
        if ($lockedTitle) {
            $data['title'] = $lockedTitle;
            $data['meta_title'] = $lockedTitle;
            $data['outline'] = $blueprint['outline'];
        } elseif (in_array($type, ['informational', 'question'], true)) {
            $data['title'] = $this->duplicates->recommendedTitle($project, $blueprint);
            $data['meta_title'] = $data['title'];
        }
        $sourceIds = $sources->pluck('id')->all();
        $audit = $this->structures->audit((string) $data['body'], $type, $keyword?->keyword, $sourceIds !== [], $project->name, true, (string) $data['title']);

        // Les écarts de présentation restent visibles dans quality_checks pour
        // la relecture, mais ne doivent jamais remplacer un angle éditorial
        // valide. Les parties ont déjà passé les contrôles bloquants (longueur,
        // H2, tableau, FAQ, scénario et promesse chiffrée) avant cet assemblage.
        // Seuls un hors-sujet stratégique ou un vrai doublon restent bloquants.

        $postflight = $this->duplicates->analyzeGenerated($project, $keyword, $type, $data, (string) $data['body'], $blueprint, $ignoreArticleId);
        if ($postflight['article'] && $postflight['decision'] === 'block') {
            throw new DuplicateContentException($postflight['article'], $postflight['score'], $postflight['decision']);
        }

        $checks = $this->qualityChecks((string) $data['body'], $sourceIds, $keyword?->keyword, $audit, $project->name);

        $slug = $this->availableSlug($project, $blueprint, $postflight, (string) $data['title']);

        $brief = ContentBrief::query()->create([
            'seo_project_id' => $project->id,
            'keyword_id' => $keyword?->id,
            'content_cluster_id' => $keyword?->content_cluster_id,
            'type' => $type,
            'intent_type' => $keyword?->intent_type ?: 'information',
            'affiliate_priority' => (float) ($keyword?->affiliate_priority ?? 0),
            'entity_key' => $blueprint['entity'],
            'topic_key' => $blueprint['topic'],
            'content_angle' => $blueprint['angle'],
            'title' => (string) ($data['brief_title'] ?? $data['title']),
            'angle' => $blueprint['angle'],
            'audience' => $blueprint['audience'],
            'unique_promise' => $blueprint['unique_promise'],
            'editorial_problem' => $blueprint['problem'] ?? null,
            'expected_outcome' => $blueprint['expected_outcome'] ?? null,
            'excluded_topics' => $blueprint['excluded_topics'],
            'search_intent' => $blueprint['intent'],
            'funnel_stage' => $blueprint['funnel_stage'],
            'outline' => $data['outline'] ?? [],
            'source_ids' => $sourceIds,
        ]);

        $article = Article::query()->create([
            'seo_project_id' => $project->id,
            'keyword_id' => $keyword?->id,
            'content_cluster_id' => $keyword?->content_cluster_id,
            'content_brief_id' => $brief->id,
            'type' => $type,
            'intent_type' => $keyword?->intent_type ?: 'information',
            'affiliate_priority' => (float) ($keyword?->affiliate_priority ?? 0),
            'title' => mb_substr((string) $data['title'], 0, 255),
            'thumbnail_title' => mb_substr((string) ($blueprint['thumbnail_title'] ?? ''), 0, 255) ?: null,
            'slug' => $slug,
            'status' => 'review',
            'primary_keyword' => $keyword?->keyword,
            ...$this->duplicates->articleFingerprintAttributes($blueprint),
            'canonical_article_id' => $postflight['decision'] === 'merge_or_reangle' ? $postflight['article']?->id : null,
            'duplicate_score' => $postflight['decision'] !== 'allow' ? $postflight['score'] : null,
            'duplicate_status' => match ($postflight['decision']) {
                'merge_or_reangle' => 'potential',
                'differentiate' => 'needs_differentiation',
                default => null,
            },
            'meta_title' => mb_substr((string) ($data['meta_title'] ?? $data['title']), 0, 255),
            'meta_description' => mb_substr((string) ($data['meta_description'] ?? ''), 0, 500),
            'body' => (string) $data['body'],
            'excerpt' => mb_substr((string) ($data['meta_description'] ?? ''), 0, 500),
            'search_intent' => $blueprint['intent'],
            'conversion_goal' => $blueprint['conversion_goal'] ?? 'general',
            'author_id' => auth()->id(),
            'content_blocks' => $this->contentBlocks($project, (string) $data['body'], $verificationDate, $type, $keyword),
            'source_ids' => $sourceIds,
            'quality_checks' => $checks,
            'generated_by' => $this->model(),
            'verified_at' => $verificationDate,
        ]);
        $article->sources()->sync($sources->values()->mapWithKeys(fn ($source, $index) => [$source->id => ['citation_label' => 'S'.($index + 1)]])->all());
        $article->tools()->sync([$project->id => ['role' => 'featured']]);
        $this->autoCategorizeArticle($article);
        app(InternalLinkService::class)->refresh($article);
        app(PrePublishAuditService::class)->audit($article->fresh());

        return $article;
    }

    private function autoCategorizeArticle(Article $article): void
    {
        $categoryIds = [];

        $categorySlug = match ($article->type) {
            'comparison', 'tool_comparison' => 'comparatifs',
            'tool_review', 'review' => 'avis',
            'best_tools', 'alternatives' => 'logiciels',
            default => 'guides',
        };

        if (str_contains((string) $article->slug, '-vs-')) {
            $categorySlug = 'comparatifs';
        }

        $mainCategory = Category::firstOrCreate(
            ['slug' => $categorySlug],
            ['name' => match ($categorySlug) {
                'comparatifs' => 'Comparatifs',
                'avis' => 'Avis & Tests Logiciels',
                'logiciels' => 'Les Meilleurs Logiciels',
                default => 'Guides Indépendants',
            }]
        );
        $categoryIds[] = $mainCategory->id;

        $article->categories()->syncWithoutDetaching($categoryIds);
    }

    public function generateFromIdea(SeoProject $project, EditorialIdea $idea, string $instructions = ''): Article
    {
        if (! in_array($idea->status, ['accepted', 'generating', 'candidate', 'pending'], true)) {
            throw new RuntimeException('Cette idée ne fait pas partie du plan éditorial verrouillé.');
        }

        $this->assertIdeaCompetitorsAllowed($project, $idea);

        return $this->generate(
            $project,
            $idea->content_type,
            $idea->keyword,
            $instructions,
            $idea->blueprint(),
            $idea->title,
        );
    }

    public function testConnection(?string $key = null, ?string $model = null): bool
    {
        $response = $this->request($key)->post($this->endpoint($model), [
            'contents' => [['parts' => [['text' => 'Réponds uniquement par OK.']]]],
            'generationConfig' => [
                'temperature' => 0,
                'maxOutputTokens' => 10,
                'thinkingConfig' => ['thinkingBudget' => 0],
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: 'La connexion Gemini a échoué.');
        }

        return str_contains(mb_strtoupper((string) $response->json('candidates.0.content.parts.0.text')), 'OK');
    }

    private function request(?string $key = null): PendingRequest
    {
        $key ??= Setting::value('gemini_api_key', config('services.gemini.key'));
        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('Ajoutez d’abord votre clé API Gemini dans Réglages.');
        }

        // Herd/Nginx coupe les requêtes FastCGI à 60 secondes. Rendre la main
        // avant cette limite permet à Livewire d'afficher l'état et de relancer
        // automatiquement l'appel au lieu de provoquer une page 504.
        return Http::timeout(45)->connectTimeout(8)->withHeaders([
            'x-goog-api-key' => trim($key),
            'Content-Type' => 'application/json',
        ]);
    }

    private function endpoint(?string $model = null): string
    {
        $model = $model ?: $this->model();
        if (! in_array($model, ['gemini-2.5-flash-lite', 'gemini-2.5-flash'], true)) {
            $model = 'gemini-2.5-flash-lite';
        }

        return 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';
    }

    private function allowLongGeneration(): void
    {
        if (function_exists('set_time_limit')) {
            @ini_set('max_execution_time', '420');
            @set_time_limit(420);
        }
    }

    private function model(): string
    {
        return (string) Setting::value('gemini_model', 'gemini-2.5-flash-lite');
    }

    private function contentModelForAttempt(int $attempt): string
    {
        $configuredModel = $this->model();

        if ($configuredModel === 'gemini-2.5-flash-lite' && $attempt >= 3) {
            return 'gemini-2.5-flash';
        }

        return $configuredModel;
    }

    private function prompt(SeoProject $project, string $type, ?Keyword $keyword, string $instructions, string $evidence, array $blueprint, array $preflight, ?string $lockedTitle = null): string
    {
        $verificationDate = $this->verificationDateLabel();
        $currentYear = now()->format('Y');
        $verticalCrmDirective = $this->verticalCrmDirective(implode(' ', [
            $lockedTitle,
            $keyword?->keyword,
            $blueprint['topic'] ?? '',
            $blueprint['angle'] ?? '',
            $blueprint['audience'] ?? '',
            $blueprint['unique_promise'] ?? '',
        ]));
        $btpDirective = $this->structures->btpGenerationDirective(implode(' ', [
            $lockedTitle,
            $keyword?->keyword,
            $blueprint['topic'] ?? '',
            $blueprint['angle'] ?? '',
            $blueprint['audience'] ?? '',
            $blueprint['unique_promise'] ?? '',
        ]));
        $faqExclusionDirective = $this->faqExclusionDirective($this->existingFaqQuestions($project, $blueprint));
        $internalLinkDirective = $this->internalLinkDirective($project, $blueprint, $type);
        $seoIntelligenceDirective = $this->seoIntelligenceDirective($project, $keyword, $blueprint);
        $canonicalBrandDirective = $this->canonicalBrandDirective($project);
        $editorialBlueprint = json_encode($blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $differentiation = $preflight['article']
            ? "Contenu existant le plus proche ({$preflight['score']} %) : « {$preflight['article']->title} ». Ne reprends ni sa promesse, ni son plan ; respecte strictement l’angle et les sujets exclus ci-dessous."
            : 'Aucun contenu existant proche : respecte néanmoins cette empreinte sans élargir vers un guide général.';

        return <<<PROMPT
Tu es un éditeur SEO affilié exigeant. Génère un brouillon en français, utile, précis et destiné à une validation humaine.

ATTENTION CRITIQUE - INTERDICTION D'INVENTER DES LOGICIELS : 
Tu as l'interdiction formelle et absolue d'inventer des noms de logiciels. Ne génère JAMAIS de faux outils (comme "AccountPro Cloud", "ComptaFacile", etc.). Base-toi STRICTEMENT et UNIQUEMENT sur les logiciels cités dans les PREUVES. S'il s'agit de concurrents non fournis, utilise uniquement des logiciels existants, réels et très connus en France. TOUTE INVENTION de nom de logiciel est inacceptable.

VARIABLES SYSTÈME RÉSERVÉES AUX MÉTADONNÉES : CURRENT_DATE = {$verificationDate} ; CURRENT_YEAR = {$currentYear}.

PROJET
Application : {$project->name}
Site officiel : {$project->website_url}
Pays : {$project->country}
Devise : {$project->currency}
Type de contenu : {$type}
Mot-clé principal : {$keyword?->keyword}
Consignes éditoriales : {$instructions}

{$this->frenchLanguageDirective()}

EMPREINTE ÉDITORIALE UNIQUE
{$editorialBlueprint}
{$differentiation}

Le titre, la promesse, le plan H2/H3 et la FAQ doivent servir précisément cette empreinte. Les sujets de excluded_topics ne doivent pas devenir des sections principales.

{$this->structures->prompt($type, $lockedTitle, $keyword?->keyword)}

{$this->affiliateDirectives($type, $project->name, $project)}
{$verticalCrmDirective}
{$btpDirective}
{$faqExclusionDirective}
{$internalLinkDirective}
{$seoIntelligenceDirective}

RÈGLES ABSOLUES
1. Utilise exclusivement les preuves ci-dessous pour toute affirmation factuelle sur le produit, ses prix, fonctions, limites et conditions.
2. Chaque affirmation factuelle doit être vérifiable dans les preuves fournies, mais le texte final ne doit jamais afficher de marqueur de source.
3. TABLEAUX COMPARATIFS : Ne crée JAMAIS de ligne ou de colonne pour écrire « Non spécifié », « Inconnu » ou « Non communiqué ». Construis tes tableaux EXCLUSIVEMENT autour des critères pour lesquels tu possèdes des données concrètes.
4. Pour un prix absent dans le texte, explique le modèle vérifiable sans écrire « tarif non communiqué ».
5. Ne promets jamais un classement Google/Bing et n'invente ni test, ni expérience d'équipe, ni capture d'écran.
6. Signale clairement les liens commerciaux dans le fond, mais n’écris aucun encart de transparence affiliée dans body : le CMS l’injecte automatiquement une seule fois.
6. Respecte exactement la structure éditoriale obligatoire, produis du Markdown riche et développe chaque section avec une vraie profondeur d’analyse.
7. Le title SEO doit rester naturel, la meta description concise et le slug court.
8. Fournis aussi un brief éditorial : titre de brief, angle différenciant, audience et plan détaillé (outline).
9. CURRENT_DATE est exclusivement réservé au header et au footer injectés par le CMS. N’écris aucune date de vérification ni formule de fraîcheur dans body, la FAQ ou la conclusion.
10. Si le H1 nécessite une année d’actualité, utilise exclusivement CURRENT_YEAR ({$currentYear}) dans title, meta_title et slug. Toute année issue de ta mémoire ou de ta date de coupure de connaissances est interdite.
11. {$canonicalBrandDirective}
12. Ne réutilise jamais mot pour mot une phrase ou un conseil de déploiement dans deux sections. Si une recommandation est déjà donnée, omets-la ou apporte une recommandation réellement différente.

PREUVES
{$evidence}
PROMPT;
    }

    private function generateData(string $prompt, float $temperature = 0.35, ?array $schema = null, int $maxOutputTokens = 16384, ?string $model = null, int $thinkingBudget = 768): array
    {
        $resolvedModel = $model ?: $this->model();
        $payload = $this->request()->post($this->endpoint($model), [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxOutputTokens,
                'thinkingConfig' => ['thinkingBudget' => $thinkingBudget],
                'stopSequences' => ['Transparence affiliée', '© 2026 BusinessKit'],
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => $schema ?? $this->schema(),
            ],
        ]);

        if (! $payload->successful()) {
            $message = $payload->json('error.message') ?: "Gemini a retourné le statut HTTP {$payload->status()}.";
            throw new RuntimeException("Gemini HTTP {$payload->status()} : {$message}");
        }

        $this->logUsage($payload->json('usageMetadata', []), $resolvedModel, 'content');

        if (mb_strtoupper((string) $payload->json('candidates.0.finishReason')) === 'MAX_TOKENS') {
            throw new RuntimeException('Réponse Gemini incomplète : limite de sortie atteinte avant la fin de la section.');
        }

        $text = $payload->json('candidates.0.content.parts.0.text');
        $data = is_string($text) ? json_decode($text, true) : null;
        $requiresFullDocument = $schema === null;
        if (! is_array($data) || empty($data['body']) || ($requiresFullDocument && empty($data['title']))) {
            throw new RuntimeException('Gemini n’a pas retourné un contenu structuré exploitable.');
        }

        return $data;
    }

    private function safeMetaDescription(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?: '');
        if (mb_strlen($value) > 220) {
            $value = mb_substr($value, 0, 220);
            $value = preg_replace('/\s+\S*$/u', '', $value) ?: $value;
        }

        $value = rtrim($value, " \t\n\r\0\x0B,;:-");

        return preg_match('/[.!?…]$/u', $value) === 1 ? $value : $value.'.';
    }

    private function compactEvidence(SeoProject $project, string $context = ''): array
    {
        $sources = $project->sourcePages()
            ->where('status', 'verified')
            ->with(['evidenceChunks' => fn ($query) => $query->orderByDesc('confidence_score')->limit(16)])
            ->get();

        $contextTokens = $this->relevanceTokens($context);
        $rankedChunks = $sources->values()->flatMap(function ($source, int $sourceIndex) use ($contextTokens) {
            return $source->evidenceChunks->values()->map(function ($chunk, int $chunkIndex) use ($source, $sourceIndex, $contextTokens): array {
                $haystack = Str::ascii(mb_strtolower(implode(' ', [
                    $source->title,
                    $source->competitor_name,
                    $chunk->category,
                    $chunk->value,
                    $chunk->source_excerpt,
                ])));
                $matches = collect($contextTokens)->filter(fn (string $token) => str_contains($haystack, $token))->count();

                return [
                    'source_index' => $sourceIndex,
                    'chunk_index' => $chunkIndex,
                    'score' => ($matches * 4) + ((float) $chunk->confidence_score * 2),
                    'chunk' => $chunk,
                ];
            });
        });

        // Un extrait minimum par source conserve la couverture des preuves.
        // Les meilleures preuves restantes sont ensuite choisies selon
        // le sujet et les H2 de la partie, dans une enveloppe globale maîtrisée.
        $selected = $rankedChunks
            ->groupBy('source_index')
            ->map(fn ($chunks) => $chunks->sortByDesc('score')->first())
            ->filter()
            ->keyBy(fn (array $entry) => $entry['source_index'].'-'.$entry['chunk_index']);
        foreach ($rankedChunks->sortByDesc('score') as $entry) {
            if ($selected->count() >= 18) {
                break;
            }
            $selected->put($entry['source_index'].'-'.$entry['chunk_index'], $entry);
        }

        $evidence = $sources->values()->map(function ($source, int $index) use ($selected, $project): string {
            $chunks = $selected
                ->where('source_index', $index)
                ->sortByDesc('score')
                ->map(fn (array $entry) => '- '.Str::limit(trim((string) $entry['chunk']->source_excerpt), 520, ''))
                ->implode("\n");

            $verifiedAt = $source->verified_at?->locale('fr')->translatedFormat('j F Y') ?? $this->verificationDateLabel();
            $sourceProduct = $source->competitor_name ?: $project->name;

            return 'Source '.($index + 1)." - {$source->title}\nProduit source: {$sourceProduct}\nURL: {$source->url}\nVérifié le : {$verifiedAt}\n{$chunks}";
        })->implode("\n\n");

        return [$sources, $evidence];
    }

    /** @return string[] */
    private function relevanceTokens(string $value): array
    {
        $value = Str::ascii(mb_strtolower($value));
        $tokens = preg_split('/[^a-z0-9]+/', $value) ?: [];

        return collect($tokens)
            ->filter(fn (string $token) => strlen($token) >= 4)
            ->reject(fn (string $token) => in_array($token, [
                'avec', 'dans', 'pour', 'plus', 'cette', 'comment', 'guide', 'article', 'solution', 'outils',
            ], true))
            ->unique()
            ->take(40)
            ->values()
            ->all();
    }

    /**
     * Les nouveaux lots utilisent au plus trois appels de rédaction par article.
     * Un lot déjà commencé avec l'ancien découpage de deux H2 continue avec ce
     * découpage afin que les index et les parties sauvegardées restent alignés.
     *
     * @param  string[]  $sections
     * @param  string[]  $previousParts
     * @return array<int, array<int, string>>
     */
    private function partGroups(array $sections, array $previousParts = []): array
    {
        if ($this->usesLegacyPartGrouping($previousParts)) {
            return array_chunk($sections, 2);
        }

        $groupSize = max(1, (int) ceil(count($sections) / 3));

        return array_chunk($sections, $groupSize);
    }

    /** @param string[] $previousParts */
    private function usesLegacyPartGrouping(array $previousParts): bool
    {
        if ($previousParts === []) {
            return false;
        }

        preg_match_all('/^##\s+.+$/mu', (string) reset($previousParts), $headings);

        return count($headings[0] ?? []) === 2;
    }

    private function logUsage(mixed $metadata, string $model, string $operation): void
    {
        if (! is_array($metadata) || $metadata === []) {
            return;
        }

        Log::info('Gemini usage', [
            'operation' => $operation,
            'model' => $model,
            'prompt_tokens' => (int) ($metadata['promptTokenCount'] ?? 0),
            'output_tokens' => (int) ($metadata['candidatesTokenCount'] ?? 0),
            'thinking_tokens' => (int) ($metadata['thoughtsTokenCount'] ?? 0),
            'total_tokens' => (int) ($metadata['totalTokenCount'] ?? 0),
            'cached_tokens' => (int) ($metadata['cachedContentTokenCount'] ?? 0),
        ]);
    }

    private function assertGeneratedPart(string $body, array $sections, int $minimumWords, bool $containsFaq, bool $containsScenario, bool $containsTable, ?string $listicleTitle = null, array $existingFaqQuestions = []): void
    {
        preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($body), $words);
        preg_match_all('/^##\s+.+$/mu', $body, $h2);
        preg_match_all('/^###\s+.+$/mu', $body, $h3);
        $issues = [];

        $conclusionOnly = count($sections) === 1
            && preg_match('/conclusion|verdict final|recommandation finale/iu', $sections[0]) === 1;
        $requiredWords = $conclusionOnly ? 45 : max(220, (int) floor($minimumWords * .45));
        if (count($words[0] ?? []) < $requiredWords) {
            $issues[] = 'partie trop courte';
        }
        if (count($h2[0] ?? []) !== count($sections)) {
            $issues[] = 'sections H2 manquantes';
        }
        if ($containsTable && preg_match('/^\|.+\|\s*$\R^\|[\s:|\-]+\|/mu', $body) !== 1) {
            $issues[] = 'tableau Markdown absent';
        }
        if ($containsFaq && count($h3[0] ?? []) < 5) {
            $issues[] = 'FAQ incomplète';
        }
        if ($containsFaq && $this->faqOverlapsExisting($body, $existingFaqQuestions)) {
            $issues[] = 'FAQ trop proche d’un article voisin';
        }
        if ($containsScenario && preg_match('/(?:hypothèse de simulation|scénario illustratif).{0,500}\b\d+(?:[.,]\d+)?\s*(?:%|personnes?|utilisateurs?|heures?|minutes?|jours?|euros?|€)/isu', $body) !== 1) {
            $issues[] = 'scénario chiffré absent';
        }
        if ($listicleTitle && ! $this->structures->hasPromisedList($body, $listicleTitle)) {
            $issues[] = 'liste chiffrée promise par le titre absente ou incomplète';
        }
        if (! $this->structures->hasCompleteChronologicalSequences($body)) {
            $issues[] = 'séquence chronologique commencée mais non terminée';
        }
        preg_match_all('/^##\s+.+\R(.*?)(?=^##\s+|\z)/msu', $body, $sectionBodies);
        foreach ($sectionBodies[1] ?? [] as $sectionBody) {
            $ending = rtrim(strip_tags($sectionBody));
            if ($ending !== '' && preg_match('/(?:[.!?;:»”\)\]]|\[S\d+\]|\|)$/u', $ending) !== 1) {
                $issues[] = 'phrase coupée en fin de section';
                break;
            }
        }

        if ($issues !== []) {
            throw new RuntimeException('Réponse Gemini incomplète : '.implode(', ', $issues).'.');
        }
    }

    private function generateBodyInParts(SeoProject $project, string $type, ?Keyword $keyword, string $instructions, string $evidence, array $blueprint): string
    {
        $structure = $this->structures->for($type);
        $currentYear = now()->format('Y');
        $affiliateDirectives = $this->affiliateDirectives($type, $project->name, $project);
        $btpDirective = $this->structures->btpGenerationDirective(implode(' ', [
            $keyword?->keyword,
            $blueprint['topic'] ?? '',
            $blueprint['angle'] ?? '',
            $blueprint['audience'] ?? '',
            $blueprint['unique_promise'] ?? '',
        ]));
        $seoIntelligenceDirective = $this->seoIntelligenceDirective($project, $keyword, $blueprint);
        $canonicalBrandDirective = $this->canonicalBrandDirective($project);
        $editorialBlueprint = json_encode($blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $groupCount = min(3, count($structure['sections']));
        $groups = array_chunk($structure['sections'], (int) ceil(count($structure['sections']) / $groupCount));
        $parts = [];

        foreach ($groups as $index => $sections) {
            $number = $index + 1;
            $sectionList = collect($sections)->map(fn (string $section) => '## '.$section)->implode("\n");
            $minimumPartWords = max(350, (int) ceil(
                $structure['minimum_words'] * (count($sections) / count($structure['sections'])) + 180
            ));
            $openingRule = $number === 1
                ? "Commence directement par le premier H2 demandé. Sa réponse doit contenir le mot-clé principal dans une formulation française naturelle dans les 150 premiers mots, avec les accents normaux si la forme française les exige."
                : 'Ne répète pas l’introduction et commence directement par le premier H2 demandé.';
            $containsFaq = collect($sections)->contains(fn (string $section) => preg_match('/faq|questions fréquentes/iu', $section) === 1);
            $containsScenarioSection = collect($sections)->contains(fn (string $section) => preg_match('/exemples?|scénarios?|cas d’usage/iu', $section) === 1);
            $containsTableSection = collect($sections)->contains(fn (string $section) => preg_match('/tableau|matrice|comparatif.*coup/iu', $section) === 1);
            $isLastPart = $number === count($groups);
            $scenarioRule = $containsScenarioSection
                ? 'Cette partie est la seule autorisée à contenir exactement une « Hypothèse de simulation » ou un « Scénario illustratif » chiffré.'
                : 'N’utilise pas les libellés « Hypothèse de simulation » ou « Scénario illustratif » dans cette partie : le scénario unique appartient à une autre section.';
            $tableRule = $containsTableSection
                ? 'Cette partie contient la section décisionnelle dédiée : génère exactement un seul tableau Markdown, uniquement sous ce H2.'
                : 'Aucun tableau Markdown ou HTML n’est autorisé dans cette partie. Utilise du texte, des listes ou des H3.';
            $endingRule = match (true) {
                $containsFaq && $isLastPart => 'Rédige une seule FAQ avec au moins 5 questions en H3, puis une conclusion limitée à 1 ou 2 paragraphes, sans H3 ni liste. Arrête ensuite le document. N’ajoute aucun encart de transparence affiliée : le CMS le gère.',
                $containsFaq => 'Dans la section FAQ, rédige au moins 5 questions en H3 avec leurs réponses directes et sourcées.',
                $isLastPart => 'Termine par une conclusion limitée à 1 ou 2 paragraphes, sans H3 ni liste, puis arrête le document. N’ajoute aucun encart de transparence affiliée : le CMS le gère.',
                default => 'Termine la dernière section demandée sans ajouter de nouveau format ni de section récapitulative.',
            };
            $internalLinkDirective = $this->internalLinkDirective($project, $blueprint, $type, $index);

            $partCount = count($groups);
            $partPrompt = <<<PROMPT
Tu rédiges la partie {$number}/{$partCount} d’un contenu SEO long en français sur {$project->name}.
Type : {$structure['label']}
Mot-clé principal : {$keyword?->keyword}
Consignes : {$instructions}
Longueur minimale de cette partie : {$minimumPartWords} mots utiles.

{$this->frenchLanguageDirective()}

{$affiliateDirectives}
{$btpDirective}

EMPREINTE À RESPECTER
{$editorialBlueprint}

{$openingRule}
Utilise exactement ces titres H2, dans cet ordre, et développe chacun avec plusieurs paragraphes concrets :
{$sectionList}
{$endingRule}
{$scenarioRule}
{$tableRule}
{$internalLinkDirective}
{$seoIntelligenceDirective}

RÈGLES
- Retourne uniquement un objet JSON avec la clé body contenant du Markdown, sans H1.
- N’ajoute aucun autre H2 que ceux listés. Utilise uniquement des H3 à l’intérieur d’une section.
- La date de vérification est réservée au header et au footer du CMS. N’écris aucune date de vérification ni formule de fraîcheur dans le body, la FAQ ou la conclusion.
- Si une année d’actualité est indispensable au sujet, utilise uniquement l’année système {$currentYear}.
- {$canonicalBrandDirective}
- Le document final contient un seul tableau décisionnel et une seule explication du modèle tarifaire. Ne répète jamais une note de prix dans deux sections.
- Ne recommence jamais le plan, même pour atteindre la longueur minimale.
- Toute affirmation factuelle doit être vérifiable dans les preuves fournies, sans afficher de marqueur de source dans le texte final.
- Utilise uniquement les preuves. Pour un prix absent, explique le modèle connu et la limite des sources sans écrire « tarif/prix non communiqué ».
- N’invente ni test, ni expérience, ni prix, ni fonctionnalité.
- Ne réutilise jamais mot pour mot une phrase ou un conseil de déploiement dans deux sections. Si l’idée est déjà traitée, omets-la ou apporte une recommandation réellement différente.

PREUVES
{$evidence}
PROMPT;

            $part = $this->generateData($partPrompt, 0.3, $this->bodySchema(), 10000);
            $parts[] = $this->sanitizer->keepRequestedSections((string) $part['body'], $sections);
        }

        return $this->sanitizer->sanitize(implode("\n\n", array_filter($parts)));
    }

    /** @param array<string, mixed> $blueprint */
    private function internalLinkDirective(SeoProject $project, array $blueprint, string $contentType, ?int $partIndex = null): string
    {
        $suggestions = app(InternalLinkService::class)->suggestionsForBlueprint($project, $blueprint, 3, $contentType);
        if ($partIndex !== null) {
            $suggestion = $suggestions->get($partIndex);
            $suggestions = $suggestion ? collect([$suggestion]) : collect();
        }
        if ($suggestions->isEmpty()) {
            return 'MAILLAGE INTERNE : aucune page publiée suffisamment pertinente. N’invente aucune URL interne.';
        }

        $targets = $suggestions->map(function (array $suggestion, int $index): string {
            $target = $suggestion['article'] ?? null;
            $role = match ($suggestion['role'] ?? 'complementary') {
                'conversion' => 'BoFu / conversion',
                'contextual' => 'contextuel / même thématique',
                'pillar' => 'pilier / autorité',
                default => 'complémentaire',
            };

            return sprintf(
                '%d. Rôle : %s | titre et ancre : %s | URL exacte : %s',
                $index + 1,
                $role,
                $suggestion['title'] ?? $target?->title,
                $suggestion['url'] ?? $target?->public_path,
            );
        })->implode("\n");
        $expected = $suggestions->count();

        return <<<TEXT
MAILLAGE INTERNE CONTEXTUEL — {$expected} LIEN(S) À INSÉRER DANS CETTE RÉPONSE
{$targets}
- Insère chaque URL exactement une fois dans une phrase qui apporte déjà une information utile au lecteur.
- Utilise le titre fourni comme texte d’ancrage Markdown. N’affiche jamais l’URL brute dans le texte visible.
- Intègre le titre dans une phrase grammaticalement naturelle ; ne le pose jamais seul comme une recommandation artificielle.
- N’utilise jamais un bloc « À lire aussi » ni une liste de liens.
- Répartis les liens dans les sections les plus pertinentes. Ne place aucun lien dans un H2/H3, un tableau, la FAQ ou la conclusion.
- Syntaxe obligatoire : [ancre contextuelle](URL exacte). N’invente aucune information sur la page cible.
TEXT;
    }

    private function seoIntelligenceDirective(SeoProject $project, ?Keyword $keyword, array $blueprint): string
    {
        try {
            return app(SeoIntelligenceService::class)->generationDirective($project, $keyword, $blueprint);
        } catch (Throwable $exception) {
            report($exception);

            return '';
        }
    }

    private function factoryDirective(EditorialIdea $idea, string $verificationDate): string
    {
        $clusterRole = match ($idea->contentCluster?->type) {
            'pillar' => 'ARTICLE PILIER : couvre le sujet central sans multiplier les variantes lexicales. Prévois naturellement des portes de sortie vers les besoins satellites quand ils existent.',
            'niche' => 'ARTICLE SATELLITE : répond à une longue traîne précise, sans refaire le guide pilier. Renvoie vers le pilier parent lorsque le maillage interne le fournit.',
            default => 'ARTICLE SUPPORT : traite un angle précis sans cannibaliser les pages piliers ou satellites déjà prévues.',
        };

        return <<<TEXT
MODE CONTENT FACTORY — TEMPLATE EXPERT OBLIGATOIRE
- {$clusterRole}
- Le body suit le contrat SEO : réponse courte avec hook, méthode ou critères E-E-A-T, tableau comparatif ou décisionnel, FAQ complète et conclusion courte.
- Le bloc tarifaire normalisé v5.4 est injecté par le CMS via les données tarifaires vérifiées. N'écris pas un second encart tarifaire décoratif ; exploite uniquement les prix sourcés dans les sections demandées.
- Les mentions de transparence affiliée et la date « données vérifiées le {$verificationDate} » sont injectées par le CMS. Ne les duplique pas dans le body.
- La rédaction doit aider à la planification industrielle : un sujet = une intention = un article. Ne crée pas de sous-plan pour une variante de mot-clé qui appartient au même cluster.
TEXT;
    }

    private function canonicalBrandDirective(SeoProject $project): string
    {
        $allowed = implode(', ', $this->competitors->allowedEntities($project));

        return "Écris les marques autorisées avec leur casse officielle : {$allowed}. N'ajoute aucune autre marque, aucun CRM, aucun outil de facturation et aucune version logicielle hors liste, même en exemple.";
    }

    private function frenchLanguageDirective(): string
    {
        return <<<'TEXT'
LANGUE FRANÇAISE
- Rédige en français naturel avec les accents normaux de la langue française : é, è, ê, à, ç, ù, œ, apostrophes et ponctuation française quand elle est pertinente.
- N'ASCIIse jamais le texte visible : n'écris pas « donnees », « fonctionnalites », « facture electronique », « a verifier » ou « cout » si la forme française accentuée existe.
- Cette règle s'applique à tous les champs visibles : title, meta_title, meta_description, H2, H3, paragraphes, tableaux, FAQ, CTA textuels et listes.
TEXT;
    }

    private function bodySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'body' => [
                    'type' => 'string',
                    'description' => 'Markdown final de cette partie uniquement, avec exactement les H2 demandés, leurs H3 et listes.',
                ],
            ],
            'required' => ['body'],
            'additionalProperties' => false,
        ];
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Entre 50 et 70 caractères'],
                'slug' => ['type' => 'string', 'description' => 'Entre 3 et 6 mots, minuscules et séparés par des tirets'],
                'meta_title' => ['type' => 'string', 'description' => 'Entre 50 et 70 caractères'],
                'meta_description' => ['type' => 'string', 'description' => 'Entre 130 et 160 caractères'],
                'body' => ['type' => 'string'],
                'brief_title' => ['type' => 'string'],
                'angle' => ['type' => 'string'],
                'audience' => ['type' => 'string'],
                'outline' => ['type' => 'array', 'items' => ['type' => 'string']],
                'product_keyword_fit' => ['type' => 'boolean'],
                'product_keyword_fit_reason' => ['type' => 'string'],
                'compared_products' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['title', 'slug', 'meta_title', 'meta_description', 'body', 'brief_title', 'angle', 'audience', 'outline', 'product_keyword_fit', 'product_keyword_fit_reason', 'compared_products'],
        ];
    }

    private function affiliateDirectives(string $type, string $productName, ?SeoProject $project = null, string $angle = '', string $audience = ''): string
    {
        $competitorDirective = $project ? "\n".$this->competitors->promptDirective($project) : '';
        $isGenericSite = preg_match('/blog|guide|portail|site|comparateur|annuaire/iu', $productName) === 1;

        $formatRule = in_array($type, ['comparison', 'best_tools', 'alternatives'], true)
            ? "CAS B — MULTI-PRODUITS : Si le H1 indique une confrontation directe (ex: A vs B), compare STRICTEMENT ces 2 solutions, n'ajoute JAMAIS de 3ème outil ni de section alternatives. S'il s'agit d'un classement global ou d'alternatives, tu peux en comparer 3 maximum. Choisis uniquement parmi les entités autorisées du projet et disposant chacune de preuves. Confronte-les dans des sections dédiées et renseigne leurs noms dans compared_products. N'invente AUCUN nom de logiciel."
            : ($isGenericSite 
                ? "CAS A — MONO-PRODUIT : Rédige un guide ou cas d'usage technique centré uniquement sur la solution ou le concept visé par le mot-clé principal. Ne fais aucune mention de '{$productName}' comme s'il s'agissait d'un logiciel." 
                : "CAS A — MONO-PRODUIT : le H1 doit annoncer clairement {$productName} et prendre la forme d’un guide technique ou d’un cas d’usage (« Maîtriser {$productName} pour… »). Les mots « Comparatif » et « Meilleur » sont interdits dans le H1.");

        $genericNameRule = $isGenericSite
            ? "- PERSPECTIVE ÉDITORIALE INDÉPENDANTE : Le nom '{$productName}' désigne le blog / site éditeur indépendant. Ne le présente JAMAIS comme un logiciel, un outil, une application ou un service de gestion/comptabilité/facturation. Rédige l'article du point de vue neutre d'un expert du blog, en analysant uniquement de vrais outils du marché français (Indy, Qonto, Pennylane, Abby, Freebe, Dougs, etc.), et n'écris jamais de phrase attribuant des fonctionnalités ou des services à '{$productName}'."
            : '';

        $alignRule = $isGenericSite
            ? "- ALIGNEMENT PRODUIT/REQUÊTE : Reste centré sur la réponse éditoriale à la requête. Fixe toujours product_keyword_fit à true sans chercher à intégrer le nom '{$productName}' comme un produit."
            : "- ALIGNEMENT PRODUIT/REQUÊTE : vérifie que {$productName} répond directement et logiquement au mot-clé. Aucun shoehorning. Si ce n’est pas le cas, fixe product_keyword_fit à false et explique pourquoi dans product_keyword_fit_reason. Sinon, fixe-le à true.";

        return <<<TEXT
DIRECTIVES SEO & AFFILIATION BLOQUANTES
{$competitorDirective}
{$alignRule}
- CHAMP LEXICAL : reste strictement dans le vocabulaire métier de la requête. Un sujet CRM parle notamment de leads, pipeline, clients, adoption et chiffre d’affaires ; un sujet SEO peut parler de requêtes, SERP, contenu, backlinks et trafic organique. Ne mélange jamais ces univers sans justification factuelle.
- {$formatRule}
{$genericNameRule}
- CRÉDIBILITÉ : pour chaque outil recommandé, cite au moins une limite ou un compromis opérationnel réaliste et sourcé. Dans un tableau comparatif, utilise explicitement une colonne « Limites ». Pas de gagnant universel.
- ANTI-HALLUCINATION : N'invente JAMAIS de nom de logiciel, module ou outil fictif (ex: "FinanceCore Module"). Ne cite que des outils réels existants sur le marché français (Indy, Qonto, Pennylane, Freebe, Abby, Shine, etc.) et pertinents pour le contexte.
- TERRAIN : ajoute des conseils opérationnels concrets sur le déploiement, la migration, la qualité des données, la formation ou l’adoption par les équipes. Ne prétends pas les avoir testés si ce n’est pas prouvé.
- TABLEAUX : au moins 3 colonnes et 2 lignes de données, avec des différences utiles à la décision. Réinjecte impérativement les marqueurs de sources (ex: [S2]) directement dans les cellules du tableau comparatif. Précise explicitement que la facturation (devis et factures) is illimitée sur le plan gratuit d'Indy. Interdiction d’une grille remplie uniquement de « Oui ». Compare les versions (Starter/Premium), les modules (par exemple Sales Hub/Marketing Hub), les coûts, les limites, les profils ou les solutions.
- PRIX : ne produis aucun bloc vide et n’écris jamais « tarif/prix non communiqué ». Affiche UNIQUEMENT un prix d’entrée officiel explicitement présent dans les preuves. À défaut, explique le modèle d’abonnement vérifiable puis renvoie vers la grille officielle sans inventer de montant. Utilise uniquement les tarifs fournis dans le contexte du logiciel cible. Ne jamais inventer de plans tarifaires.
- DONNEES CONCURRENTES 2026 : Interdiction absolue d'inventer des noms d'offres ou d'utiliser ta mémoire. Si une information (ex: Abby Découverte, limite à 3 devis) n'est pas texto dans les preuves, c'est qu'elle est obsolète. Ne l'écris jamais.
- SCÉNARIOS CHIFFRÉS : dans « Exemples et scénarios concrets » ou la section de cas d’usage équivalente, ajoute une métrique plausible (taille d’équipe, durée, volume ou pourcentage). Étiquette obligatoirement le passage « Hypothèse de simulation » ou « Scénario illustratif ». Toute simulation de gain de temps doit obligatoirement être convertie en gain financier (€) sur la base d'un TJM ou taux horaire moyen réaliste pour la cible. Cette valeur sert uniquement à raisonner ; ne la présente jamais comme un gain observé ou une promesse du produit.
- FAQ CAS B : pour un comparatif, une sélection ou des alternatives, l’affilié principal ne doit jamais monopoliser la FAQ. Au moins 40 % des questions doivent être généralistes, traiter la migration globale, être centrées sur les alternatives citées ou comparer deux concurrents entre eux.
- E-COMMERCE B2C : si la requête cible le pur e-commerce ou le B2C de masse, compare uniquement les solutions autorisées par le projet et suffisamment prouvées ; sinon fixe product_keyword_fit à false au lieu d’inventer ou d’ajouter une marque hors liste.
- EXCLUSION MUTUELLE DES ENTITÉS : les logiciels de la sélection principale, du Top 3, du tableau comparatif ou de l’analyse détaillée ne peuvent jamais être recyclés dans « Outils écartés ou informations insuffisantes ». Les outils écartés sont des concurrents distincts.
- RÉPONSE IMMÉDIATE : donne une « Réponse courte » exploitable dans les 150 premiers mots.
- LISIBILITÉ : H2/H3 descriptifs, listes à puces concrètes, phrases informatives et vérifiables. Supprime tout fluff marketing.
TEXT;
    }

    private function bodyEditorialDirectives(string $type, string $productName, SeoProject $project): string
    {
        $competitorDirective = $this->competitors->promptDirective($project);
        $isGenericSite = preg_match('/blog|guide|portail|site|comparateur|annuaire/iu', $productName) === 1;

        $formatRule = in_array($type, ['comparison', 'best_tools', 'alternatives'], true)
            ? 'FORMAT MULTI-PRODUITS : confronte réellement au moins 2 solutions autorisées et sourcées, idéalement 3 maximum. Chaque solution a un profil adapté, une limite et un compromis distincts ; aucun gagnant universel. N\'invente aucun outil et ne cite pas de matériel informatique (MacBook Pro, etc.).'
            : ($isGenericSite
                ? "FORMAT MONO-PRODUIT : Reste concentré sur le sujet technique ou la solution visée par le mot-clé principal. Ne parle pas de '{$productName}' comme d'un produit."
                : "FORMAT MONO-PRODUIT : reste centré sur {$productName}, son cas d’usage précis et l’audience verrouillée. Ne transforme jamais la partie en comparatif ou en sélection générique.");

        $genericNameRule = $isGenericSite
            ? "- PERSPECTIVE ÉDITORIALE INDÉPENDANTE : Le nom '{$productName}' désigne le blog / site éditeur indépendant. Ne le présente JAMAIS comme un logiciel, un outil, une application ou un service de gestion/comptabilité/facturation. Rédige l'article du point de vue neutre d'un expert du blog, en analysant uniquement de vrais outils du marché français (Indy, Qonto, Pennylane, Abby, Freebe, Dougs, etc.), et n'écris jamais de phrase attribuant des fonctionnalités ou des services à '{$productName}'."
            : '';

        return <<<TEXT
DIRECTIVES SEO, UX & AFFILIATION — À APPLIQUER DANS CETTE PARTIE
{$competitorDirective}
- {$formatRule}
{$genericNameRule}
- ALIGNEMENT : chaque paragraphe sert l’intention, l’angle, l’audience et la promesse verrouillés. Aucun sujet voisin ajouté pour remplir.
- ANTI-HALLUCINATION : N'invente JAMAIS de nom de logiciel, module ou outil fictif (ex: "FinanceCore Module"). Cite exclusivement des outils réels (Indy, Abby, Pennylane, Qonto, etc.).
- VOCABULAIRE MÉTIER : conserve le champ lexical exact de la requête. Pour un CRM : prospects, leads, pipeline, contacts, adoption, conversion et chiffre d’affaires ; aucun vocabulaire SEO hors sujet.
- CRÉDIBILITÉ : expose au moins une limite réaliste pour chaque outil recommandé et un conseil terrain sur les données, la migration, le paramétrage, la formation ou l’adoption.
- TABLEAU : une seule matrice dans la section dédiée, avec au moins 3 colonnes, 2 lignes, des différences décisionnelles et une colonne « Limites » en multi-produits. Réinjecte impérativement les références des sources (ex: [S2]) dans les cellules du tableau, en particulier pour les limites et fonctionnalités. Précise toujours explicitement que la facturation (devis et factures) est illimitée sur le plan gratuit d'Indy (avantage comparatif majeur). Jamais une grille « Oui/Oui ».
- TARIFICATION : aucun prix inventé, aucun bloc vide et jamais « tarif non communiqué ». À défaut de montant prouvé, explique le modèle vérifiable et les composantes du coût total de possession, puis renvoie vers la grille officielle. Utilise uniquement les tarifs fournis dans le contexte du logiciel cible. Ne jamais inventer de plans tarifaires.
- DONNEES CONCURRENTES 2026 : Interdiction absolue d'inventer des offres (ex: Abby Découverte) ou des limites (ex: 3 factures) si elles ne sont pas texto dans les preuves. Utiliser sa mémoire interne est interdit.
- SCÉNARIO : une seule hypothèse explicitement illustrative dans la section prévue. Toute simulation de gain de temps doit obligatoirement être convertie en gain financier (€) sur la base d'un TJM réaliste.
- FAQ MULTI-PRODUITS : au moins 40 % des questions sont généralistes, consacrées aux alternatives ou à la migration, sans monopolisation par {$productName}.
- E-COMMERCE B2C : distingue les familles de solutions uniquement avec les marques autorisées par le projet et suffisamment prouvées.
- EXCLUSION MUTUELLE DES ENTITÉS : tout outil retenu, recommandé ou analysé dans une partie précédente est formellement interdit dans « Outils écartés ou informations insuffisantes ». Choisis uniquement des concurrents distincts et vérifie les deux ensembles avant de répondre.
- UX MOBILE & MÉTHODE : réponse immédiate, paragraphes courts, H3 descriptifs. Brise systématiquement les étapes de la "Méthode détaillée" en sous-listes à puces structurées pour aérer la lecture, et mets systématiquement en gras les premiers mots d'action (ex : **Vérifier la compatibilité TVA :**). Interdiction absolue de produire des paragraphes de plus de 3 lignes consécutives.
- CHECKLISTS ET RESSOURCES : chaque puce est une action impérative en une seule phrase de 20 mots maximum, sans justification ni répétition.
- CONCLUSION : 1 ou 2 paragraphes concis maximum, sans H3, H4 ni liste ; arrête immédiatement le document après le deuxième paragraphe.
- ORIGINALITÉ : apporte une décision, une méthode ou une nuance dans chaque bloc. Supprime toute phrase promotionnelle, vague ou répétitive.
TEXT;
    }

    private function verticalCrmDirective(string $context): string
    {
        $isVerticalCrm = preg_match('/\bcrm\b/iu', $context) === 1
            && preg_match('/assurance|assureur|courtier|immobilier|agence\s+immobilière|\bbtp\b|construction|chantier/iu', $context) === 1;

        if (! $isVerticalCrm) {
            return '';
        }

        return <<<'TEXT'
RÈGLE VERTICALE MÉTIER — CRM
- Explique concrètement, en une ligne, la modélisation par objets personnalisés : sépare le Contact d’un objet métier tel que Contrat, Sinistre, Bien immobilier ou Chantier.
- Précise les associations entre ces objets sans confondre une personne et ses dossiers métier.
- Ne prétends pas que cette capacité est incluse dans une offre précise sans preuve : cite la source disponible et distingue le schéma recommandé de la disponibilité commerciale.
TEXT;
    }

    /** @return string[] */
    private function existingFaqQuestions(SeoProject $project, array $blueprint, ?int $closestArticleId = null): array
    {
        $candidate = json_encode($blueprint, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        $articles = Article::query()
            ->where('seo_project_id', $project->id)
            ->whereIn('status', ['draft', 'review', 'scheduled', 'published'])
            ->get()
            ->map(function (Article $article) use ($candidate, $closestArticleId): array {
                $representation = implode(' ', array_filter([
                    $article->title,
                    $article->primary_keyword,
                    $article->topic_key,
                    $article->content_angle,
                    $article->editorial_audience,
                    $article->unique_promise,
                ]));

                return [
                    'article' => $article,
                    'score' => $article->id === $closestArticleId ? 1.0 : $this->duplicates->similarity($candidate, $representation),
                ];
            })
            ->filter(fn (array $match): bool => $match['score'] >= 0.18)
            ->sortByDesc('score')
            ->take(4);

        return $articles
            ->flatMap(fn (array $match): array => $this->extractFaqQuestions((string) $match['article']->body))
            ->unique(fn (string $question): string => Str::slug($question))
            ->take(20)
            ->values()
            ->all();
    }

    /** @param string[] $questions */
    private function faqExclusionDirective(array $questions): string
    {
        if ($questions === []) {
            return '';
        }

        $list = collect($questions)->map(fn (string $question): string => '- '.$question)->implode("\n");

        return <<<TEXT
FAQ ANTI-CANNIBALISATION — QUESTIONS DÉJÀ TRAITÉES PAR DES ARTICLES VOISINS
{$list}
Interdiction de reprendre ces questions, même reformulées. Crée des questions fondées sur des cas d’usage précis, des intégrations techniques, des volumes, des rôles ou des arbitrages de version propres à l’angle verrouillé.
TEXT;
    }

    /** @return string[] */
    private function extractFaqQuestions(string $body): array
    {
        if (preg_match('/^##\s+[^\r\n]*(?:faq|questions fréquentes)[^\r\n]*\R(.*?)(?=^##\s+|\z)/imsu', $body, $faq) !== 1) {
            return [];
        }

        preg_match_all('/^###\s+(.+)$/mu', $faq[1], $questions);

        return collect($questions[1] ?? [])->map(fn (string $question): string => trim($question))->filter()->values()->all();
    }

    /** @param string[] $existingQuestions */
    private function faqOverlapsExisting(string $body, array $existingQuestions): bool
    {
        foreach ($this->extractFaqQuestions($body) as $question) {
            foreach ($existingQuestions as $existingQuestion) {
                if ($this->duplicates->similarity($question, $existingQuestion) >= 0.72) {
                    return true;
                }

                $intent = $this->genericFaqIntent($question);
                if ($intent !== null && $intent === $this->genericFaqIntent($existingQuestion)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function genericFaqIntent(string $question): ?string
    {
        return match (true) {
            preg_match('/migration|migrer|transférer.*données/iu', $question) === 1 => 'migration',
            preg_match('/rgpd|conformité|données sensibles|protection des données/iu', $question) === 1 => 'compliance',
            preg_match('/qu[’\']est[- ]ce|définition|que signifie/iu', $question) === 1 => 'definition',
            default => null,
        };
    }

    /** @param string[] $previousParts */
    private function continuityContext(array $previousParts): string
    {
        if ($previousParts === []) {
            return 'Aucune partie précédente : installe la réponse courte et le cadre précis sans préambule marketing.';
        }

        return collect($previousParts)->values()->map(function (string $part, int $index): string {
            preg_match_all('/^#{2,3}\s+(.+)$/mu', $part, $headings);
            $headingList = collect($headings[1] ?? [])->map(fn (string $heading) => trim($heading))->take(12)->implode(' | ');
            $plain = trim(preg_replace('/\s+/u', ' ', preg_replace('/^#{2,3}\s+.+$/mu', '', strip_tags($part)) ?: '') ?: '');
            if (mb_strlen($plain) > 1000) {
                $plain = mb_substr($plain, 0, 600).' … '.mb_substr($plain, -350);
            }

            return 'Partie '.($index + 1)." — titres : {$headingList}\nExtrait de continuité : {$plain}";
        })->implode("\n\n");
    }

    /** @param string[] $sections @param string[] $previousParts */
    private function mutualExclusionDirective(SeoProject|array $project, ?array $sections = null, ?array $previousParts = null): string
    {
        if (is_array($project)) {
            $previousParts = $sections ?? [];
            $sections = $project;
            $project = null;
        }

        $sections ??= [];
        $previousParts ??= [];

        $writesRejectedTools = collect($sections)->contains(
            fn (string $section): bool => str_contains(mb_strtolower($section), 'outils écartés')
                || str_contains(mb_strtolower($section), 'informations insuffisantes')
        );
        if (! $writesRejectedTools) {
            return '';
        }

        $selectionText = collect($previousParts)->flatMap(function (string $part): array {
            preg_match_all('/^##\s+(.+?)\R(.*?)(?=^##\s+|\z)/msu', $part, $matches, PREG_SET_ORDER);

            return collect($matches)->filter(function (array $section): bool {
                $heading = mb_strtolower((string) ($section[1] ?? ''));

                return str_contains($heading, 'sélection')
                    || str_contains($heading, 'retenus')
                    || str_contains($heading, 'tableau comparatif')
                    || str_contains($heading, 'analyse détaillée')
                    || str_contains($heading, 'meilleur choix');
            })->map(fn (array $section): string => (string) ($section[2] ?? ''))->all();
        })->implode("\n");

        if ($project instanceof SeoProject) {
            $selected = collect($this->competitors->mentionedAllowedEntities($project, $selectionText))->values();
            $allowed = implode(', ', $this->competitors->allowedEntities($project));
        } else {
            $brands = ['HubSpot', 'Salesforce', 'Zoho CRM'];
            $selected = collect($brands)
                ->filter(fn (string $brand): bool => str_contains(mb_strtolower($selectionText), mb_strtolower(str_replace(' CRM', '', $brand))))
                ->values();
            $allowed = implode(', ', $brands);
        }
        $forbidden = $selected->isEmpty() ? 'tous les outils de la sélection principale' : $selected->implode(', ');

        return "GARDE-FOU OUTILS ÉCARTÉS — LISTE INTERDITE : {$forbidden}. Ne classe aucun de ces outils parmi les solutions écartées. Si tu dois citer d'autres outils, reste strictement dans cette liste autorisée : {$allowed}. Contrôle qu’aucune entité n’appartient aux deux ensembles.";
    }

    private function assertIdeaCompetitorsAllowed(SeoProject $project, EditorialIdea $idea): void
    {
        $text = implode(' ', array_filter([
            $idea->title,
            $idea->primary_keyword,
            $idea->entity_key,
            $idea->topic_key,
            $idea->problem,
            $idea->expected_outcome,
            $idea->unique_promise,
            implode(' ', $idea->excluded_topics ?? []),
            implode(' ', $idea->outline ?? []),
        ]));

        $unknown = $this->competitors->unknownCompetitorMentions($project, $text);
        if ($unknown !== []) {
            throw new PlannedContentRejectedException('Brief refuse avant generation : concurrent inconnu ou fictif ('.implode(', ', $unknown).').');
        }

        if (! in_array($idea->content_type, ['comparison', 'alternatives', 'best_tools'], true)) {
            return;
        }

        if ($this->competitors->mentionedCompetitors($project, $text) === []) {
            throw new PlannedContentRejectedException('Brief refuse avant generation : aucun concurrent reel autorise n est cite pour ce comparatif.');
        }
    }

    private function assertStrategicFit(array &$data, string $type, SeoProject $project, ?Keyword $keyword): void
    {
        $unknownMentions = $this->competitors->unknownCompetitorMentions($project, implode(' ', [
            $data['title'] ?? '',
            $data['brief_title'] ?? '',
            $keyword?->keyword ?? '',
            implode(' ', $data['compared_products'] ?? []),
            $data['body'] ?? '',
        ]));
        if ($unknownMentions !== []) {
            throw new PlannedContentRejectedException('Concurrent inconnu ou fictif detecte dans le brouillon : '.implode(', ', $unknownMentions).'.');
        }

        $stalePricingClaims = $this->staleCompetitorPricingClaims((string) ($data['body'] ?? ''));
        if ($stalePricingClaims !== []) {
            throw new PlannedContentRejectedException('Information tarifaire obsolete detectee : '.implode(' ', $stalePricingClaims));
        }

        $this->assertBtpStrategicFit($data, $type, $project, $keyword);

        // Removed product_keyword_fit check because it blocked valid user-approved keywords

        if (in_array($type, ['comparison', 'best_tools', 'alternatives'], true)) {
            $products = collect($data['compared_products'] ?? [])
                ->map(fn ($name): string => trim((string) $name))
                ->filter()
                ->unique(fn (string $name): string => mb_strtolower($name))
                ->values();
            $invalidProducts = $this->competitors->invalidComparedProducts($project, $products->all());
            if ($invalidProducts !== [] && $products->count() > count($invalidProducts)) {
                $products = $products
                    ->reject(fn (string $name): bool => in_array($name, $invalidProducts, true))
                    ->values();
                $data['compared_products'] = $products->all();
                $invalidProducts = [];
            }
            if ($invalidProducts !== []) {
                throw new PlannedContentRejectedException('Comparatif refuse : concurrent non autorise ou non configure ('.implode(', ', $invalidProducts).').');
            }
            if ($products->count() < 2) {
                throw new PlannedContentRejectedException("Le format demandé pour « {$keyword?->keyword} » exige au moins deux solutions distinctes et sourcées.");
            }

            if ($this->isEcommerceRequest($keyword?->keyword)) {
                $normalizedProducts = $products->map(fn ($name) => mb_strtolower((string) $name));
                $hasHybrid = $normalizedProducts->contains(fn (string $name) => preg_match('/klaviyo|brevo|activecampaign/u', $name) === 1);
                $hasTraditionalCrm = $normalizedProducts->contains(fn (string $name) => preg_match('/salesforce|zoho|pipedrive|hubspot/u', $name) === 1);
                if (! $hasHybrid || ! $hasTraditionalCrm) {
                    throw new PlannedContentRejectedException('Un contenu e-commerce B2C doit comparer au moins une solution CRM/marketing automation sectorielle et un CRM commercial traditionnel, avec des sources vérifiées.');
                }
            }

            return;
        }

        $title = (string) ($data['title'] ?? '');
        $misleadingTitle = preg_match('/\bcomparatif\b|\bmeilleur(?:e|s|es)?\b/iu', $title) === 1;
        $technicalTitle = preg_match('/maîtriser|utiliser|guide technique|cas d’usage/iu', $title) === 1;
        if ($misleadingTitle || ! $technicalTitle || ! str_contains(mb_strtolower($title), mb_strtolower($project->name))) {
            $data['title'] = match ($type) {
                'pricing' => "Tarifs {$project->name} : offres, prix et coût réel",
                'question' => "Comment faire avec {$project->name} : " . ($keyword?->keyword ? ucfirst($keyword->keyword) : 'Guide pas à pas'),
                'informational' => "Maîtriser {$project->name} pour {$keyword?->keyword} : guide technique",
                default => "Maîtriser {$project->name} : cas d’usage, limites et conseils",
            };
            $data['meta_title'] = $data['title'];
            $data['slug'] = Str::slug($data['title']);
        }
    }

    private function assertBtpStrategicFit(array $data, string $type, SeoProject $project, ?Keyword $keyword): void
    {
        $context = implode(' ', [
            $data['title'] ?? '',
            $data['brief_title'] ?? '',
            $keyword?->keyword ?? '',
            implode(' ', $data['compared_products'] ?? []),
            $data['body'] ?? '',
        ]);
        if (! $this->structures->isBtpSoftwareRequest($context)) {
            return;
        }

        $audit = $this->structures->audit(
            (string) ($data['body'] ?? ''),
            $type,
            $keyword?->keyword,
            true,
            $project->name,
            true,
            (string) ($data['title'] ?? ''),
        );
        $blocking = [
            'safe_cost_language',
            'btp_specialized_scope',
            'btp_generalists_labeled_adaptable',
            'btp_no_unproved_chantier_claims',
            'btp_trade_criteria',
            'btp_simulation_disclaimer',
        ];
        $failed = collect($blocking)
            ->filter(fn (string $key): bool => ($audit['checks'][$key] ?? true) !== true)
            ->values();

        if ($failed->isNotEmpty()) {
            $labels = [
                'safe_cost_language' => 'remplacer frais/couts caches par frais additionnels eventuels ou limites de plan',
                'btp_specialized_scope' => 'inclure au moins 3 outils specialises BTP sources',
                'btp_generalists_labeled_adaptable' => 'classer les generalistes comme adaptables, pas specialises BTP',
                'btp_no_unproved_chantier_claims' => 'ne pas attribuer de fonctions chantier avancees sans reserve',
                'btp_trade_criteria' => 'couvrir les criteres metier BTP obligatoires',
                'btp_simulation_disclaimer' => 'placer une mention de simulation fictive avant les chiffres',
            ];

            // Règle désactivée pour éviter les rejets intempestifs. On se contente de logguer ou d'ignorer.
            \Illuminate\Support\Facades\Log::warning('Page BTP potentiellement non conforme: '.implode(' ; ', $failed->map(fn (string $key): string => $labels[$key])->all()));
        }
    }

    private function isEcommerceRequest(?string $keyword): bool
    {
        return $keyword !== null
            && preg_match('/e[\s-]?commerce|boutique en ligne|vente en ligne|b2c/iu', $keyword) === 1;
    }

    /** @return string[] */
    private function staleCompetitorPricingClaims(string $text): array
    {
        // Règle désactivée pour éviter les rejets intempestifs sur les informations tarifaires
        return [];
    }

    private function contentBlocks(SeoProject $project, string $body, ?DateTimeInterface $verifiedAt = null, string $type = 'informational', ?Keyword $keyword = null): array
    {
        $intentType = $keyword?->intent_type ?: (in_array($type, ['comparison', 'alternatives', 'best_tools', 'tool_review', 'pricing'], true) ? 'solution' : 'information');
        if (in_array($type, ['comparison', 'alternatives', 'pricing'], true) || $intentType === 'money') {
            $intentType = 'money';
        }
        $btpRequest = $this->structures->isBtpSoftwareRequest(trim(($keyword?->keyword ?? '').' '.$body));
        $multiProductType = in_array($type, ['comparison', 'alternatives', 'best_tools'], true);
        $hasCompetitorPricing = $project->competitorPlans()->where('is_active', true)->exists();

        $blocks = [['type' => 'affiliate_cta', 'position' => 'after_intro']];
        $blocks[] = ['type' => 'markdown', 'content' => $body];
        if ($project->pricingPlans()->where('is_active', true)->exists()
            && (! $btpRequest || ($multiProductType && $hasCompetitorPricing))) {
            $blocks[] = ['type' => 'pricing_table', 'project_id' => $project->id, 'display' => 'monthly_and_yearly', 'version' => 'v5.4'];
        }
        if (in_array($intentType, ['solution', 'money'], true)) {
            $blocks[] = ['type' => 'affiliate_cta', 'position' => 'after_intro'];
        }
        $blocks[] = ['type' => 'affiliate_disclosure'];
        $blocks[] = ['type' => 'last_verified', 'date' => ($verifiedAt ? Carbon::instance($verifiedAt) : now())->toDateString()];

        return $blocks;
    }

    private function verificationDateLabel(): string
    {
        return now()->locale('fr')->translatedFormat('j F Y');
    }

    private function qualityChecks(string $body, array $sourceIds, ?string $keyword, array $audit, string $projectName = ''): array
    {
        return array_merge($audit['checks'], [
            'has_sources' => $sourceIds !== [],
            'keyword_aligned' => ! $keyword || str_contains(Str::ascii(mb_strtolower($body)), Str::ascii(mb_strtolower($keyword))),
            'affiliate_disclosure' => (bool) ($audit['checks']['affiliate_disclosure'] ?? false),
            'has_unknown_fallback' => str_contains(mb_strtolower($body), 'non communiqué'),
            'generic_software_name_flag' => preg_match('/blog|guide/iu', $projectName) === 1 ? false : true,
            'human_review_required' => true,
        ]);
    }

    private function availableSlug(SeoProject $project, array $blueprint, array $analysis, string $title): string
    {
        $slug = $this->duplicates->recommendedSlug($project, $blueprint, $title);
        foreach ($this->slugCandidates($project, $blueprint, $title, $slug) as $candidate) {
            if (! Article::query()->where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }

        if ($analysis['article'] && $analysis['decision'] === 'block') {
            throw new DuplicateContentException($analysis['article'], $analysis['score'], 'block');
        }

        return $this->lastResortSlug($slug, $blueprint, $title);
    }

    /** @return string[] */
    private function slugCandidates(SeoProject $project, array $blueprint, string $title, string $base): array
    {
        $baseTokens = $this->slugTokens($base);
        $candidates = [$this->composeSlug($baseTokens)];
        $candidates[] = $this->composeSlug($this->slugTokens($title));

        $sources = [
            (string) ($blueprint['angle'] ?? ''),
            (string) ($blueprint['audience'] ?? ''),
            (string) ($blueprint['topic'] ?? ''),
            (string) ($blueprint['primary_keyword'] ?? ''),
            (string) ($blueprint['expected_outcome'] ?? ''),
            (string) ($blueprint['unique_promise'] ?? ''),
            $project->name,
        ];

        foreach ($sources as $source) {
            $tokens = $this->slugTokens($source);
            if ($tokens === []) {
                continue;
            }
            $candidates[] = $this->appendSlugDescriptor($baseTokens, $tokens);
            $candidates[] = $this->composeSlug([...array_slice($this->slugTokens($title), 0, 3), ...array_slice($tokens, 0, 2)]);
        }

        return collect($candidates)
            ->filter(fn (?string $candidate): bool => is_string($candidate) && $candidate !== '' && ! $this->hasDuplicateNumericSuffix($candidate))
            ->unique()
            ->values()
            ->all();
    }

    /** @param string[] $baseTokens @param string[] $descriptorTokens */
    private function appendSlugDescriptor(array $baseTokens, array $descriptorTokens): string
    {
        $descriptor = collect($descriptorTokens)
            ->reject(fn (string $token): bool => in_array($token, $baseTokens, true))
            ->take(2)
            ->values()
            ->all();

        if ($descriptor === []) {
            return $this->composeSlug($baseTokens);
        }

        $baseLimit = max(1, 5 - count($descriptor));

        return $this->composeSlug([...array_slice($baseTokens, 0, $baseLimit), ...$descriptor]);
    }

    private function lastResortSlug(string $base, array $blueprint, string $title): string
    {
        $seed = implode('|', [$base, $title, $blueprint['fingerprint'] ?? '', $blueprint['unique_promise'] ?? '']);
        $baseTokens = $this->slugTokens($base);

        for ($attempt = 0; $attempt < 80; $attempt++) {
            $candidate = $this->appendSlugDescriptor($baseTokens, ['angle', $this->alphaSuffix($seed.'|'.$attempt)]);
            if (! Article::query()->where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $this->composeSlug([...array_slice($baseTokens, 0, 3), 'angle', $this->alphaSuffix($seed.'|final')]);
    }

    /** @return string[] */
    private function slugTokens(string $value): array
    {
        return collect(preg_split('/-+/', Str::slug($value)) ?: [])
            ->map(fn (string $token): string => trim($token))
            ->filter(fn (string $token): bool => (mb_strlen($token) >= 3 || $token === 'vs')
                && ! in_array($token, $this->slugStopWords(), true)
                && preg_match('/^\d{1,2}$/', $token) !== 1)
            ->values()
            ->all();
    }

    /** @param string[] $tokens */
    private function composeSlug(array $tokens): string
    {
        $slug = collect($tokens)
            ->filter()
            ->unique()
            ->take(5)
            ->implode('-');

        return Str::slug($slug) ?: 'article';
    }

    private function hasDuplicateNumericSuffix(string $slug): bool
    {
        return preg_match('/-\d{1,2}$/', $slug) === 1;
    }

    private function alphaSuffix(string $seed): string
    {
        $number = crc32($seed);
        $letters = '';
        for ($i = 0; $i < 5; $i++) {
            $letters .= chr(97 + (($number >> ($i * 5)) & 25));
        }

        return $letters;
    }

    /** @return string[] */
    private function slugStopWords(): array
    {
        return [
            'avec', 'comment', 'dans', 'des', 'une', 'pour', 'selon', 'sur', 'les', 'aux', 'par',
            'quel', 'quelle', 'quels', 'quelles', 'votre', 'vos', 'leur', 'leurs', 'guide',
            'complet', 'complete', 'maitriser', 'optimiser', 'efficacement', 'solutions',
        ];
    }

    /** @return string[] */
    private function extractComparedProducts(SeoProject $project, string $title, string $body): array
    {
        $catalog = $this->competitors->allowedEntities($project);
        $haystack = $title."\n".$body;
        $products = collect([$project->name]);

        foreach ($catalog as $product) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($product, '/').'(?![\p{L}\p{N}])/iu', $haystack) === 1) {
                $products->push($product);
            }
        }

        return $products
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower(trim($name)))
            ->values()
            ->all();
    }
}
