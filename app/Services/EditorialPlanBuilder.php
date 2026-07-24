<?php

namespace App\Services;

use App\Models\EditorialIdea;
use App\Models\EditorialPlan;
use App\Models\Keyword;
use App\Models\SeoProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class EditorialPlanBuilder
{
    private const FORBIDDEN_ANGLES = ['general', 'guide-pratique', 'guide-complet', 'presentation-generale', 'vue-ensemble'];

    public function __construct(
        private readonly GeminiEditorialIdeaGenerator $generator,
        private readonly EditorialDuplicateDetector $duplicates,
        private readonly ProductKeywordMatcher $matcher,
        private readonly CompetitorCatalog $competitors,
        private readonly SeoContentStructure $structures,
        private readonly KnowledgeGraphStrategyBuilder $knowledgeGraph,
    ) {}

    public function build(SeoProject $project, ?int $userId, int $requestedCount, string $instructions = ''): EditorialPlan
    {
        $plan = $this->createPlan($project, $userId, $requestedCount, $instructions);

        try {
            while ($plan->status === 'planning') {
                $plan = $this->advance($plan);
            }

            return $plan;
        } catch (\Throwable $exception) {
            if ($plan->fresh()->status === 'planning') {
                $plan->update(['status' => 'failed']);
            }
            throw $exception;
        }
    }

    public function createPlan(SeoProject $project, ?int $userId, int $requestedCount, string $instructions = '', array $keywordScope = [], array $clusterScope = []): EditorialPlan
    {
        return EditorialPlan::query()->create([
            'seo_project_id' => $project->id,
            'user_id' => $userId,
            'name' => 'Plan '.$project->name.' — '.now()->format('d/m/Y H:i'),
            'requested_count' => $requestedCount,
            'instructions' => $instructions ?: null,
            'keyword_scope' => array_values(array_unique(array_map('intval', $keywordScope))) ?: null,
            'content_cluster_scope' => array_values(array_unique(array_map('intval', $clusterScope))) ?: null,
            'status' => 'planning',
        ]);
    }

    public function advance(EditorialPlan $plan): EditorialPlan
    {
        $lock = Cache::lock("editorial-plan-advance:{$plan->id}", 240);
        if (! $lock->get()) {
            return $plan->fresh(['ideas' => fn ($query) => $query->orderBy('position')]);
        }

        try {
            return $this->advanceLocked($plan);
        } finally {
            $lock->release();
        }
    }

    private function advanceLocked(EditorialPlan $plan): EditorialPlan
    {
        $plan->refresh();
        if ($plan->status !== 'planning') {
            return $plan;
        }

        $project = $plan->project;
        $keywords = $this->strategicKeywords($project, $plan->keyword_scope ?? []);
        if ($keywords->isEmpty()) {
            $plan->update(['status' => 'failed']);
            throw new RuntimeException('Aucun mot-clé importé ne correspond réellement au produit.');
        }

        $valid = $plan->ideas()->where('status', 'candidate')->get();
        $reserveTarget = max(2, (int) ceil($plan->requested_count * .2));
        $target = $plan->requested_count + $reserveTarget;
        $maxAttempts = $this->maxAttempts($plan);
        if ($valid->count() >= $target || ($plan->attempts > 0 && $valid->count() >= $plan->requested_count)) {
            return $this->lockPlan($plan, $valid);
        }
        if ($plan->attempts >= $maxAttempts) {
            if ($valid->count() >= $plan->requested_count) {
                return $this->lockPlan($plan, $valid);
            }
            if ($valid->count() > 0) {
                $plan->update(['requested_count' => $valid->count()]);
                return $this->lockPlan($plan, $valid);
            }
            $plan->update(['status' => 'failed']);
            throw new RuntimeException("Aucun angle unique n'a pu être validé après {$maxAttempts} étapes.");
        }

        $priorIdeas = EditorialIdea::query()
            ->whereHas('plan', fn ($query) => $query->where('seo_project_id', $project->id)->whereKeyNot($plan->id))
            ->where(fn ($query) => $this->reusableEditorialIdeas($query))
            ->with('closestArticle')
            ->get();
        $excluded = collect($this->existingFingerprints($project))
            ->merge($plan->ideas()->pluck('fingerprint'))
            ->filter()->unique()->values()->all();
        $usedPrimaryKeywords = $plan->ideas()->pluck('primary_keyword')->filter()->unique()->all();
        $generationKeywords = $keywords->reject(fn (Keyword $k) => in_array($k->keyword, $usedPrimaryKeywords, true));
        if ($generationKeywords->isEmpty()) {
            $generationKeywords = $keywords;
        }

        // En raison de la structure ultra-riche (ProductProfile + RoadmapLevel),
        // générer 8 idées dépasse facilement la limite de 8192 tokens en sortie.
        // On limite à 3 par appel pour rester sécurisé et on itère plus souvent si besoin.
        $missing = $target - $valid->count();
        $desired = min(3, max(2, $missing));
        
        $strategicSubjects = $this->knowledgeGraph->generateSubjects($project);

        $rawIdeas = $this->generator->generate(
            $project,
            $generationKeywords,
            $strategicSubjects,
            $desired,
            $excluded,
            (string) $plan->instructions,
            $plan->attempts + 1,
        );
        $plan->increment('attempts');

        foreach ($rawIdeas as $rawIdea) {
            $plan->increment('candidate_count');
            $blueprint = $this->duplicates->normalizeBlueprint($rawIdea);
            $keyword = $this->sourceKeyword($keywords, $rawIdea, $blueprint);
            $decision = $this->validate($project, $blueprint, $valid->concat($priorIdeas), $keyword);
            $idea = $this->persistCandidate($plan, $blueprint, $rawIdea, $keyword, $decision);

            if ($decision['accepted']) {
                $valid->push($idea);
            } else {
                $plan->increment('rejected_count');
                if ($decision['category'] === 'duplicate') {
                    $plan->increment('duplicate_count');
                } elseif ($decision['category'] === 'weak_angle') {
                    $plan->increment('weak_angle_count');
                } elseif ($decision['category'] === 'source_gap') {
                    $plan->increment('source_gap_count');
                }
            }
        }

        $plan->refresh();
        if ($valid->count() >= $target || $valid->count() >= $plan->requested_count) {
            return $this->lockPlan($plan, $valid);
        }

        return $plan->fresh(['ideas' => fn ($query) => $query->orderByDesc('seo_score')]);
    }

    private function maxAttempts(EditorialPlan $plan): int
    {
        // En générant par batch de 3, un plan de 50 articles nécessite au moins 17 appels sans échec.
        // On donne une marge très large.
        return max(6, (int) ceil($plan->requested_count / 2) + 10);
    }

    private function lockPlan(EditorialPlan $plan, Collection $valid): EditorialPlan
    {
        $priorityMap = [
            'Page pilier' => 1,
            'Page commerciale' => 2,
            'Article comparatif' => 3,
            'Tutoriel' => 4,
            'FAQ' => 5,
            'Article de blog (Longue traîne)' => 6,
        ];

        $ranked = $valid->unique('id')->sortBy(function ($idea) use ($priorityMap) {
            $level = $idea->roadmap_level ?? '';
            $priority = $priorityMap[$level] ?? 99;
            return [$priority, -$idea->seo_score];
        })->values();

        $plan->ideas()->whereIn('status', ['candidate', 'accepted', 'reserve'])->update([
            'status' => 'reserve',
            'position' => null,
        ]);
        foreach ($ranked as $index => $idea) {
            $idea->update([
                'status' => $index < $plan->requested_count ? 'accepted' : 'reserve',
                'position' => $index + 1,
            ]);
        }
        $acceptedCount = $plan->ideas()->where('status', 'accepted')->count();
        $plan->update([
            'accepted_count' => $acceptedCount,
            'status' => 'locked',
            'locked_at' => now(),
        ]);

        return $plan->fresh(['ideas' => fn ($query) => $query->orderBy('position')]);
    }

    public function replacementFor(EditorialPlan $plan, EditorialIdea $rejected): EditorialIdea
    {
        $replacement = $plan->ideas()->where('status', 'reserve')->orderByDesc('seo_score')->first();
        if (! $replacement) {
            $this->replenishReserves($plan);
            $replacement = $plan->ideas()->where('status', 'reserve')->orderByDesc('seo_score')->first();
        }
        if (! $replacement) {
            throw new RuntimeException('Aucun angle de remplacement exploitable n’a été trouvé après trois nouveaux cycles de planification.');
        }

        $rejected->update(['status' => 'rejected']);
        $replacement->update([
            'status' => 'accepted',
            'replacement_for_id' => $rejected->id,
            'position' => $rejected->position,
        ]);

        return $replacement;
    }

    private function replenishReserves(EditorialPlan $plan): void
    {
        $project = $plan->project;
        $keywords = $this->strategicKeywords($project, $plan->keyword_scope ?? []);
        $comparisonPool = $plan->ideas()->whereIn('status', ['accepted', 'generating', 'generated', 'reserve'])->get();
        $excluded = $plan->ideas()->pluck('fingerprint')->filter()->unique()->values()->all();

        $usedPrimaryKeywords = $plan->ideas()->pluck('primary_keyword')->filter()->unique()->all();
        $generationKeywords = $keywords->reject(fn (Keyword $k) => in_array($k->keyword, $usedPrimaryKeywords, true));
        if ($generationKeywords->isEmpty()) {
            $generationKeywords = $keywords;
        }

        $strategicSubjects = $this->knowledgeGraph->generateSubjects($project);

        for ($attempt = 1; $attempt <= 1; $attempt++) {
            $rawIdeas = $this->generator->generate(
                $project,
                $generationKeywords,
                $strategicSubjects,
                3,
                $excluded,
                (string) $plan->instructions,
                $plan->attempts + $attempt,
            );
            $plan->increment('attempts');

            foreach ($rawIdeas as $rawIdea) {
                $plan->increment('candidate_count');
                $blueprint = $this->duplicates->normalizeBlueprint($rawIdea);
                $keyword = $this->sourceKeyword($keywords, $rawIdea, $blueprint);
                $decision = $this->validate($project, $blueprint, $comparisonPool, $keyword);
                $idea = $this->persistCandidate($plan, $blueprint, $rawIdea, $keyword, $decision);
                $excluded[] = $blueprint['fingerprint'];

                if ($decision['accepted']) {
                    $idea->update(['status' => 'reserve']);
                    $comparisonPool->push($idea);
                } else {
                    $plan->increment('rejected_count');
                    if ($decision['category'] === 'duplicate') {
                        $plan->increment('duplicate_count');
                    } elseif ($decision['category'] === 'weak_angle') {
                        $plan->increment('weak_angle_count');
                    } elseif ($decision['category'] === 'source_gap') {
                        $plan->increment('source_gap_count');
                    }
                }
            }

            if ($plan->ideas()->where('status', 'reserve')->exists()) {
                return;
            }
        }
    }

    private function validate(SeoProject $project, array $blueprint, Collection $valid, ?Keyword $keyword): array
    {
        if (in_array($blueprint['angle'], self::FORBIDDEN_ANGLES, true)
            || mb_strlen($blueprint['unique_promise']) < 35
            || count($blueprint['outline']) < 5) {
            return $this->reject('weak_angle', 'Angle, promesse ou mini-plan trop générique.');
        }

        $proposedKeyword = new Keyword(['keyword' => $blueprint['primary_keyword']]);
        if (! $keyword || ! $this->matcher->matches($project, $keyword) || ! $this->matcher->matches($project, $proposedKeyword)) {
            return $this->reject('off_topic', 'Le mot-clé ne correspond pas au produit.');
        }

        if ($formatIssue = $this->multiProductFormatIssue($project, $blueprint)) {
            return $this->reject('weak_angle', $formatIssue);
        }

        if ($competitorIssue = $this->competitorIssue($project, $blueprint)) {
            return $this->reject('weak_angle', $competitorIssue);
        }

        $sourceCoverage = $this->sourceCoverage($project);
        if ($sourceCoverage < 40) {
            return $this->reject('source_gap', 'Les sources vérifiées ne couvrent pas suffisamment ce sujet.', $sourceCoverage);
        }

        if ($coverageIssue = $this->alreadyPlannedKeywordIssue($blueprint, $valid, $keyword)) {
            return $this->reject('duplicate', $coverageIssue, $sourceCoverage, 100);
        }

        $representation = implode(' ', [
            $blueprint['title'] ?? '', $blueprint['primary_keyword'], $blueprint['problem'],
            $blueprint['expected_outcome'], $blueprint['unique_promise'], implode(' ', $blueprint['outline']),
        ]);
        $existing = $this->duplicates->analyzeBlueprint($project, $blueprint, $representation);
        $bestScore = (float) $existing['score'];
        $lexicalScore = (float) ($existing['lexical_score'] ?? 0);
        $closestArticle = $existing['article'];
        if ($bestScore >= 72 || $lexicalScore >= 65) {
            return $this->reject('duplicate', $bestScore >= 86 || $lexicalScore >= 65 ? 'Sujet ou intention déjà couverts (anti-cannibalisation).' : 'Angle trop proche d’un contenu existant.', $sourceCoverage, max($bestScore, $lexicalScore), $closestArticle?->id);
        }

        foreach ($valid as $accepted) {
            $score = $this->duplicates->compareBlueprints($blueprint, $accepted->blueprint());
            $outlineScore = $this->duplicates->compareOutlines($blueprint['outline'], $accepted->outline ?? []);
            if ($score >= 72 || $outlineScore >= .80) {
                return $this->reject('duplicate', $outlineScore >= .80 ? 'Mini-plan trop similaire à une idée du lot.' : 'Promesse trop proche d’une idée du lot.', $sourceCoverage, max($score, $outlineScore * 100));
            }
            $bestScore = max($bestScore, $score);
        }

        return [
            'accepted' => true,
            'category' => null,
            'reason' => null,
            'source_coverage' => $sourceCoverage,
            'similarity' => round($bestScore, 2),
            'closest_article_id' => $closestArticle?->id,
            'seo_score' => $this->score($keyword, $blueprint, $sourceCoverage, $bestScore, $project),
        ];
    }

    private function multiProductFormatIssue(SeoProject $project, array $blueprint): ?string
    {
        $type = (string) ($blueprint['content_type'] ?? 'informational');
        $title = (string) ($blueprint['title'] ?? '');
        $normalizedTitle = mb_strtolower($title);

        if ($type === 'comparison' && preg_match('/\bvs\.?\b|\bface à\b|\bentre\s+.+\s+et\s+.+/iu', $title) !== 1) {
            return 'Un comparatif doit annoncer explicitement les deux solutions confrontées dans son titre.';
        }

        if ($type === 'alternatives' && ! str_contains($normalizedTitle, mb_strtolower($project->name))) {
            return "Une page d’alternatives doit partir explicitement de {$project->name} et nommer cette référence dans son titre.";
        }

        if ($type === 'best_tools') {
            $promisesSeveralTools = preg_match('/\b(?:[2-9]|[1-9][0-9]+)\b|\b(?:meilleurs?|sélection|top)\b/iu', $title) === 1;
            if (! $promisesSeveralTools) {
                return 'Une sélection de meilleurs outils doit annoncer plusieurs solutions ; un titre mono-produit doit utiliser le type test ou guide.';
            }
        }

        return null;
    }

    private function competitorIssue(SeoProject $project, array $blueprint): ?string
    {
        $type = (string) ($blueprint['content_type'] ?? 'informational');
        $text = implode(' ', array_filter([
            $blueprint['title'] ?? '',
            $blueprint['primary_keyword'] ?? '',
            $blueprint['entity'] ?? '',
            $blueprint['topic'] ?? '',
            $blueprint['problem'] ?? '',
            $blueprint['expected_outcome'] ?? '',
            $blueprint['unique_promise'] ?? '',
            implode(' ', $blueprint['excluded_topics'] ?? []),
            implode(' ', $blueprint['outline'] ?? []),
        ]));

        $unknown = $this->competitors->unknownCompetitorMentions($project, $text);
        if ($unknown !== []) {
            return 'Concurrent inconnu ou fictif detecte : '.implode(', ', $unknown).'. Configurez-le comme concurrent reel ou utilisez uniquement : '.implode(', ', $this->competitors->allowedEntities($project)).'.';
        }

        if (! in_array($type, ['comparison', 'alternatives', 'best_tools'], true)) {
            return null;
        }

        if ($this->structures->isBtpSoftwareRequest($text)) {
            $specialists = collect($this->structures->btpSpecialistTools())
                ->filter(fn (string $name): bool => preg_match('/\b'.preg_quote($name, '/').'\b/iu', $text) === 1)
                ->values();
            if ($specialists->count() < 3) {
                return 'Une page BTP comparative doit citer au moins 3 outils specialises BTP dans le titre ou le plan : '.implode(', ', $this->structures->btpSpecialistTools()).'. Les generalistes doivent rester classes comme adaptables.';
            }
        }

        $mentioned = $this->competitors->mentionedCompetitors($project, $text);
        if ($mentioned === []) {
            $available = $this->competitors->competitorsFor($project);

            return $available === []
                ? 'Un comparatif exige au moins un concurrent reel configure sur le projet.'
                : 'Un comparatif doit citer au moins un concurrent reel autorise dans le titre ou le plan : '.implode(', ', $available).'.';
        }

        return null;
    }

    private function alreadyPlannedKeywordIssue(array $blueprint, Collection $valid, ?Keyword $keyword): ?string
    {
        $keys = $this->planningKeywordKeys($keyword?->id, (string) ($keyword?->keyword ?: ($blueprint['primary_keyword'] ?? '')));
        if ($keys === []) {
            return null;
        }

        foreach ($valid as $accepted) {
            if (! $accepted instanceof EditorialIdea) {
                continue;
            }

            $acceptedKeys = $this->planningKeywordKeys($accepted->keyword_id, (string) $accepted->primary_keyword);
            if (array_intersect($keys, $acceptedKeys) !== []) {
                return 'Mot-cle deja retenu dans le plan : "'.$accepted->title.'". Un meme mot-cle Semrush ne doit produire qu un seul article ; choisissez un autre mot-cle ou un vrai satellite distinct.';
            }
        }

        return null;
    }

    /**
     * Compare both the Semrush row id and the normalized keyword text so an
     * imported duplicate row cannot slip through as a second editorial idea.
     */
    private function planningKeywordKeys(?int $keywordId, string $keyword): array
    {
        $keys = [];
        if ($keywordId) {
            $keys[] = 'id:'.$keywordId;
        }

        $normalized = $this->normalizePlanningKeyword($keyword);
        if ($normalized !== '') {
            $keys[] = 'text:'.$normalized;
        }

        return array_values(array_unique($keys));
    }

    private function normalizePlanningKeyword(string $keyword): string
    {
        $value = trim($keyword);
        if ($value === '') {
            return '';
        }

        return trim(preg_replace('/[^a-z0-9]+/u', '-', mb_strtolower(str($value)->ascii()->toString())) ?: '', '-');
    }

    private function persistCandidate(EditorialPlan $plan, array $blueprint, array $raw, ?Keyword $keyword, array $decision): EditorialIdea
    {
        return $plan->ideas()->create([
            'keyword_id' => $keyword?->id,
            'content_cluster_id' => $keyword?->content_cluster_id,
            'closest_article_id' => $decision['closest_article_id'] ?? null,
            'title' => mb_substr(trim((string) ($raw['title'] ?? $blueprint['unique_promise'])), 0, 255),
            'primary_keyword' => mb_substr((string) ($keyword?->keyword ?: $blueprint['primary_keyword']), 0, 255),
            'entity_key' => $blueprint['entity'],
            'topic_key' => $blueprint['topic'],
            'intent' => $blueprint['intent'],
            'angle' => $blueprint['angle'],
            'audience' => $blueprint['audience'],
            'problem' => $blueprint['problem'],
            'expected_outcome' => $blueprint['expected_outcome'],
            'funnel_stage' => $blueprint['funnel_stage'] ?? 'consideration',
            'unique_promise' => $blueprint['unique_promise'],
            'excluded_topics' => $blueprint['excluded_topics'],
            'outline' => $blueprint['outline'],
            'roadmap_level' => $raw['roadmap_level'] ?? null,
            'brief_details' => [
                'call_to_action' => $raw['call_to_action'] ?? null,
                'lsi_keywords' => $raw['lsi_keywords'] ?? [],
                'people_also_ask' => $raw['people_also_ask'] ?? [],
                'tone_of_voice' => $raw['tone_of_voice'] ?? null,
                'schema_org' => $raw['schema_org'] ?? null,
                'internal_links_strategy' => $raw['internal_links_strategy'] ?? null,
            ],
            'fingerprint' => $blueprint['fingerprint'],
            'content_type' => in_array($raw['content_type'] ?? '', ['informational', 'tool_review', 'pricing', 'comparison', 'alternatives', 'best_tools'], true) ? $raw['content_type'] : 'informational',
            'status' => $decision['accepted'] ? 'candidate' : 'rejected',
            'rejection_reason' => $decision['reason'],
            'seo_score' => $decision['seo_score'] ?? 0,
            'similarity_score' => $decision['similarity'] ?? 0,
            'source_coverage' => $decision['source_coverage'] ?? 0,
        ]);
    }

    private function reject(string $category, string $reason, float $coverage = 0, float $similarity = 0, ?int $articleId = null): array
    {
        return ['accepted' => false, 'category' => $category, 'reason' => $reason, 'source_coverage' => $coverage, 'similarity' => $similarity, 'closest_article_id' => $articleId];
    }

    private function closestKeyword(Collection $keywords, string $value): ?Keyword
    {
        $exact = $keywords->first(fn (Keyword $keyword) => mb_strtolower($keyword->keyword) === mb_strtolower($value));
        if ($exact) {
            return $exact;
        }

        $closest = $keywords->sortByDesc(fn (Keyword $keyword) => $this->duplicates->similarity($keyword->keyword, $value))->first();
        if (! $closest || $this->duplicates->similarity($closest->keyword, $value) < .45) {
            return null;
        }

        return $closest;
    }

    private function sourceKeyword(Collection $keywords, array $rawIdea, array $blueprint): ?Keyword
    {
        $sourceId = filter_var($rawIdea['source_keyword_id'] ?? null, FILTER_VALIDATE_INT);
        if ($sourceId !== false && $sourceId !== null) {
            $source = $keywords->first(fn (Keyword $keyword) => $keyword->id === (int) $sourceId);
            if ($source !== null
                && $this->duplicates->similarity($source->keyword, (string) ($blueprint['primary_keyword'] ?? '')) >= .80) {
                return $source;
            }
        }

        return $this->closestKeyword($keywords, (string) ($blueprint['primary_keyword'] ?? ''));
    }

    private function sourceCoverage(SeoProject $project): float
    {
        $pages = $project->sourcePages()->where('status', 'verified')->count();
        $chunks = $project->sourcePages()->where('status', 'verified')->withCount('evidenceChunks')->get()->sum('evidence_chunks_count');

        return min(100, 45 + ($pages * 8) + min(35, $chunks * 2));
    }

    /**
     * Empêche les synonymes à fort volume de monopoliser les 120 mots-clés
     * transmis à Gemini et réserve une vraie place aux faibles KD qualifiés.
     *
     * @return Collection<int, Keyword>
     */
    private function strategicKeywords(SeoProject $project, array $keywordScope = []): Collection
    {
        if ($keywordScope !== []) {
            return $project->keywords()
                ->with('contentCluster')
                ->withCount(['articles', 'editorialIdeas'])
                ->whereIn('id', array_map('intval', $keywordScope))
                ->get()
                ->filter(fn (Keyword $keyword) => $this->matcher->matches($project, $keyword))
                ->values();
        }

        $top = $project->keywords()->with('contentCluster')->withCount(['articles', 'editorialIdeas'])
            ->orderByDesc('opportunity_score')->limit(500)->get();
        $recent = $project->keywords()->with('contentCluster')->withCount(['articles', 'editorialIdeas'])
            ->latest('created_at')->limit(200)->get();
        $all = $top->concat($recent)->unique('id')
            ->filter(fn (Keyword $keyword) => $this->matcher->matches($project, $keyword))
            ->values();
        $bucket = fn (string $tier) => $all->filter(fn (Keyword $keyword) => $keyword->strategyTier() === $tier);
        $newlyImported = $all->filter(fn (Keyword $keyword) => $keyword->isUnplanned())
            ->sortByDesc('created_at');

        return $this->pickDiverse($bucket('pillar')->sortByDesc('search_volume'), 12)
            ->concat($this->pickDiverse($newlyImported, 45))
            ->concat($this->pickDiverse($bucket('quick_win')->sortByDesc('opportunity_score'), 45))
            ->concat($this->pickDiverse($bucket('niche')->sortByDesc('opportunity_score'), 33))
            ->concat($this->pickDiverse($bucket('supporting')->sortByDesc('opportunity_score'), 15))
            ->concat($this->pickDiverse($all, 150))
            ->unique('id')
            ->take(150)
            ->values();
    }

    private function pickDiverse(Collection $pool, int $limit): Collection
    {
        $grouped = $pool->groupBy(fn (Keyword $k) => $k->cluster ?: 'Général')
            ->map(fn ($group) => $group->values());
        
        $selected = collect();
        $index = 0;
        
        while ($selected->count() < $limit && $grouped->isNotEmpty()) {
            $addedThisRound = 0;
            foreach ($grouped as $cluster => $group) {
                if (isset($group[$index])) {
                    $selected->push($group[$index]);
                    $addedThisRound++;
                }
            }
            if ($addedThisRound === 0) {
                break;
            }
            $index++;
        }
        
        return $selected->take($limit)->values();
    }

    private function score(Keyword $keyword, array $blueprint, float $coverage, float $similarity, SeoProject $project): float
    {
        $seoOpportunity = min(100, max(0, (float) $keyword->opportunity_score));
        $commercial = in_array($blueprint['intent'], ['commercial', 'transactional'], true) ? 95 : 65;
        $internalLinks = min(100, 45 + ($project->articles()->count() * 5));
        $difficultyPenalty = min(12, ((float) $keyword->keyword_difficulty) * .12);
        $newKeywordBonus = $keyword->isUnplanned() ? 8 : 0;
        $affiliatePriority = min(100, max(0, (float) $keyword->affiliate_priority));
        $businessScore = in_array($blueprint['content_type'] ?? '', ['best_tools', 'comparison', 'alternatives'], true) ? 45 : 0;
        $targetAudienceBonus = preg_match('/indépendant|bnc|auto[\s-]?entrepreneur|micro[\s-]?entreprise|freelance|gratuit/iu', $keyword->keyword) ? 35 : 0;

        return round(max(0,
            ($seoOpportunity * .25) + 15 + ($commercial * .15) + 15
            + ((100 - $similarity) * .15) + ($coverage * .10) + ($internalLinks * .05)
            + ($affiliatePriority * .12) + $newKeywordBonus - $difficultyPenalty + $businessScore + $targetAudienceBonus
        ), 2);
    }

    private function existingFingerprints(SeoProject $project): array
    {
        $articles = $project->articles()->whereIn('status', ['draft', 'review', 'scheduled', 'published'])->pluck('topic_fingerprint')->filter();
        $ideas = EditorialIdea::query()->whereHas('plan', fn ($query) => $query->where('seo_project_id', $project->id))
            ->where(fn ($query) => $this->reusableEditorialIdeas($query))
            ->pluck('fingerprint');

        return $articles->merge($ideas)->filter()->unique()->values()->all();
    }

    /**
     * Une idée retirée par le garde-fou reste une référence de déduplication :
     * elle ne sera jamais rédigée, mais Gemini ne pourra pas la reproposer sous
     * un titre légèrement différent au prochain remplissage du calendrier.
     */
    private function reusableEditorialIdeas($query): void
    {
        $query->whereIn('status', ['accepted', 'reserve', 'generating', 'generated'])
            ->orWhere(function ($rejected): void {
                $rejected->where('status', 'rejected')
                    ->where('rejection_reason', 'like', 'Doublon retiré automatiquement%');
            });
    }
}
