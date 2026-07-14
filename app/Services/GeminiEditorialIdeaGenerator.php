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
        int $desiredCount,
        array $excludedFingerprints = [],
        string $instructions = '',
        int $attempt = 1,
    ): array {
        $currentDate = now()->locale('fr')->translatedFormat('j F Y');
        $currentYear = now()->format('Y');
        $keywordData = $keywords->take(120)->map(fn (Keyword $keyword) => array_filter([
            'keyword_id' => $keyword->id,
            'keyword' => $keyword->keyword,
            'volume' => $keyword->search_volume,
            'difficulty' => $keyword->keyword_difficulty,
            'intent' => $keyword->intent,
            'cluster' => $keyword->cluster,
            'opportunity' => $keyword->opportunity_score,
            'strategy_tier' => $keyword->strategyTier(),
            'new_for_planning' => $keyword->isUnplanned(),
        ], fn ($value) => $value !== null && $value !== ''))->values()->all();
        $keywordsJson = json_encode($keywordData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $excludedJson = json_encode(array_values(array_unique($excludedFingerprints)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $publishedPillars = $project->articles()
            ->where('status', 'published')
            ->with('keyword')
            ->get()
            ->filter(fn ($article) => $article->keyword?->strategyTier() === 'pillar')
            ->count();
        $strategy = $publishedPillars < 2
            ? "PHASE FONDATIONS : {$publishedPillars}/2 pilier(s) publié(s). Propose d’abord jusqu’à 2 piliers génériques distincts, puis consacre les idées restantes aux quick_win et niches."
            : 'PHASE EXPANSION : les fondations existent. Au moins 60 % des idées doivent cibler strategy_tier quick_win ou niche, avec une intention et un angle réellement distincts.';

        $prompt = <<<PROMPT
Tu es le moteur de planification éditoriale d’un site SEO affilié. Tu ne rédiges aucun article.

Crée {$desiredCount} idées réellement distinctes pour {$project->name} à partir des mots-clés importés. C’est le cycle {$attempt}.
Variables système injectées par l’application : CURRENT_DATE = {$currentDate} ; CURRENT_YEAR = {$currentYear}.
Consignes utilisateur : {$instructions}
Stratégie de portefeuille : {$strategy}

Pour chaque idée, fournis un brief autonome : source_keyword_id, title, primary_keyword, entity, topic, intent, angle, audience, problem, expected_outcome, funnel_stage, unique_promise, excluded_topics, outline et content_type.

RÈGLES BLOQUANTES
- Une variante lexicale n’est pas une nouvelle idée. L’unité est sujet + intention + angle + audience + résultat.
- source_keyword_id doit recopier exactement le keyword_id d’une ligne fournie. primary_keyword doit recopier exactement le keyword de cette même ligne, sans le reformuler. N’invente jamais un KD et ne rattache jamais une idée à un mot-clé seulement voisin.
- Regroupe les synonymes génériques (« logiciel devis facture », « logiciel facture devis », « logiciel pour devis et facture ») dans un seul pilier. Ne crée jamais une page par formulation.
- strategy_tier pillar sert aux pages d’autorité larges et très complètes ; quick_win sert aux faibles KD précis ; niche sert aux métiers, plateformes ou profils spécifiques.
- Un quick_win reste un contenu expert et complet. Faible difficulté ne signifie jamais contenu court, superficiel ou produit en série sans valeur.
- Analyse en priorité les mots-clés marqués new_for_planning=true : ils viennent d’être ajoutés et n’ont encore produit ni article ni idée éditoriale.
- Un nouveau mot-clé n’impose pas automatiquement un nouvel article. S’il est synonyme d’un sujet déjà couvert, rattache-le au cluster existant et propose plutôt un angle réellement distinct parmi les autres nouveaux mots-clés.
- L’angle décrit une opération ou une décision précise. Interdits : general, guide-pratique, guide-complet, presentation-generale, vue-ensemble.
- Le mini-plan contient 5 à 8 H2 précis. Deux idées ne doivent pas partager plus de 3 H2.
- Le mini-plan est déjà un contrat de rédaction : chaque H2 répond à une question distincte, respecte la promesse du titre et évite les intitulés génériques (« Pourquoi c’est important », « Guide complet », « Tout savoir »).
- La promesse, le problème et le résultat doivent être concrets et différents d’une idée à l’autre.
- Respecte strictement le métier du produit et l’intention du mot-clé. Aucun sujet forcé.
- Mono-produit : utilise informational, tool_review ou pricing ; jamais comparison, alternatives ou best_tools, et jamais « comparatif » ou « meilleur » dans le titre.
- Comparison : nomme explicitement les deux solutions dans le titre avec « X vs Y » et prévois au moins 2 solutions dans le plan.
- Alternatives : le titre part explicitement du produit affilié sous la forme « Alternatives à {$project->name}… » et le plan confronte au moins 2 solutions nommées.
- Best_tools : le titre annonce un Top ou un nombre d’outils supérieur ou égal à 2 ; le plan prévoit une section distincte pour chaque logiciel retenu.
- Les exclusions empêchent la future rédaction de dériver vers les autres idées du lot.
- Ne retourne jamais body, introduction, paragraphes ou article complet.
- Si un title promet un nombre (« Les 10… », « 5 étapes… »), outline contient exactement ce nombre d’items identifiables ou prévoit explicitement une section dédiée qui les portera.
- Pour un article définitionnel/débutant (ToFu : « Qu’est-ce que… », « définition », « comprendre »), ne prévois jamais à la fois une méthode chronologique et une checklist du même processus. Conserve uniquement la checklist opérationnelle ; utilise les autres H2 pour la définition, les concepts, les exemples, les erreurs, les outils et la FAQ.
- ANCRAGE TEMPOREL DES TITRES : si un H1 nécessite une année d’actualité, utilise exclusivement CURRENT_YEAR ({$currentYear}). Toute autre année d’actualité est interdite. Ne mentionne pas CURRENT_DATE dans le titre.
- ORTHOGRAPHE DES MARQUES : recopie chaque nom sans espace parasite, sans lettre isolée ajoutée devant et avec sa casse officielle. N’écris jamais « Hu bspot », « Hub Spot », « H HubSpot » ou « Sales Force ». Formes canoniques : HubSpot, Salesforce, Odoo, Zoho CRM, Pipedrive, Brevo, Klaviyo, ActiveCampaign, Semrush et Ahrefs.

Empreintes déjà couvertes ou refusées — ne pas les reformuler :
{$excludedJson}

Mots-clés disponibles :
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
                        ],
                        'required' => ['source_keyword_id', 'title', 'primary_keyword', 'entity', 'topic', 'intent', 'angle', 'audience', 'problem', 'expected_outcome', 'funnel_stage', 'unique_promise', 'excluded_topics', 'outline', 'content_type'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['ideas'],
            'additionalProperties' => false,
        ];
    }
}
