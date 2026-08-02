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

Crée {$desiredCount} idées d'articles sémantiquement intéressantes pour {$project->name}. C’est le cycle {$attempt}.
Variables système injectées par l’application : CURRENT_DATE = {$currentDate} ; CURRENT_YEAR = {$currentYear}.
Consignes utilisateur : {$instructions}
Stratégie de portefeuille : {$strategy}

LES QUATRE TYPOLOGIES DE CONTENUS (à utiliser pour catégoriser et orienter chaque idée) :

1. PILIERS (informational)
Objectif : construire des guides de fond qui asseyent l'autorité du site sur des sujets métiers larges et distincts (TVA, statut juridique, notes de frais, immobilisations, paie, clôture d'exercice, etc.) — pas des comparatifs ni des listes d'outils.
* Contrainte technique unique : content_type = 'informational' (obligatoire).
* Diversification : Propose des sujets aussi variés que possible entre eux. Si deux de tes idées touchent un terrain proche, garde la meilleure et remplace l'autre par un angle différent.
* Orientation : Consulte la liste de titres déjà publiés fournie en contexte pour t'orienter vers des zones encore peu couvertes — ce n'est pas grave si un recoupement t'échappe, le filtre de publication en code fera la vérification finale.

2. QUESTIONS / HOW-TO
Objectif : répondre directement à une question ou un besoin pratique ('Comment faire X avec Y ?', 'Comment configurer X ?', 'Quelle méthode pour...').
* Contrainte technique unique : content_type = 'question' (obligatoire).
* Structure attendue dans le brief : prévois un plan avec une réponse directe en intro (format Featured Snippet), des étapes numérotées, un exemple concret, les pièges à éviter et une synthèse finale.
* Diversification : Diversifie les sujets entre eux dans ce lot. Si plusieurs idées répondent au même besoin sous un angle proche, garde la plus utile et explore autre chose pour les suivantes.
* Orientation : Consulte la liste de titres déjà publiés pour repérer les terrains déjà bien couverts et privilégier ce qui manque encore au site.

3. MONEY PAGES / CONVERSION
Objectif : requêtes transactionnelles très spécifiques, orientées décision d'achat.
* Contrainte technique unique : content_type doit être choisi parmi les types de conversion ('pricing', 'comparison', 'alternatives', 'best_tools', 'tool_review').
* Structure attendue dans le brief : prévois un tableau de prix, la mention d'un simulateur si pertinent, et un CTA clair. Les contraintes factuelles sur le sourçage des avantages du CTA seront appliquées lors de la rédaction par le rédacteur ; ne mentionne pas cette contrainte dans le titre ou l'angle.
* Diversification et Orientation : Diversifie les sujets entre eux dans ce lot, et vise des terrains encore peu exploités par rapport aux titres déjà publiés fournis en contexte.

4. TRAFIC DE MASSE / INTERCEPTION
Objectif : requêtes navigationnelles — avis détaillés, alternatives, décryptage d'offres. Ton analytique et comparatif.
* Contrainte technique unique : content_type doit être choisi parmi ('tool_review', 'comparison', 'alternatives', 'best_tools').
* Structure attendue dans le brief : précise dans le brief que le rédacteur devra vérifier les prix et fonctionnalités à la date de publication à partir des sources fournies.
* Diversification et Orientation : Diversifie les sujets entre eux dans ce lot, et vise des terrains encore peu exploités par rapport aux titres déjà publiés fournis en contexte.


ORIENTATIONS ET PRÉFÉRENCES (Le filtre déterministe côté code assurera la validation stricte et finale en aval) :

- CONCURRENTS DANS LES TITRES COMPARATIFS (OBLIGATOIRE) : Pour tout article comparatif (type 'comparison' ou 'best_tools'), tu DOIS nommer explicitement dans le titre au moins un concurrent réel choisi dans cette liste, en fonction de la catégorie du sujet :
  * Logiciels compta/facturation : Pennylane, Dougs, Abby, Tiime, Freebe
  * Logiciels BTP : Obat, Tolteck, Costructor, ProGBat, EBP Bâtiment, Sage Batigest, Mediabat, Batappli, iXbat
  * Banques pro : Qonto, Shine, BNP Paribas, Société Générale, LCL, Boursorama, Crédit Agricole, La Banque Postale, Revolut, CMB
  Ne propose jamais de titre générique type "les meilleures options" ou "alternatives à Indy" sans nommer précisément lequel de ces concurrents est comparé.

- COMPARATIFS DÉJÀ PUBLIÉS (INTERDICTION DE REPROPOSER CES PAIRES) :
  Indy vs Pennylane · Indy vs Dougs · Indy vs Abby
  Priorise activement les combinaisons encore inexplorées en comparatif direct avec Indy : Shine, Qonto, Tiime, Freebe, ainsi que les banques (BNP Paribas, Société Générale, LCL, Boursorama, Crédit Agricole, La Banque Postale, Revolut, CMB) et les outils BTP spécialisés.

- COMPARATIF BTP (OBLIGATOIRE) : Pour tout article comparatif BTP, cite au moins 3 outils spécialisés (parmi Obat, Tolteck, Costructor, ProGBat, EBP Bâtiment, Sage Batigest, Mediabat, Batappli, iXbat) dans le titre ou le plan, plutôt qu'un face-à-face à deux.

- FORMAT ALTERNATIVES (OBLIGATOIRE) : Pour tout contenu de type 'alternatives à X' (type 'alternatives'), nomme explicitement l'alternative retenue dans le titre (ex: "X vs Y" ou "X vs Y : alternatives" plutôt que "X et ses alternatives").

- COMPATIBILITÉ DES ENTITÉS : Indy ne gérant pas certains statuts (comme les associations loi 1901, CSE, agriculture, LMP, LMNP), privilégie des sujets ciblant plutôt : micro-entreprise, auto-entrepreneur, SASU, SAS, EURL, SARL, SCI, SCM, EI, EIRL, freelance, indépendant.
- PÉRIMÈTRE THÉMATIQUE : Vise de préférence des sujets liés à la comptabilité, liasse fiscale, facturation, devis, création d'entreprise, statuts juridiques, ou comptes bancaires professionnels. Le mot "compte pro" doit désigner un compte bancaire (ex: Qonto, Shine, Indy), pas un compte client sur une plateforme tierce.
- DIVERSIFICATION ET DOUBLONS : Suggère des sujets et angles variés. Pour t'orienter, compare tes idées aux titres déjà publiés. Un recoupement occasionnel ou une estimation de similarité imprécise n'est pas grave car le filtre de publication automatique en code se chargera de la déduplication et de la vérification stricte en fin de chaîne.
- NOMS DE LOGICIELS : Utilise uniquement des outils existants et connus du marché français (comme Indy, Qonto, Shine, Pennylane, Freebe, Abby, Henrri, Sage, Excel, etc.) ou ceux cités dans les sujets stratégiques en contexte. Évite les noms de logiciels inventés ou fictifs.
- ORDRE DE PRIORITÉ CONSEILLÉ : Privilégie les pages piliers (Pillar) avant de proposer des contenus secondaires ou de longue traîne.
- STRUCTURATION DU BRIEF :
  * Le mini-plan (outline) doit être structuré de manière professionnelle avec idéalement 5 à 8 H2. Évite les H2 génériques du type "Introduction" ou "Conclusion" et privilégie des titres précis et parlants.
  * Pour un type 'comparison', mentionne explicitement les deux solutions comparées dans le titre (ex: X vs Y) et prévois au moins deux solutions concurrentes dans le plan.
  * Pour un type 'alternatives', pars du produit ou d'un concurrent (ex: Alternatives à X).
  * Pour 'question', formule un titre de question directe ou tutoriel.
  * L'objectif de conversion (conversion_goal) doit correspondre au but commercial de la page ('create_company', 'invoice', 'account', 'accounting', 'plus', 'micro', 'general').
  * Remplis le reste des champs de manière riche et détaillée pour faciliter la rédaction ultérieure (PAA, mots-clés LSI, audience, problème, etc.).
  * thumbnail_title : Résume le titre en 7 mots MAX (percutant) pour la miniature de l'article.
  * cited_software_brands : Liste des marques/logiciels cités ou visés par ton idée (ex: ["Indy", "Qonto"]), ou un tableau vide [] si générique.

LANGUE FRANÇAISE
- Rédige tous les champs visibles en français naturel avec les accents normaux de la langue française.
- Les slugs restent gérés par l'application.
- Exception technique : primary_keyword recopie le mot-clé Semrush, ou le titre du Sujet Stratégique s'il n'y a pas de mot-clé exact.

{$competitorDirective}

Pour chaque idée, fournis un brief autonome au format JSON, structuré selon la roadmap : source_keyword_id, title, thumbnail_title, primary_keyword, entity, topic, intent, angle, audience, problem, expected_outcome, funnel_stage, unique_promise, excluded_topics, outline, content_type, roadmap_level, call_to_action, conversion_goal, lsi_keywords, cited_software_brands, people_also_ask, tone_of_voice, schema_org, internal_links_strategy.

Empreintes déjà couvertes ou refusées (fournies pour t'aider à t'orienter, sans bloquer ta créativité) :
{$excludedJson}

Sujets Stratégiques (Knowledge Graph) :
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
        
        // Filet de sécurité déterministe pour corriger les hallucinations courantes avant décodage
        if (is_string($text)) {
            $text = preg_replace('/\bHenri(?!\s+Valdan)\b/u', 'Henrri', $text);
        }
        
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
                            'thumbnail_title' => $string,
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
                            'content_type' => ['type' => 'string', 'enum' => ['informational', 'tool_review', 'pricing', 'comparison', 'alternatives', 'best_tools', 'question']],
                            'roadmap_level' => ['type' => 'string', 'enum' => ['Level 1 - Pillar', 'Level 2 - Commercial', 'Level 3 - Long Tail', 'Level 4 - FAQ', 'Level 5 - Comparatifs', 'Level 6 - Alternatives', 'Level 7 - Tutoriels']],
                            'call_to_action' => $string,
                            'conversion_goal' => ['type' => 'string', 'enum' => ['create_company', 'invoice', 'account', 'accounting', 'plus', 'micro', 'general']],
                            'lsi_keywords' => [
                                'type' => 'array',
                                'items' => $string,
                            ],
                            'cited_software_brands' => [
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
                        'required' => ['source_keyword_id', 'title', 'thumbnail_title', 'primary_keyword', 'entity', 'topic', 'intent', 'angle', 'audience', 'problem', 'expected_outcome', 'funnel_stage', 'unique_promise', 'excluded_topics', 'outline', 'content_type', 'roadmap_level', 'call_to_action', 'conversion_goal', 'lsi_keywords', 'people_also_ask', 'tone_of_voice', 'schema_org', 'internal_links_strategy'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['ideas'],
            'additionalProperties' => false,
        ];
    }
}
