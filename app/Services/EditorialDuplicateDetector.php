<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Keyword;
use App\Models\SeoProject;
use Illuminate\Support\Str;

final class EditorialDuplicateDetector
{
    public function __construct(
        private readonly TopicNormalizer $topics,
        private readonly SeoSlugGenerator $slugs,
    ) {}

    public function blueprint(SeoProject $project, ?Keyword $keyword, string $type): array
    {
        $keywordValue = trim((string) $keyword?->keyword);
        $topic = $this->topicKey($keywordValue, $type);
        $crmDomain = str_contains($topic, 'crm')
            || preg_match('/crm|clients?|prospects?|leads?|pipeline|commercial/iu', $keywordValue) === 1;
        $entity = Str::slug($project->name).($crmDomain ? '-crm' : '');
        $intent = $this->intent($keyword, $type);
        $audience = $this->audience($keywordValue);
        $angle = $this->angle($topic, $type);

        $blueprint = [
            'entity' => $entity,
            'topic' => $topic,
            'intent' => $intent,
            'audience' => $audience,
            'angle' => $angle,
            'funnel_stage' => $this->funnelStage($intent, $type),
            'primary_keyword' => $keywordValue,
            'unique_promise' => $this->uniquePromise($project->name, $topic, $audience),
            'problem' => $this->uniquePromise($project->name, $topic, $audience),
            'expected_outcome' => $this->expectedOutcome($topic),
            'excluded_topics' => $this->excludedTopics($topic, $type),
            'outline' => [],
        ];
        $blueprint['fingerprint'] = $this->fingerprint($blueprint);

        return $blueprint;
    }

    public function normalizeBlueprint(array $blueprint): array
    {
        $blueprint['entity'] = $this->topics->key((string) ($blueprint['entity'] ?? ''));
        $blueprint['topic'] = $this->topics->normalize((string) ($blueprint['topic'] ?? ''));
        $rawIntent = $this->topics->key((string) ($blueprint['intent'] ?? 'informational'));
        $blueprint['intent'] = match (true) {
            str_contains($rawIntent, 'info') => 'informational',
            str_contains($rawIntent, 'transaction') => 'transactional',
            str_contains($rawIntent, 'commerc') => 'commercial',
            default => $rawIntent ?: 'informational',
        };
        $blueprint['angle'] = $this->topics->key((string) ($blueprint['angle'] ?? ''));
        $blueprint['audience'] = $this->topics->key((string) ($blueprint['audience'] ?? 'general'));
        $blueprint['problem'] = trim((string) ($blueprint['problem'] ?? $blueprint['unique_promise'] ?? ''));
        $blueprint['expected_outcome'] = trim((string) ($blueprint['expected_outcome'] ?? ''));
        $blueprint['unique_promise'] = trim((string) ($blueprint['unique_promise'] ?? ''));
        $blueprint['excluded_topics'] = array_values(array_filter((array) ($blueprint['excluded_topics'] ?? [])));
        $blueprint['outline'] = array_values(array_filter((array) ($blueprint['outline'] ?? [])));
        $blueprint['fingerprint'] = $this->fingerprint($blueprint);

        return $blueprint;
    }

    public function compareBlueprints(array $candidate, array $existing): float
    {
        $candidate = $this->normalizeBlueprint($candidate);
        $existing = $this->normalizeBlueprint($existing);
        
        $titleSimilarity = $this->similarity((string) ($candidate['title'] ?? ''), (string) ($existing['title'] ?? ''));
        $keywordSimilarity = $this->similarity((string) ($candidate['primary_keyword'] ?? ''), (string) ($existing['primary_keyword'] ?? ''));
        
        if ($titleSimilarity >= 0.80 || $keywordSimilarity >= 0.80) {
            return 100.0;
        }

        $score = $this->blueprintScore($candidate, $existing);
        $semantic = $this->similarity($this->blueprintRepresentation($candidate), $this->blueprintRepresentation($existing));
        $outline = $this->compareOutlines($candidate['outline'], $existing['outline']);

        return round(min(100, $score + ($semantic * 18) + ($outline * 18)), 2);
    }

    public function compareOutlines(array $first, array $second): float
    {
        if ($first === [] || $second === []) {
            return 0.0;
        }

        $scores = [];
        foreach ($first as $heading) {
            $scores[] = collect($second)->map(fn ($other) => $this->similarity((string) $heading, (string) $other))->max() ?: 0;
        }

        return array_sum($scores) / max(count($first), count($second));
    }

    public function analyzeBefore(SeoProject $project, ?Keyword $keyword, string $type, ?int $ignoreArticleId = null): array
    {
        $blueprint = $this->blueprint($project, $keyword, $type);

        return ['blueprint' => $blueprint, ...$this->findBestMatch($project, $blueprint, null, $ignoreArticleId)];
    }

    public function customizeBlueprint(array $blueprint, ?string $audience, ?string $angle, ?string $uniquePromise, array $excludedTopics): array
    {
        $blueprint['audience'] = Str::slug((string) $audience) ?: $blueprint['audience'];
        $blueprint['angle'] = Str::slug((string) $angle) ?: $blueprint['angle'];
        $blueprint['unique_promise'] = trim((string) $uniquePromise) ?: $blueprint['unique_promise'];
        $blueprint['excluded_topics'] = $excludedTopics ?: $blueprint['excluded_topics'];
        $blueprint['fingerprint'] = $this->fingerprint($blueprint);

        return $blueprint;
    }

    public function analyzeBlueprint(SeoProject $project, array $blueprint, string $representation, ?int $ignoreArticleId = null): array
    {
        return ['blueprint' => $blueprint, ...$this->findBestMatch($project, $blueprint, $representation, $ignoreArticleId)];
    }

    public function analyzeGenerated(
        SeoProject $project,
        ?Keyword $keyword,
        string $type,
        array $data,
        string $body,
        array $blueprint,
        ?int $ignoreArticleId = null,
    ): array {
        $representation = implode(' ', array_filter([
            $data['title'] ?? null,
            $keyword?->keyword,
            implode(' ', $data['outline'] ?? []),
            $this->structuralText($body),
            $blueprint['unique_promise'],
        ]));

        return ['blueprint' => $blueprint, ...$this->findBestMatch($project, $blueprint, $representation, $ignoreArticleId)];
    }

    public function hydrateArticleFingerprint(Article $article): array
    {
        $article->loadMissing(['project', 'keyword', 'brief']);
        $blueprint = $this->blueprint($article->project, $article->keyword, $article->type);
        if ($article->primary_keyword && ! $article->keyword) {
            $virtualKeyword = new Keyword([
                'keyword' => $article->primary_keyword,
                'intent' => $article->search_intent,
            ]);
            $blueprint = $this->blueprint($article->project, $virtualKeyword, $article->type);
        }
        $article->update($this->articleFingerprintAttributes($blueprint));

        return $blueprint;
    }

    public function articleFingerprintAttributes(array $blueprint): array
    {
        return [
            'entity_key' => $blueprint['entity'],
            'topic_key' => $blueprint['topic'],
            'content_angle' => $blueprint['angle'],
            'editorial_audience' => $blueprint['audience'],
            'funnel_stage' => $blueprint['funnel_stage'],
            'topic_fingerprint' => $blueprint['fingerprint'],
            'unique_promise' => $blueprint['unique_promise'],
            'editorial_problem' => $blueprint['problem'] ?? null,
            'expected_outcome' => $blueprint['expected_outcome'] ?? null,
            'excluded_topics' => $blueprint['excluded_topics'],
        ];
    }

    public function decision(float $score): string
    {
        return match (true) {
            $score >= 85 => 'block',
            $score >= 70 => 'merge_or_reangle',
            $score >= 50 => 'differentiate',
            default => 'allow',
        };
    }

    public function recommendedSlug(SeoProject $project, array $blueprint, ?string $title = null): string
    {
        return $this->slugs->generate($project, $blueprint, $title ?: $this->recommendedTitle($project, $blueprint));
    }

    public function recommendedTitle(SeoProject $project, array $blueprint): string
    {
        $product = $this->displayName($project->name);
        if ($blueprint['topic'] === 'gestion-relation-client') {
            return "Maîtriser {$product} CRM : guide complet pour optimiser votre gestion commerciale";
        }

        return match ($blueprint['topic']) {
            'pipeline-commercial' => "Comment créer et gérer un pipeline commercial dans {$product}",
            'suivi-prospects' => "Automatiser le suivi des prospects avec {$product} CRM",
            'reporting-commercial' => "Configurer les rapports commerciaux dans {$product}",
            'crm-pme' => "{$product} CRM pour les petites entreprises : fonctionnalités et limites",
            default => "Maîtriser {$product} : {$blueprint['unique_promise']}",
        };
    }

    public function displayName(string $name): string
    {
        return match (mb_strtolower(trim($name))) {
            'hubspot' => 'HubSpot',
            'activecampaign' => 'ActiveCampaign',
            default => $name,
        };
    }

    private function findBestMatch(
        SeoProject $project,
        array $candidateBlueprint,
        ?string $candidateRepresentation,
        ?int $ignoreArticleId,
    ): array {
        $candidateBlueprint = $this->normalizeBlueprint($candidateBlueprint);
        // Le worker est un processus long : un cache local figé avant la
        // création du premier article rendait celui-ci invisible aux suivants.
        $articles = Article::query()
            ->with(['project', 'keyword', 'brief'])
            ->where('seo_project_id', $project->id)
            ->whereIn('status', ['draft', 'review', 'scheduled', 'published'])
            ->when($ignoreArticleId, fn ($query) => $query->whereKeyNot($ignoreArticleId))
            ->get();

        $bestArticle = null;
        $bestScore = 0.0;
        $bestLexicalScore = 0.0;
        foreach ($articles as $article) {
            $articleBlueprint = $this->normalizeBlueprint($this->blueprintForArticle($article));
            $score = $this->blueprintScore($candidateBlueprint, $articleBlueprint);
            $candidateKeyword = $this->topics->key((string) ($candidateBlueprint['primary_keyword'] ?? ''));
            $articleKeyword = $this->topics->key((string) ($articleBlueprint['primary_keyword'] ?? ''));
            if ($candidateKeyword !== '' && $candidateKeyword === $articleKeyword && $candidateBlueprint['intent'] === $articleBlueprint['intent']) {
                // Face à un article déjà créé, le même mot-clé principal est un
                // signal supplémentaire. Entre deux briefs du même lot, les
                // angles restent libres d'être réellement distincts.
                $score += 46;
            }
            $candidateText = $candidateRepresentation ?: $this->blueprintRepresentation($candidateBlueprint);
            $lexicalScore = $this->similarity($candidateText, $this->articleRepresentation($article));
            $score = min(100, $score + ($lexicalScore * 20));
            if ($score > $bestScore) {
                $bestArticle = $article;
                $bestScore = $score;
                $bestLexicalScore = $lexicalScore;
            }
        }

        return [
            'article' => $bestArticle,
            'score' => round($bestScore, 2),
            'lexical_score' => round($bestLexicalScore * 100, 2),
            'decision' => $this->decision($bestScore),
        ];
    }

    private function blueprintForArticle(Article $article): array
    {
        if ($article->topic_fingerprint) {
            return [
                'entity' => $article->entity_key,
                'topic' => $article->topic_key,
                'intent' => $this->normalizedIntent($article->search_intent, $article->type),
                'audience' => $article->editorial_audience ?: 'general',
                'angle' => $article->content_angle ?: 'guide-complet',
                'funnel_stage' => $article->funnel_stage ?: 'consideration',
                'primary_keyword' => (string) $article->primary_keyword,
                'unique_promise' => (string) $article->unique_promise,
                'problem' => (string) $article->editorial_problem,
                'expected_outcome' => (string) $article->expected_outcome,
                'excluded_topics' => $article->excluded_topics ?? [],
                'outline' => $article->brief?->outline ?? [],
                'fingerprint' => $article->topic_fingerprint,
            ];
        }

        $keyword = $article->keyword ?: new Keyword([
            'keyword' => $article->primary_keyword ?: $article->title,
            'intent' => $article->search_intent,
        ]);

        return $this->blueprint($article->project, $keyword, $article->type);
    }

    private function blueprintScore(array $candidate, array $existing): float
    {
        if ($candidate['fingerprint'] === $existing['fingerprint']) {
            return 100.0;
        }

        $score = ($candidate['entity'] === $existing['entity'] ? 18 : 0)
            + ($candidate['intent'] === $existing['intent'] ? 14 : 0)
            + (26 * $this->similarity($candidate['topic'], $existing['topic']))
            + (16 * $this->similarity($candidate['angle'], $existing['angle']))
            + ($candidate['audience'] === $existing['audience'] ? 6 : 0);

        return $score;
    }

    public function similarity(string $first, string $second): float
    {
        $firstTokens = $this->tokens($first);
        $secondTokens = $this->tokens($second);
        if ($firstTokens === [] || $secondTokens === []) {
            return 0.0;
        }

        return count(array_intersect($firstTokens, $secondTokens))
            / count(array_unique([...$firstTokens, ...$secondTokens]));
    }

    private function topicKey(string $keyword, string $type): string
    {
        $value = $this->normalize($keyword);

        return match (true) {
            $type === 'pricing' => 'tarifs',
            $type === 'comparison' => 'comparaison-directe',
            $type === 'best_tools' => 'selection-outils',
            $type === 'alternatives' => 'alternatives',
            preg_match('/pipeline|etape de vente|processus commercial/u', $value) === 1 => 'pipeline-commercial',
            preg_match('/prospect|lead|suivi commercial|relance/u', $value) === 1 => 'suivi-prospects',
            preg_match('/rapport|reporting|tableau de bord|prevision/u', $value) === 1 => 'reporting-commercial',
            preg_match('/pme|petite entreprise|tpe/u', $value) === 1 => 'crm-pme',
            preg_match('/crm|relation client|gestion commerciale|gestion (?:des )?clients?|suivi clients?|logiciel client/u', $value) === 1 => 'gestion-relation-client',
            default => collect($this->tokens($value))->take(5)->implode('-') ?: 'guide-general',
        };
    }

    private function angle(string $topic, string $type): string
    {
        return match ($topic) {
            'pipeline-commercial' => 'configuration-pipeline',
            'suivi-prospects' => 'automatisation-prospects',
            'reporting-commercial' => 'configuration-rapports',
            'crm-pme' => 'pme-fonctionnalites-limites',
            'gestion-relation-client' => 'centraliser-interactions-clients',
            default => match ($type) {
                'pricing' => 'cout-reel',
                'comparison' => 'decision-directe',
                'alternatives' => 'migration-alternatives',
                'best_tools' => 'selection-par-profil',
                default => 'guide-pratique',
            },
        };
    }

    private function intent(?Keyword $keyword, string $type): string
    {
        return $this->normalizedIntent($keyword?->intent, $type);
    }

    private function normalizedIntent(?string $intent, string $type): string
    {
        return match (true) {
            $type === 'informational' => 'informational',
            $type === 'pricing' => 'transactional',
            in_array($type, ['comparison', 'best_tools', 'alternatives', 'tool_review'], true) => 'commercial',
            str_contains($this->normalize((string) $intent), 'info') => 'informational',
            str_contains($this->normalize((string) $intent), 'transaction') => 'transactional',
            default => 'commercial',
        };
    }

    private function audience(string $keyword): string
    {
        $value = $this->normalize($keyword);

        return match (true) {
            preg_match('/pme|petite entreprise|tpe/u', $value) === 1 => 'pme',
            preg_match('/e.?commerce|boutique en ligne|b2c/u', $value) === 1 => 'ecommerce',
            preg_match('/debutant|demarrer|premier/u', $value) === 1 => 'debutants',
            preg_match('/agence/u', $value) === 1 => 'agences',
            default => 'general',
        };
    }

    private function funnelStage(string $intent, string $type): string
    {
        return $type === 'pricing' || $intent === 'transactional' ? 'decision' : 'consideration';
    }

    private function uniquePromise(string $product, string $topic, string $audience): string
    {
        return match ($topic) {
            'pipeline-commercial' => "guider la configuration du premier pipeline commercial dans {$product}",
            'suivi-prospects' => "automatiser les relances et le suivi des prospects dans {$product}",
            'reporting-commercial' => "configurer des rapports commerciaux exploitables dans {$product}",
            'crm-pme' => "évaluer les fonctions et limites de {$product} pour une petite entreprise",
            'gestion-relation-client' => "centraliser les interactions clients dans {$product} CRM pour {$audience}",
            default => "répondre au sujet {$topic} avec un angle {$audience}",
        };
    }

    private function excludedTopics(string $topic, string $type): array
    {
        $topics = ['comparatif général', 'tarifs détaillés', 'présentation marketing générique'];
        if ($topic !== 'pipeline-commercial') {
            $topics[] = 'configuration détaillée du pipeline';
        }
        if ($topic !== 'suivi-prospects') {
            $topics[] = 'automatisation détaillée des prospects';
        }
        if ($type !== 'pricing') {
            $topics[] = 'analyse exhaustive des offres tarifaires';
        }

        return array_values(array_unique($topics));
    }

    private function fingerprint(array $blueprint): string
    {
        return implode('|', [
            $blueprint['entity'],
            $blueprint['topic'],
            $blueprint['intent'],
            $blueprint['angle'],
            $blueprint['audience'],
            $this->topics->key((string) ($blueprint['expected_outcome'] ?? '')),
        ]);
    }

    private function blueprintRepresentation(array $blueprint): string
    {
        return implode(' ', [
            $blueprint['title'] ?? '', $blueprint['entity'], $blueprint['topic'], $blueprint['intent'], $blueprint['audience'],
            $blueprint['angle'], $blueprint['primary_keyword'], $blueprint['unique_promise'],
            $blueprint['problem'] ?? '', $blueprint['expected_outcome'] ?? '', implode(' ', $blueprint['outline'] ?? []),
        ]);
    }

    private function expectedOutcome(string $topic): string
    {
        return match ($topic) {
            'pipeline-commercial' => 'obtenir un pipeline commercial opérationnel',
            'suivi-prospects' => 'automatiser les relances sans perdre de prospect',
            'reporting-commercial' => 'piloter les conversions avec des rapports fiables',
            'crm-pme' => 'choisir une configuration adaptée aux contraintes d’une PME',
            'gestion-relation-client' => 'centraliser et suivre les interactions clients',
            default => 'résoudre précisément le besoin de recherche',
        };
    }

    private function articleRepresentation(Article $article): string
    {
        return implode(' ', array_filter([
            $article->title,
            $article->primary_keyword,
            $article->brief?->angle,
            $article->brief?->audience,
            implode(' ', $article->brief?->outline ?? []),
            $this->structuralText($article->body),
        ]));
    }

    private function structuralText(string $body): string
    {
        preg_match_all('/^#{2,3}\s+(.+)$/mu', $body, $headings);

        return implode(' ', $headings[1] ?? []);
    }

    private function tokens(string $value): array
    {
        $value = $this->normalize($value);
        $value = str_replace([
            'gestion de la relation client', 'gestion relation client', 'relation client',
            'gestion commerciale', 'logiciel client',
        ], ' crm ', $value);
        $value = preg_replace('/(?:devis|factures?|facturation|comptable|comptabilite|paiements?|cash.?flow|chiffre d.affaires)/u', ' crm-facturation ', $value) ?: $value;
        preg_match_all('/[a-z0-9]{3,}/', $value, $matches);

        return array_values(array_unique(array_diff($matches[0] ?? [], $this->stopWords())));
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\s+/', ' ', Str::ascii(mb_strtolower($value))) ?: '';
    }

    private function stopWords(): array
    {
        return [
            'avec', 'comment', 'dans', 'des', 'elle', 'guide', 'les', 'leur', 'maitriser', 'optimiser',
            'pour', 'sans', 'son', 'sur', 'une', 'utiliser', 'votre', 'vos', 'complet', 'complete',
            'logiciel', 'outil', 'solution', 'meilleur', 'meilleure', 'avis', 'test', '2026',
        ];
    }
}
