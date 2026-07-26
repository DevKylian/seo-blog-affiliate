<?php

namespace App\Services;

use App\Models\Keyword;
use App\Models\SeoProject;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class GeminiEditorialIdeaGenerator
{
    /** @return array<int, array<string, mixed>> */
    public function generate(
        SeoProject $project,
        Collection $keywords,
        Collection $strategicSubjects,
        int $desiredCount,
        array $excludedFingerprints = [],
        string $instructions = '',
        int $attempt = 1,
    ): array {
        $currentDate = now()->locale('fr')->translatedFormat('j F Y');
        $currentYear = now()->format('Y');
        $keywordData = $keywords->take(120)->shuffle()->map(fn (Keyword $keyword) => array_filter([
            'keyword_id' => $keyword->id,
            'keyword' => $keyword->keyword,
            'volume' => $keyword->search_volume,
            'difficulty' => $keyword->keyword_difficulty,
            'intent' => $keyword->intent,
            'intent_type' => $keyword->intent_type,
            'affiliate_cluster' => $keyword->affiliate_cluster,
            'affiliate_priority' => $keyword->affiliate_priority,
            'user_moment' => $keyword->user_moment,
            'problem' => $keyword->problem_label,
            'solution' => $keyword->solution_label,
            'cluster' => $keyword->cluster,
            'content_cluster_id' => $keyword->content_cluster_id,
            'content_cluster_type' => $keyword->contentCluster?->type,
            'opportunity' => $keyword->opportunity_score,
            'strategy_tier' => $keyword->strategyTier(),
            'new_for_planning' => $keyword->isUnplanned(),
        ], fn ($value) => $value !== null && $value !== ''))->values()->all();
        $keywordsJson = json_encode($keywordData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $strategicJson = json_encode($strategicSubjects->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $excludedJson = json_encode(array_values(array_unique($excludedFingerprints)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $competitorDirective = app(CompetitorCatalog::class)->promptDirective($project);
        $publishedPillars = $project->articles()
            ->where('status', 'published')
            ->with('keyword')
            ->get()
            ->filter(fn ($article) => $article->keyword?->strategyTier() === 'pillar')
            ->count();
        $strategy = $publishedPillars < 2
            ? "PHASE FONDATIONS : {$publishedPillars}/2 pilier(s) publié(s). Propose d’abord jusqu’à 2 piliers génériques distincts ou de grands comparatifs, puis consacre les idées restantes aux quick_win et niches."
            : 'PHASE EXPANSION : les fondations existent. Au moins 60 % des idées doivent cibler strategy_tier quick_win ou niche, avec une intention et un angle réellement distincts.';

        $prompt = <<<PROMPT
Tu es l'éditeur stratégique d’un site SEO affilié de très haut niveau. Tu ne rédiges aucun article, tu construis le plan éditorial parfait. Ton rôle n'est pas de générer des articles en vrac, mais de bâtir une autorité sémantique.

Crée {$desiredCount} idées réellement distinctes pour {$project->name}. C’est le cycle {$attempt}.
Variables système injectées par l’application : CURRENT_DATE = {$currentDate} ; CURRENT_YEAR = {$currentYear}.
Consignes utilisateur : {$instructions}
Stratégie de portefeuille : {$strategy}

ATTENTION CRITIQUE - INTERDICTION D'INVENTER DES LOGICIELS : 
Tu as l'interdiction formelle et absolue d'inventer des noms de logiciels. Ne génère JAMAIS de faux outils (comme "AccountPro Cloud", "ComptaFacile", etc.). Base-toi STRICTEMENT et UNIQUEMENT sur les logiciels cités dans les "Sujets Stratégiques" ou ceux qui sont des outils réels et extrêmement connus du marché français. TOUTE INVENTION de logiciel entraînera le rejet de ta réponse.

ATTENTION : Tu as deux sources pour générer tes idées :
1. "Sujets Stratégiques (Knowledge Graph)" : Ce sont les idées fondatrices (Piliers, Comparatifs, Alternatives). Tu DOIS traiter en priorité ces sujets car ils ont une très haute valeur métier.
2. "Mots-clés disponibles (Semrush)" : Ce sont les requêtes pour la longue traîne et le trafic de masse.

LANGUE FRANÇAISE
- Rédige tous les champs visibles en français naturel avec les accents normaux de la langue française.
- Les slugs restent gérés par l'application.
- Exception technique : primary_keyword recopie le mot-clé Semrush, ou le titre du Sujet Stratégique s'il n'y a pas de mot-clé exact.

{$competitorDirective}

Pour chaque idée, fournis un brief autonome, classé par niveau de roadmap : source_keyword_id, title, primary_keyword, entity, topic, intent, angle, audience, problem, expected_outcome, funnel_stage, unique_promise, excluded_topics, outline, content_type, roadmap_level, call_to_action, lsi_keywords, people_also_ask, tone_of_voice, schema_org, internal_links_strategy.

ORDRE DE PRIORITÉ ABSOLU ET RÈGLES DE SILOING
1. Piliers d'abord : Priorise TOUJOURS les pages piliers (Level 1 - Pillar) avant les contenus secondaires. Ce sont les fondations du site.
2. Conversion ensuite : Les comparatifs et pages money (Level 2, 5, 6) ne doivent être créés qu'après ou pour soutenir les piliers.
3. Longue traîne en dernier : Ne génère des articles longue traîne (Level 3 - Long Tail) que si la page pilier du thème est évidente ou existe déjà.
4. Rôle unique : Chaque page doit avoir un rôle unique dans le silo (pilier, conversion, support ou outil).

RÈGLE ANTI-DOUBLON STRATÉGIQUE (BLOQUANTE)
1. Identifier l’intention de recherche précise.
2. Vérifier (via les empreintes couvertes ci-dessous) si une page pilier ou similaire existe déjà sur ce thème.
3. Si oui, créer seulement une page complémentaire ou comparative si l'intention est RÉELLEMENT différente.
4. Ne jamais créer deux pages pour la même intention (ex: "Logiciel comptable freelance" et "Meilleur logiciel comptable pour freelance" sont la MÊME intention, un seul pilier suffit).
5. Si un sujet est déjà couvert à 70 % ou plus, ne propose pas de nouvelle page.
6. Chaque nouvelle page longue traîne ou avis doit indiquer dans 'internal_links_strategy' qu'elle pointe vers son pilier parent.

RÈGLES BLOQUANTES DE STRUCTURATION
- Une idée basée sur un Sujet Stratégique (Knowledge Graph) N'A PAS besoin d'un `source_keyword_id` valide, tu peux mettre null ou 0.
- `roadmap_level` DOIT être l'un des choix de l'enum stricte (Level 1 - Pillar, Level 2 - Commercial, etc.) selon la hiérarchie sémantique.
- Le brief doit être ULTRA-RICHE. Le rédacteur IA n'aura besoin d'aucune autre réflexion. Fournis des mots-clés LSI précis, des PAA réelles, une consigne de Tone of Voice spécifique, et la logique de maillage interne (ex: "Lien vers le pilier parent [Nom du pilier]").
- Un quick_win reste un contenu expert et complet. Faible difficulté ne signifie jamais contenu court ou superficiel.
- Le mini-plan (outline) doit être EXTRÊMEMENT structuré et professionnel. Il doit contenir 5 à 8 H2.
- Interdiction absolue des H2 génériques type "Introduction", "Conclusion", "Pourquoi c'est important". Les H2 doivent être des affirmations fortes ou des questions ultra-précises.
- DIVERSITÉ THÉMATIQUE ET FORMATS : Tu dois IMPÉRATIVEMENT générer une variété de formats (content_type). Génère des comparatifs en priorité si les Sujets Stratégiques le suggèrent.
- NATURALITÉ DES TITRES (TRÈS IMPORTANT) : Rédige des titres sobres, naturels et alignés sur de vraies requêtes Google. Interdiction des titres artificiels ou clickbait.
- COHÉRENCE DES ENTITÉS : Respecte la nature réelle des outils. Par exemple, Indy et Freebe sont des logiciels de facturation/compta, ce ne sont PAS des "comptes pros" au sens bancaire.
- SIMPLICITÉ ET CIBLAGE : Ne crée pas de sujets trop artificiels. Crée des silos étanches : une page pour la compta, une page pour la signature, etc.
- ANTI-HALLUCINATION STRICTE : N'invente JAMAIS de nom de logiciel, module ou outil fictif (ex: "FinanceCore Module"). Si tu dois citer un outil, utilise EXCLUSIVEMENT des logiciels réels et connus (ex: Indy, Qonto, Pennylane, Freebe, Abby, Shine). Toute invention de marque est formellement interdite.
- Comparison : nomme explicitement les deux solutions dans le titre avec « X vs Y » et prévois au moins 2 solutions concurrentielles dans le plan.
- Alternatives : le titre part explicitement du produit cible ou d'un concurrent sous la forme « Alternatives à X… ».

Empreintes déjà couvertes ou refusées — ne pas les reformuler ni créer de contenu avec une intention similaire (> 70%) :
{$excludedJson}

Sujets Stratégiques (Knowledge Graph - PRIORITÉ ABSOLUE) :
{$strategicJson}

Mots-clés disponibles (Semrush) :
{$keywordsJson}
PROMPT;

        $response = $this->request()->post($this->endpoint(), [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.65,
                'maxOutputTokens' => min(12288, max(8192, $desiredCount * 700)),
                'thinkingConfig' => ['thinkingBudget' => 1024],
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => $this->schema(),
            ],
        ]);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?: 'Erreur Gemini sans détail.';
            throw new RuntimeException("Gemini HTTP {$response->status()} pendant la planification : {$message}");
        }

        $usage = $response->json('usageMetadata', []);
        if (is_array($usage) && $usage !== []) {
            Log::info('Gemini usage', [
                'operation' => 'editorial_plan',
                'model' => $this->model(),
                'prompt_tokens' => (int) ($usage['promptTokenCount'] ?? 0),
                'output_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
                'thinking_tokens' => (int) ($usage['thoughtsTokenCount'] ?? 0),
                'total_tokens' => (int) ($usage['totalTokenCount'] ?? 0),
                'cached_tokens' => (int) ($usage['cachedContentTokenCount'] ?? 0),
            ]);
        }

        if (mb_strtoupper((string) $response->json('candidates.0.finishReason')) === 'MAX_TOKENS') {
            throw new RuntimeException('Gemini n’a pas terminé le plan éditorial avant la limite de sortie.');
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $data = is_string($text) ? json_decode($text, true) : null;
        if (! is_array($data) || ! isset($data['ideas']) || ! is_array($data['ideas'])) {
            throw new RuntimeException('Gemini n’a pas retourné de plan éditorial structuré.');
        }

        return array_slice($data['ideas'], 0, $desiredCount);
    }

    private function request(): PendingRequest
    {
        $key = Setting::value('gemini_api_key', config('services.gemini.key'));
        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('Ajoutez d’abord votre clé API Gemini dans Réglages.');
        }

        // Toujours rester sous le timeout FastCGI de Herd (60 s) : le polling
        // Livewire réessaie les timeouts transitoires cinq secondes plus tard.
        return Http::timeout(45)->connectTimeout(8)->withHeaders([
            'x-goog-api-key' => trim($key),
            'Content-Type' => 'application/json',
        ]);
    }

    private function endpoint(): string
    {
        $model = $this->model();
        if (! in_array($model, ['gemini-2.5-flash-lite', 'gemini-2.5-flash'], true)) {
            $model = 'gemini-2.5-flash-lite';
        }

        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
    }

    private function model(): string
    {
        return (string) Setting::value('gemini_model', 'gemini-2.5-flash-lite');
    }

    private function schema(): array
    {
        // Le prompt porte les descriptions et les règles métier. Répéter ces
        // textes dans le schéma, puis imposer une taille exacte sur un tableau
        // d'objets contenant lui-même deux tableaux, fait exploser l'automate de
        // validation de Gemini dès qu'un lot dépasse quelques idées.
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'ideas' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'source_keyword_id' => ['type' => 'integer'],
                            'title' => $string,
                            'primary_keyword' => $string,
                            'entity' => $string,
                            'topic' => $string,
                            'intent' => $string,
                            'angle' => $string,
                            'audience' => $string,
                            'problem' => $string,
                            'expected_outcome' => $string,
                            'funnel_stage' => $string,
                            'unique_promise' => $string,
                            'excluded_topics' => [
                                'type' => 'array',
                                'items' => $string,
                            ],
                            'outline' => [
                                'type' => 'array',
                                'items' => $string,
                            ],
                            'content_type' => ['type' => 'string', 'enum' => ['informational', 'tool_review', 'pricing', 'comparison', 'alternatives', 'best_tools']],
                            'roadmap_level' => ['type' => 'string', 'enum' => ['Level 1 - Pillar', 'Level 2 - Commercial', 'Level 3 - Long Tail', 'Level 4 - FAQ', 'Level 5 - Comparatifs', 'Level 6 - Alternatives', 'Level 7 - Tutoriels']],
                            'call_to_action' => $string,
                            'lsi_keywords' => [
                                'type' => 'array',
                                'items' => $string,
                            ],
                            'people_also_ask' => [
                                'type' => 'array',
                                'items' => $string,
                            ],
                            'tone_of_voice' => $string,
                            'schema_org' => $string,
                            'internal_links_strategy' => $string,
                        ],
                        'required' => ['source_keyword_id', 'title', 'primary_keyword', 'entity', 'topic', 'intent', 'angle', 'audience', 'problem', 'expected_outcome', 'funnel_stage', 'unique_promise', 'excluded_topics', 'outline', 'content_type', 'roadmap_level', 'call_to_action', 'lsi_keywords', 'people_also_ask', 'tone_of_voice', 'schema_org', 'internal_links_strategy'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['ideas'],
            'additionalProperties' => false,
        ];
    }
}
