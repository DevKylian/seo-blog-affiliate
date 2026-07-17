<?php

namespace App\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class SeoContentStructure
{
    private const BTP_SPECIALIST_TOOLS = [
        'Obat',
        'Tolteck',
        'Costructor',
        'ProGBat',
        'EBP Bâtiment',
        'Sage Batigest',
        'Mediabat',
        'Batappli',
        'iXbat',
    ];

    private const BTP_GENERALIST_TOOLS = [
        'Indy',
        'Abby',
        'Freebe',
        'Pennylane',
        'Henrri',
        'Sinao',
        'Evoliz',
        'Facture.net',
    ];

    private const BTP_CRITERIA = [
        'acompte',
        'situation de travaux',
        'retenue de garantie',
        'TVA bâtiment',
        'bibliothèque d’ouvrages',
        'matériaux',
        'métrés',
        'suivi chantier',
        'rentabilité chantier',
    ];

    public function for(string $type): array
    {
        $structures = $this->structures();

        if (! isset($structures[$type])) {
            throw new InvalidArgumentException("Type de contenu SEO inconnu : {$type}.");
        }

        return $structures[$type];
    }

    public function prompt(string $type, ?string $title = null, ?string $keyword = null): string
    {
        $structure = $this->for($type);
        $sections = collect($this->sectionsFor($type, $title))
            ->map(fn (string $section, int $index) => ($index + 1).'. '.$section)
            ->implode("\n");
        $listicleRule = $this->listiclePromise($title)
            ? "- Le titre promet une liste chiffrée : la section dédiée doit contenir exactement {$this->listiclePromise($title)['count']} items distincts, présentés en H3 ou dans une liste numérotée séquentielle."
            : '- Si un titre contient un nombre, respecte exactement ce nombre dans une section dédiée.';
        $verticalDirective = $this->btpGenerationDirective(trim(($title ?? '').' '.($keyword ?? '')));

        return <<<TEXT
STRUCTURE ÉDITORIALE OBLIGATOIRE — {$structure['label']}
Objectif : {$structure['objective']}
Longueur cible : {$structure['target_min']} à {$structure['target_max']} mots utiles, sans remplissage ni répétition.

Le champ body doit commencer par une « Réponse courte » claire : donne la définition, la recommandation ou la conclusion dans les 150 premiers mots et contient naturellement le mot-clé principal. N'ajoute pas de titre H1 dans body : le H1 est fourni séparément dans title.

Utilise ensuite ces sections H2 dans cet ordre. Les libellés peuvent être légèrement adaptés au mot-clé, mais aucun sujet ne doit être supprimé :
{$sections}

RÈGLES DE PROFONDEUR
- Chaque H2 comporte une réponse développée, concrète et non redondante ; utilise des H3 pour les sous-parties.
- Aucun paragraphe ne dépasse 90 mots ou 5 phrases. Sépare les idées par une ligne vide pour une lecture mobile fluide.
- Dans les sections Checklist et Outils/Ressources : 1 à 2 phrases de transition maximum, puis directement des H3 ou une liste. Chaque puce contient une seule phrase d’action de 20 mots maximum. Aucune justification, théorie ou répétition d’une section précédente.
- Chaque puce doit être une phrase grammaticale autonome et achevée. Elle ne se termine jamais par un mot coupé, une préposition, une conjonction ou un groupe nominal incomplet. Relis et réécris tout item incomplet avant de retourner le JSON.
- Garde une marge de sortie suffisante : ne commence jamais une phrase, une puce, un H3 ou une séquence que tu ne peux pas terminer intégralement.
- Une marche à suivre chronologique n’apparaît qu’à un seul endroit du document. Pour un article définitionnel/débutant, la Checklist remplace la section Méthode : ne recrée jamais une seconde procédure ailleurs.
- Toute séquence commencée par « Étape 1 », « La première étape » ou « Premièrement » contient obligatoirement une Étape 2 et une étape finale dans le même H2. Aucune séquence ne reste avortée.
{$listicleRule}
- Insère au moins un tableau Markdown de synthèse ou de décision avec des données exclusivement vérifiées.
- Le tableau doit faire choisir : au moins deux lignes de données et trois colonnes utiles, avec des différences réelles. Interdiction des tableaux remplis de « Oui ».
- Ajoute des listes, exemples de décision, scénarios et calculs concrets. Une métrique inventée n’est autorisée que comme donnée d’entrée d’une « Hypothèse de simulation » ou d’un « Scénario illustratif » explicitement étiqueté ; ne la présente jamais comme une performance réelle du produit.
- Pour chaque outil recommandé, expose au moins une limite réaliste et un conseil d’implémentation terrain (adoption, migration, formation, nettoyage des données ou déploiement).
- EXCLUSION MUTUELLE DES ENTITÉS : les logiciels retenus dans le Top, la sélection, le tableau ou l’analyse détaillée ne doivent strictement jamais réapparaître dans « Outils écartés ou informations insuffisantes ». Cette section négative cite uniquement des concurrents distincts ; vérifie les deux ensembles avant de rendre le JSON.
- Utilise le vocabulaire métier de la requête. Écarte les développements hors sujet et chaque phrase promotionnelle sans information vérifiable.
- La FAQ contient au moins 5 questions formulées en H3, avec des réponses directes et sourcées.
- La conclusion répond clairement à la requête, distingue les profils adaptés et non adaptés, puis propose une prochaine étape raisonnable.
- CONCLUSION STRICTE : le H2 final contient exactement 1 ou 2 paragraphes concis, sans H3, H4 ni liste. Arrête immédiatement le document après le deuxième paragraphe.
- La longueur vient de la profondeur d'analyse et de l'aide à la décision. N'invente jamais pour atteindre la longueur cible.
{$verticalDirective}
TEXT;
    }

    /** @return string[] */
    public function sectionsFor(
        string $type,
        ?string $title = null,
        ?string $intent = null,
        ?string $funnelStage = null,
        ?string $keyword = null,
    ): array {
        $sections = $this->for($type)['sections'];
        if ($type === 'informational' && $this->isInformationalTofu($title, $keyword, $intent, $funnelStage)) {
            $sections = array_values(array_filter(
                $sections,
                fn (string $section): bool => preg_match('/méthode.*étape|étape.*étape/iu', $section) !== 1,
            ));
        }
        $promise = $this->listiclePromise($title);
        if (! $promise) {
            return $sections;
        }

        $preferredIndexes = [
            'tool_review' => 3,
            'pricing' => 2,
            'comparison' => 3,
            'best_tools' => 3,
            'alternatives' => 4,
            'informational' => 2,
        ];
        $index = $preferredIndexes[$type] ?? 1;
        if (isset($sections[$index])) {
            $sections[$index] = $promise['heading'];
        }

        return $sections;
    }

    public function isInformationalTofu(?string $title, ?string $keyword = null, ?string $intent = null, ?string $funnelStage = null): bool
    {
        if (mb_strtolower((string) $funnelStage) === 'awareness') {
            return true;
        }

        $value = mb_strtolower(trim(($title ?? '').' '.($keyword ?? '')));

        return preg_match('/qu[’\']?est[- ]ce (?:que|qu[’\'])|c[’\']?est quoi|définition|définir|comprendre|à quoi (?:sert|ça sert)|notions? de base|débutants?/iu', $value) === 1
            && ! in_array(mb_strtolower((string) $intent), ['commercial', 'transactional'], true);
    }

    /** @return array{count:int, heading:string}|null */
    public function listiclePromise(?string $title): ?array
    {
        if (! $title || preg_match('/(?:^|\b(?:les|des|top)\s+)(\d{1,2})\s+([^:?!]{2,100})/iu', trim($title), $matches) !== 1) {
            return null;
        }

        $count = (int) $matches[1];
        if ($count < 2 || $count > 50) {
            return null;
        }

        $subject = trim((string) preg_split('/\s+(?:pour|et\s+comment|afin\s+de|qui\s+)/iu', trim($matches[2]), 2)[0]);
        $subject = rtrim($subject, " \t\n\r\0\x0B,;:-");
        if ($subject === '') {
            $subject = 'éléments à connaître';
        }

        return [
            'count' => $count,
            'heading' => 'Les '.$count.' '.$subject,
        ];
    }

    public function isBtpSoftwareRequest(?string $context): bool
    {
        $text = $this->key((string) $context);

        return preg_match('/\b(?:btp|batiment|chantier|chantiers|travaux|artisan(?:s)?\s+batiment|construction)\b/u', $text) === 1
            && preg_match('/\b(?:logiciel|outils?|application|devis|factures?|facturation|comparatif|meilleurs?|solution)\b/u', $text) === 1;
    }

    /** @return string[] */
    public function btpSpecialistTools(): array
    {
        return self::BTP_SPECIALIST_TOOLS;
    }

    /** @return string[] */
    public function btpGeneralistTools(): array
    {
        return self::BTP_GENERALIST_TOOLS;
    }

    public function btpGenerationDirective(?string $context): string
    {
        if (! $this->isBtpSoftwareRequest($context)) {
            return '';
        }

        $specialists = implode(', ', self::BTP_SPECIALIST_TOOLS);
        $generalists = implode(', ', self::BTP_GENERALIST_TOOLS);
        $criteria = implode(', ', self::BTP_CRITERIA);

        return <<<TEXT

RÈGLE VERTICALE BTP / BÂTIMENT — BLOQUANTE
- Une page ciblant logiciel/facturation/devis BTP doit inclure au moins 3 outils spécialisés BTP sourcés parmi : {$specialists}.
- Les outils généralistes ({$generalists}) peuvent être cités uniquement comme solutions adaptables ou généralistes, jamais comme logiciels de gestion de chantier dédiés.
- Distingue explicitement « spécialisé BTP » et « adaptable au BTP » dans le tableau, le verdict et les recommandations.
- Ne coche jamais « Oui » pour gestion de chantier, situations de travaux, retenues de garantie, acomptes, métrés, bibliothèque d'ouvrages ou rentabilité chantier sans preuve directe dans les sources.
- Si la preuve manque, écris « À vérifier », « non spécialisé BTP » ou « adaptable, mais pas outil chantier dédié ».
- La matrice doit couvrir les critères métier suivants quand ils sont pertinents : {$criteria}.
- N'utilise pas « frais cachés » ou « coûts cachés » ; écris « frais additionnels éventuels », « modules payants », « options » ou « limites de plan ».
- Toute simulation doit commencer par « Simulation fictive à visée pédagogique : les chiffres suivants ne sont pas une promesse de résultat. »
- N'ajoute pas un bloc tarifaire détaillé pour le seul outil affilié sur une page comparative BTP : équilibre les prix avec les outils BTP sourcés ou renvoie vers une fiche dédiée.
TEXT;
    }

    public function audit(string $body, string $type, ?string $keyword, bool $hasSources, ?string $primaryProduct = null, bool $affiliateDisclosureInjected = false, ?string $title = null): array
    {
        $structure = $this->for($type);
        preg_match_all('/[\p{L}\p{N}]+(?:[’\'\-][\p{L}\p{N}]+)*/u', strip_tags($body), $words);
        preg_match_all('/^##\s+.+$/mu', $body, $h2);
        preg_match_all('/^###\s+.+$/mu', $body, $h3);
        $h2Headings = collect($h2[0])->map(fn (string $heading) => mb_strtolower(trim(preg_replace('/^##\s+/u', '', $heading) ?: '')));
        $faqCount = $h2Headings->filter(fn (string $heading) => preg_match('/faq|questions fréquentes/iu', $heading) === 1)->count();
        $conclusionCount = $h2Headings->filter(fn (string $heading) => preg_match('/conclusion|verdict final|recommandation finale/iu', $heading) === 1)->count();
        preg_match_all('/^.*(?:hypothèse de simulation|simulation hypothétique|scénario illustratif).*$/imu', $body, $scenarioLines);
        $scenarioCount = count($scenarioLines[0] ?? []);
        $disclosureCount = preg_match_all('/(?:transparence|divulgation)\s+affili/iu', $body);
        $tableCount = preg_match_all('/^\|[^\r\n]*\|\R^\|[\s:|\-]+\|/mu', $body);

        $normalizedBody = mb_strtolower($body);
        $first150Words = mb_strtolower(implode(' ', array_slice($words[0], 0, 150)));
        $accentlessFirst150Words = Str::ascii($first150Words);
        $accentlessKeyword = $keyword ? Str::ascii(mb_strtolower($keyword)) : null;
        $requiredTermGroups = collect($structure['required_terms']);
        if ($type === 'informational' && $this->isInformationalTofu($title, $keyword)) {
            $requiredTermGroups = $requiredTermGroups->reject(
                fn (array $terms): bool => in_array('étape', $terms, true) || in_array('méthode', $terms, true),
            );
        }
        $requiredSections = $requiredTermGroups
            ->every(fn (array $terms) => collect($terms)->contains(fn (string $term) => str_contains($normalizedBody, $term)));
        $multiProduct = in_array($type, ['comparison', 'best_tools', 'alternatives'], true);
        $ecommerceRequest = $multiProduct && $this->isEcommerceRequest($keyword);
        $btpRequest = $this->isBtpSoftwareRequest(trim(($title ?? '').' '.($keyword ?? '')));

        $checks = [
            'minimum_length' => count($words[0]) >= $structure['minimum_words'],
            'structured_h2' => count($h2[0]) >= $structure['minimum_h2'],
            'bounded_h2' => count($h2[0]) <= count($this->sectionsFor($type, $title)),
            'unique_h2' => $h2Headings->unique()->count() === $h2Headings->count(),
            'detailed_subsections' => count($h3[0]) >= 5,
            'required_sections' => $requiredSections,
            'decision_table' => preg_match('/^\|.+\|\s*$\R^\|[\s:|\-]+\|/mu', $body) === 1,
            'single_decision_table' => $tableCount === 1,
            'useful_decision_table' => $this->hasUsefulDecisionTable($body),
            'comparison_limits_column' => ! $multiProduct || preg_match('/^\|[^\r\n]*\blimites?\b[^\r\n]*\|/imu', $body) === 1,
            'faq_complete' => preg_match('/^##\s+.*(?:faq|questions fréquentes)/imu', $body) === 1 && count($h3[0]) >= 5,
            'single_faq' => $faqCount === 1,
            'faq_balanced' => ! $multiProduct || ! $primaryProduct || $this->hasBalancedFaq($body, $primaryProduct),
            'conclusion_present' => preg_match('/^##\s+.*(?:conclusion|verdict|à retenir)/imu', $body) === 1,
            'single_conclusion' => $conclusionCount === 1,
            'concise_conclusion' => $this->hasConciseConclusion($body),
            'sources_attached' => $hasSources,
            'keyword_in_opening' => ! $accentlessKeyword || str_contains($accentlessFirst150Words, $accentlessKeyword),
            'direct_answer_early' => count(array_slice($words[0], 0, 150)) >= 40
                && preg_match('/réponse courte|en bref|permet|convient|consiste|désigne|recommand/iu', $first150Words) === 1,
            'realistic_weakness' => preg_match('/limite|inconvénient|point faible|désavantage|compromis/iu', $body) === 1,
            'implementation_advice' => preg_match('/migration|déploiement|adoption|nettoy|formation|paramétr|implément|accompagnement des équipes/iu', $body) === 1,
            'labeled_scenario_metric' => preg_match('/(?:hypothèse de simulation|simulation hypothétique|scénario illustratif).{0,350}\b\d+(?:[.,]\d+)?\s*(?:%|personnes?|utilisateurs?|heures?|minutes?|jours?|euros?|€)/isu', $body) === 1,
            'single_labeled_scenario' => $scenarioCount === 1,
            'scannable_lists' => preg_match('/^(?:-|\*)\s+\S+/mu', $body) === 1,
            'listicle_promise' => $this->hasPromisedList($body, $title),
            'readable_paragraphs' => $this->hasReadableParagraphs($body),
            'concise_list_sections' => $this->hasConciseListSections($body),
            'vertical_custom_objects' => ! $this->isVerticalCrmRequest(trim(($title ?? '').' '.($keyword ?? '')))
                || preg_match('/\b(?:objets?\s+personnalisés?|custom\s+objects?)\b.{0,220}\b(?:contrat|sinistre|bien\s+immobilier|chantier|police)\b/isu', $body) === 1,
            'complete_chronological_sequences' => $this->hasCompleteChronologicalSequences($body),
            'pricing_not_empty' => preg_match('/\b(?:tarif|prix)(?:s)?(?:\s+\p{L}+){0,3}\s+non communiqu/iu', $body) !== 1,
            'pricing_model_explained' => preg_match('/facturation|abonnement|par (?:siège|utilisateur|volume)|à l’usage|coût total de possession|offre|formule/iu', $body) === 1,
            'fresh_verification_date' => $this->hasCurrentVerificationDates($body),
            'ecommerce_hybrid_scope' => ! $ecommerceRequest || $this->hasEcommerceHybridScope($body),
            'safe_cost_language' => $this->hasSafeCostLanguage($body),
            'btp_specialized_scope' => ! $btpRequest || $this->hasBtpSpecializedScope($body),
            'btp_generalists_labeled_adaptable' => ! $btpRequest || $this->generalistBtpToolsAreLabeledAdaptable($body),
            'btp_no_unproved_chantier_claims' => ! $btpRequest || ! $this->hasUnprovedBtpGeneralistClaims($body),
            'btp_trade_criteria' => ! $btpRequest || $this->hasBtpTradeCriteria($body),
            'btp_simulation_disclaimer' => ! $btpRequest || $this->hasBtpSimulationDisclaimer($body),
            'affiliate_disclosure' => $affiliateDisclosureInjected || preg_match('/affili/iu', $body) === 1,
            'single_affiliate_disclosure' => $affiliateDisclosureInjected ? $disclosureCount === 0 : $disclosureCount <= 1,
        ];

        $labels = [
            'minimum_length' => "minimum {$structure['minimum_words']} mots",
            'structured_h2' => "minimum {$structure['minimum_h2']} sections H2",
            'bounded_h2' => 'aucun second plan ni H2 au-delà de la structure prévue',
            'unique_h2' => 'des titres H2 uniques',
            'detailed_subsections' => 'au moins 5 sous-sections H3',
            'required_sections' => 'tous les sujets obligatoires',
            'decision_table' => 'un tableau Markdown de décision',
            'single_decision_table' => 'un seul tableau décisionnel dans le document',
            'useful_decision_table' => 'un tableau discriminant (2 lignes, 3 colonnes, pas seulement « Oui »)',
            'comparison_limits_column' => 'une colonne « Limites » dans les tableaux multi-produits',
            'faq_complete' => 'une FAQ avec au moins 5 questions',
            'single_faq' => 'une seule FAQ',
            'faq_balanced' => 'au moins 40 % de questions FAQ généralistes ou centrées sur les alternatives',
            'conclusion_present' => 'une conclusion ou un verdict',
            'single_conclusion' => 'une seule conclusion finale',
            'concise_conclusion' => 'une conclusion limitée à deux paragraphes, sans sous-titre ni liste',
            'sources_attached' => 'des sources vérifiées attachées',
            'keyword_in_opening' => 'le mot-clé dans les 150 premiers mots',
            'direct_answer_early' => 'une réponse directe dans les 150 premiers mots',
            'realistic_weakness' => 'au moins une limite ou un inconvénient réaliste',
            'implementation_advice' => 'au moins un conseil d’implémentation terrain',
            'labeled_scenario_metric' => 'une métrique plausible dans un scénario explicitement hypothétique',
            'single_labeled_scenario' => 'un seul scénario chiffré hypothétique',
            'scannable_lists' => 'des listes à puces concrètes',
            'listicle_promise' => 'le nombre exact d’éléments promis dans le titre, dans une section dédiée',
            'readable_paragraphs' => 'des paragraphes de 90 mots et 5 phrases maximum',
            'concise_list_sections' => 'des sections Checklist/Outils composées de puces d’action en une phrase de 20 mots maximum',
            'vertical_custom_objects' => 'une modélisation métier concrète avec des objets personnalisés distincts du contact',
            'complete_chronological_sequences' => 'toute séquence commencée avec une étape 1 contient aussi une étape 2 et une étape finale',
            'pricing_not_empty' => 'aucune mention vide « tarif/prix non communiqué »',
            'pricing_model_explained' => 'le modèle de facturation ou le coût total de possession',
            'fresh_verification_date' => 'aucune date de vérification antérieure à l’année système',
            'ecommerce_hybrid_scope' => 'un outil CRM/marketing automation e-commerce et un CRM commercial traditionnel',
            'safe_cost_language' => 'un vocabulaire prix prudent, sans « frais cachés »',
            'btp_specialized_scope' => 'au moins 3 outils spécialisés BTP sourcés ou analysés',
            'btp_generalists_labeled_adaptable' => 'les outils généralistes BTP sont explicitement classés comme adaptables',
            'btp_no_unproved_chantier_claims' => 'aucune fonction chantier avancée attribuée à un généraliste sans réserve',
            'btp_trade_criteria' => 'les critères métier BTP obligatoires',
            'btp_simulation_disclaimer' => 'les simulations BTP sont annoncées comme fictives avant les chiffres',
            'affiliate_disclosure' => 'la transparence affiliée',
            'single_affiliate_disclosure' => 'un seul encart affilié, injecté par le CMS',
        ];
        $issues = collect($checks)->filter(fn (bool $passed) => ! $passed)->keys()
            ->map(fn (string $key) => $labels[$key])->values()->all();

        return [
            'passed' => $issues === [],
            'word_count' => count($words[0]),
            'h2_count' => count($h2[0]),
            'h3_count' => count($h3[0]),
            'checks' => $checks,
            'issues' => $issues,
            'structure' => $structure,
        ];
    }

    public function isProductionReadyAfterRepair(array $audit): bool
    {
        $minimumWords = (int) ($audit['structure']['minimum_words'] ?? 0);
        $minimumH2 = (int) ($audit['structure']['minimum_h2'] ?? 0);
        $checks = $audit['checks'] ?? [];

        return (int) ($audit['word_count'] ?? 0) >= max(1200, (int) floor($minimumWords * .72))
            && (int) ($audit['h2_count'] ?? 0) >= max(5, $minimumH2 - 2)
            && (int) ($audit['h3_count'] ?? 0) >= 3
            && ($checks['required_sections'] ?? false)
            && ($checks['bounded_h2'] ?? false)
            && ($checks['unique_h2'] ?? false)
            && ($checks['single_faq'] ?? false)
            && ($checks['single_conclusion'] ?? false)
            && ($checks['concise_conclusion'] ?? false)
            && ($checks['single_decision_table'] ?? false)
            && ($checks['single_affiliate_disclosure'] ?? false)
            && ($checks['sources_attached'] ?? false)
            && ($checks['realistic_weakness'] ?? false)
            && ($checks['implementation_advice'] ?? false)
            && ($checks['listicle_promise'] ?? false)
            && ($checks['complete_chronological_sequences'] ?? false)
            && ($checks['affiliate_disclosure'] ?? false);
    }

    public function hasCompleteChronologicalSequences(string $body): bool
    {
        preg_match_all('/^##\s+[^\r\n]+\R(.*?)(?=^##\s+|\z)/msu', $body, $sections);
        foreach ($sections[1] ?? [] as $section) {
            if (preg_match('/\b(?:étape\s*1|première étape|premièrement)\b/iu', $section) !== 1) {
                continue;
            }

            $hasSecond = preg_match('/\b(?:étape\s*2|deuxième étape|seconde étape|deuxièmement)\b/iu', $section) === 1;
            $hasFinal = preg_match('/\b(?:étape\s*(?:[3-9]|[1-9]\d+)|troisième étape|dernière étape|étape finale|finalement)\b/iu', $section) === 1;
            if (! $hasSecond || ! $hasFinal) {
                return false;
            }
        }

        return true;
    }

    public function hasCurrentVerificationDates(string $body, ?int $currentYear = null): bool
    {
        $currentYear ??= (int) now()->format('Y');
        $date = '(?:\d{1,2}(?:er)?\s+(?:janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre)\s+(\d{4})|(\d{4})-\d{2}-\d{2}|\d{1,2}[.\/-]\d{1,2}[.\/-](\d{4}))';
        $prefix = '(?:en\s+date\s+du|à\s+la\s+date\s+du|vérifi(?:é|ée|és|ées)\s+le|mis(?:e|es)?\s+à\s+jour\s+le|dernière\s+vérification\s*:?|(?:informations?|données?|tarifs?|prix)\s+disponibles?\s+au)';
        preg_match_all('/\b'.$prefix.'\s*'.$date.'/iu', $body, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $year = (int) collect(array_slice($match, 1))->first(fn (string $value): bool => preg_match('/^\d{4}$/', $value) === 1);
            if ($year !== 0 && $year !== $currentYear) {
                return false;
            }
        }

        return preg_match('/\b(?:analyse|tarifs?|prix|données|informations)[^.\n]{0,90}\b(?:à\s+jour\s+en|vérifi(?:é|ée|és|ées)\s+en)\s*(?!'.preg_quote((string) $currentYear, '/').'\b)20\d{2}\b/iu', $body) !== 1;
    }

    public function hasPromisedList(string $body, ?string $title): bool
    {
        $promise = $this->listiclePromise($title);
        if (! $promise) {
            return true;
        }

        preg_match_all('/^##\s+([^\r\n]+)\R(.*?)(?=^##\s+|\z)/msu', $body, $sections, PREG_SET_ORDER);
        foreach ($sections as $section) {
            if (preg_match('/faq|questions fréquentes/iu', $section[1]) === 1) {
                continue;
            }

            preg_match_all('/^\s*(\d{1,2})[.)]\s+\S+/mu', $section[2], $numbered);
            $numbers = array_map('intval', $numbered[1] ?? []);
            if ($numbers === range(1, $promise['count'])) {
                return true;
            }

            preg_match_all('/^###\s+(?:\d{1,2}[.)-]?\s*)?\S.+$/mu', $section[2], $items);
            if (count($items[0] ?? []) === $promise['count']
                && (str_contains(mb_strtolower($section[1]), (string) $promise['count'])
                    || mb_strtolower(trim($section[1])) === mb_strtolower($promise['heading']))) {
                return true;
            }
        }

        return false;
    }

    private function hasReadableParagraphs(string $body): bool
    {
        foreach (preg_split('/\n{2,}/u', trim($body)) ?: [] as $block) {
            $block = trim($block);
            if ($block === '' || preg_match('/^(?:#{2,6}\s|[-*+]\s|\d+[.)]\s|\||>|```)/u', $block) === 1) {
                continue;
            }

            // Les points d’interrogation des titres de FAQ ne sont pas des
            // phrases de prose et ne doivent pas fausser la densité mobile.
            $block = trim(preg_replace('/^#{2,6}\s+.*$/mu', '', $block) ?: $block);

            preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($block), $words);
            $sentenceCount = preg_match_all('/[.!?…](?:\s|$)/u', $block);
            if (count($words[0] ?? []) > 90 || $sentenceCount > 5) {
                return false;
            }
        }

        return true;
    }

    private function hasConciseConclusion(string $body): bool
    {
        if (preg_match('/^##\s+[^\r\n]*(?:conclusion|verdict final|recommandation finale)[^\r\n]*\R(.*?)(?=^##\s+|\z)/imsu', $body, $section) !== 1) {
            return false;
        }

        if (preg_match('/^#{3,4}\s+|^(?:[-*+]|\d+[.)])\s+/mu', $section[1]) === 1) {
            return false;
        }

        $paragraphs = collect(preg_split('/\n{2,}/u', trim($section[1])) ?: [])
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter();

        return $paragraphs->count() >= 1 && $paragraphs->count() <= 2;
    }

    private function hasConciseListSections(string $body): bool
    {
        preg_match_all('/^##\s+([^\r\n]+)\R(.*?)(?=^##\s+|\z)/msu', $body, $sections, PREG_SET_ORDER);
        foreach ($sections as $section) {
            if (preg_match('/checklist|outils?.*ressources?/iu', $section[1]) !== 1) {
                continue;
            }
            if (preg_match('/^(?:###\s+|(?:[-*+]|\d+[.)])\s+)/mu', $section[2], $marker, PREG_OFFSET_CAPTURE) !== 1) {
                return false;
            }

            $intro = trim(substr($section[2], 0, $marker[0][1]));
            preg_match_all('/[\p{L}\p{N}]+/u', $intro, $words);
            $sentences = preg_match_all('/[.!?…](?:\s|$)/u', $intro);
            if (count($words[0] ?? []) > 60 || $sentences > 2) {
                return false;
            }

            preg_match_all('/^(?:[-*+]|\d+[.)])\s+(.+)$/mu', $section[2], $items);
            foreach ($items[1] ?? [] as $item) {
                $item = preg_replace('/^\*\*[^*]+\*\*\s*/u', '', trim($item)) ?: trim($item);
                preg_match_all('/\S+/u', $item, $itemWords);
                if (count($itemWords[0] ?? []) > 20 || preg_match_all('/[.!?…](?:\s|$)/u', $item) > 1) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isVerticalCrmRequest(string $context): bool
    {
        return preg_match('/\bcrm\b/iu', $context) === 1
            && preg_match('/assurance|assureur|courtier|immobilier|agence\s+immobilière|\bbtp\b|construction|chantier/iu', $context) === 1;
    }

    private function hasUsefulDecisionTable(string $body): bool
    {
        $lines = preg_split('/\R/u', $body) ?: [];
        foreach ($lines as $index => $line) {
            if ($index === 0 || preg_match('/^\s*\|?[\s:|-]+\|[\s:|-]+\|/u', $line) !== 1) {
                continue;
            }

            $header = array_values(array_filter(array_map('trim', explode('|', $lines[$index - 1])), fn (string $cell) => $cell !== ''));
            $rows = [];
            for ($rowIndex = $index + 1; isset($lines[$rowIndex]) && str_contains($lines[$rowIndex], '|'); $rowIndex++) {
                $cells = array_values(array_filter(array_map('trim', explode('|', $lines[$rowIndex])), fn (string $cell) => $cell !== ''));
                if ($cells !== []) {
                    $rows[] = $cells;
                }
            }

            if (count($header) < 3 || count($rows) < 2) {
                continue;
            }

            $decisionCells = collect($rows)
                ->flatMap(fn (array $row) => array_slice($row, 1))
                ->map(fn (string $cell) => mb_strtolower(trim($cell, ' ✓✔️')))
                ->filter();
            if ($decisionCells->contains(fn (string $cell) => ! in_array($cell, ['oui', 'yes'], true))) {
                return true;
            }
        }

        return false;
    }

    private function hasBalancedFaq(string $body, string $primaryProduct): bool
    {
        if (preg_match('/^##\s+[^\r\n]*(?:faq|questions fréquentes)[^\r\n]*\R(.*?)(?=^##\s+|\z)/imsu', $body, $faqSection) !== 1) {
            return false;
        }

        preg_match_all('/^###\s+(.+)$/mu', $faqSection[1], $questions);
        $questions = $questions[1] ?? [];
        if (count($questions) < 5) {
            return false;
        }

        $primaryMentions = collect($questions)
            ->filter(fn (string $question) => str_contains(mb_strtolower($question), mb_strtolower($primaryProduct)))
            ->count();
        $alternativeOrGeneralQuestions = count($questions) - $primaryMentions;

        return $alternativeOrGeneralQuestions >= (int) ceil(count($questions) * 0.4);
    }

    private function isEcommerceRequest(?string $keyword): bool
    {
        return $keyword !== null
            && preg_match('/e[\s-]?commerce|boutique en ligne|vente en ligne|b2c/iu', $keyword) === 1;
    }

    private function hasEcommerceHybridScope(string $body): bool
    {
        $hasHybrid = preg_match('/\b(?:Klaviyo|Brevo|ActiveCampaign)\b/iu', $body) === 1;
        $hasTraditionalCrm = preg_match('/\b(?:Salesforce|Zoho|Pipedrive|HubSpot)\b/iu', $body) === 1;

        return $hasHybrid && $hasTraditionalCrm;
    }

    private function hasSafeCostLanguage(string $body): bool
    {
        return preg_match('/\b(?:frais|couts?) caches\b/u', $this->key($body)) !== 1;
    }

    private function hasBtpSpecializedScope(string $body): bool
    {
        return count($this->mentionedNames($body, self::BTP_SPECIALIST_TOOLS)) >= 3;
    }

    private function generalistBtpToolsAreLabeledAdaptable(string $body): bool
    {
        $mentioned = $this->mentionedNames($body, self::BTP_GENERALIST_TOOLS);
        if ($mentioned === []) {
            return true;
        }

        $text = $this->key($body);
        $hasClassification = preg_match('/\b(?:generaliste|adaptable|non specialise btp|pas specialise|pas un outil chantier dedie|outil chantier dedie)\b/u', $text) === 1;

        return $hasClassification && collect($mentioned)->every(function (string $tool) use ($text): bool {
            $toolKey = preg_quote($this->key($tool), '/');

            return preg_match('/(?:'.$toolKey.'.{0,220}(?:generaliste|adaptable|non specialise|pas specialise|pas un outil chantier dedie)|(?:generaliste|adaptable|non specialise|pas specialise|pas un outil chantier dedie).{0,220}'.$toolKey.')/u', $text) === 1;
        });
    }

    private function hasUnprovedBtpGeneralistClaims(string $body): bool
    {
        $text = $this->key($body);
        $toolKeys = collect(self::BTP_GENERALIST_TOOLS)
            ->map(fn (string $tool): string => $this->key($tool))
            ->filter()
            ->values();
        $toolPattern = $toolKeys->map(fn (string $tool): string => preg_quote($tool, '/'))->implode('|');
        if ($toolPattern === '') {
            return false;
        }

        $claimPattern = '(?:gestion de chantier integree|gestion chantier integree|situations? de travaux|retenues? de garantie|bibliotheque d ouvrages|metres|rentabilite chantier|concu(?:e)? pour ces contraintes|specialise(?:e)? btp)';
        $safePattern = '\b(?:a verifier|non specialise|adaptable|pas specialise|pas un outil chantier dedie|preuve insuffisante|selon les sources disponibles)\b';

        $advancedTableClaim = false;
        foreach (preg_split('/\R/u', $body) ?: [] as $line) {
            $lineKey = $this->key($line);
            if ($lineKey === '') {
                continue;
            }

            $isTableLine = str_contains($line, '|');
            $lineHasAdvancedClaim = preg_match('/'.$claimPattern.'/u', $lineKey) === 1;
            $lineHasGeneralist = preg_match('/(?:'.$toolPattern.')/u', $lineKey) === 1;

            if ($isTableLine && $lineHasAdvancedClaim && ! $lineHasGeneralist) {
                $advancedTableClaim = true;
                continue;
            }
            if (! $isTableLine) {
                $advancedTableClaim = false;
            }

            if (! $lineHasGeneralist) {
                continue;
            }

            $claimsAdvancedCapability = $lineHasAdvancedClaim
                || ($advancedTableClaim && preg_match('/(?<![a-z0-9])(?:oui|inclus|incluse|integre|integree)(?![a-z0-9])/u', $lineKey) === 1);

            if ($claimsAdvancedCapability && preg_match('/'.$safePattern.'/u', $lineKey) !== 1) {
                return true;
            }
        }

        preg_match_all('/(?:'.$toolPattern.').{0,180}'.$claimPattern.'|'.$claimPattern.'.{0,180}(?:'.$toolPattern.')/u', $text, $matches);

        foreach ($matches[0] ?? [] as $claim) {
            if (preg_match('/'.$safePattern.'/u', $claim) !== 1) {
                return true;
            }
        }

        return false;
    }

    private function hasBtpTradeCriteria(string $body): bool
    {
        $text = $this->key($body);
        $groups = [
            ['acompte', 'acomptes'],
            ['situation de travaux', 'situations de travaux', 'facture de situation'],
            ['retenue de garantie', 'retenues de garantie'],
            ['tva batiment', 'tva btp'],
            ['bibliotheque d ouvrages', 'ouvrages', 'materiaux', 'materiaux'],
            ['metres', 'metre'],
            ['suivi chantier', 'suivi de chantier', 'chantier'],
            ['rentabilite chantier', 'rentabilite par chantier'],
        ];

        $covered = collect($groups)->filter(
            fn (array $terms): bool => collect($terms)->contains(fn (string $term): bool => str_contains($text, $this->key($term)))
        )->count();

        return $covered >= 5;
    }

    private function hasBtpSimulationDisclaimer(string $body): bool
    {
        $text = $this->key($body);
        if (preg_match('/\b(?:reduire.{0,80}moitie|economie annuelle|economiser.{0,80}(?:eur|euros)|gain.{0,80}\d)/u', $text) !== 1) {
            return true;
        }

        return preg_match('/simulation fictive a visee pedagogique.{0,260}(?:reduire.{0,80}moitie|economie annuelle|economiser|gain|\d)/u', $text) === 1
            || preg_match('/(?:hypothese de simulation|scenario illustratif).{0,260}(?:reduire.{0,80}moitie|economie annuelle|economiser|gain|\d)/u', $text) === 1;
    }

    /** @param string[] $names @return string[] */
    private function mentionedNames(string $body, array $names): array
    {
        $text = ' '.$this->key($body).' ';

        return collect($names)
            ->filter(fn (string $name): bool => preg_match('/(?<![a-z0-9])'.preg_quote($this->key($name), '/').'(?![a-z0-9])/u', $text) === 1)
            ->values()
            ->all();
    }

    private function key(string $value): string
    {
        $value = Str::ascii(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?: '');
    }

    private function structures(): array
    {
        return [
            'tool_review' => [
                'label' => 'Avis / fiche détaillée',
                'objective' => 'Répondre à une intention d’évaluation avec un verdict nuancé, vérifiable et utile à la décision.',
                'target_min' => 2600,
                'target_max' => 3600,
                'minimum_words' => 2000,
                'minimum_h2' => 9,
                'sections' => [
                    'Verdict rapide et points clés',
                    'Présentation : qu’est-ce que l’outil ?',
                    'À qui s’adresse l’outil — et à qui il ne convient pas',
                    'Fonctionnalités principales analysées',
                    'Prise en main et cas d’usage concrets',
                    'Tarifs, formules et coût réel',
                    'Avantages et limites',
                    'Intégrations, support et conditions commerciales',
                    'Alternatives selon le profil et le budget',
                    'FAQ',
                    'Verdict final et méthodologie de vérification',
                ],
                'required_terms' => [['verdict'], ['fonctionnalit'], ['tarif', 'prix'], ['avantage'], ['limite', 'inconvénient'], ['alternative'], ['faq', 'questions fréquentes']],
            ],
            'pricing' => [
                'label' => 'Page tarifs',
                'objective' => 'Expliquer le coût réel, les différences entre offres et le meilleur choix selon chaque profil.',
                'target_min' => 2200,
                'target_max' => 3000,
                'minimum_words' => 1700,
                'minimum_h2' => 8,
                'sections' => [
                    'Tarifs en bref et modèle de facturation',
                    'Tableau comparatif des offres',
                    'Analyse détaillée de chaque formule',
                    'Facturation mensuelle ou annuelle : coût réel',
                    'Essai gratuit, offre gratuite et remboursement',
                    'Coûts additionnels, utilisateurs, taxes et limites',
                    'Quelle offre choisir selon votre profil ?',
                    'Alternatives selon le budget',
                    'FAQ sur les tarifs',
                    'Conclusion : le prix est-il justifié ?',
                ],
                'required_terms' => [['tarif', 'prix'], ['tableau', 'comparatif'], ['mensuel', 'annuel'], ['coût'], ['choisir', 'profil'], ['faq', 'questions fréquentes'], ['conclusion']],
            ],
            'comparison' => [
                'label' => 'Comparatif direct',
                'objective' => 'Comparer critère par critère et désigner le meilleur choix pour plusieurs profils sans faux gagnant universel.',
                'target_min' => 3200,
                'target_max' => 4500,
                'minimum_words' => 2500,
                'minimum_h2' => 9,
                'sections' => [
                    'Verdict rapide : quel outil choisir ?',
                    'Tableau comparatif en un coup d’œil',
                    'Positionnement et publics cibles',
                    'Fonctionnalités comparées critère par critère',
                    'Tarifs et coût réel comparés',
                    'Facilité d’utilisation et déploiement',
                    'Intégrations, support et limites',
                    'Quel outil gagne selon chaque cas d’usage ?',
                    'Alternatives aux deux solutions',
                    'FAQ du comparatif',
                    'Conclusion et méthodologie',
                ],
                'required_terms' => [['verdict'], ['comparatif', 'compar'], ['fonctionnalit'], ['tarif', 'prix', 'coût'], ['cas d’usage', 'profil'], ['alternative'], ['faq', 'questions fréquentes']],
            ],
            'best_tools' => [
                'label' => 'Sélection des meilleurs outils',
                'objective' => 'Aider à présélectionner plusieurs solutions grâce à des critères publics, des profils et des compromis explicites.',
                'target_min' => 3500,
                'target_max' => 5000,
                'minimum_words' => 2800,
                'minimum_h2' => 8,
                'sections' => [
                    'Sélection rapide des meilleures solutions',
                    'Tableau comparatif de la sélection',
                    'Méthodologie et critères de classement',
                    'Analyse détaillée de chaque outil retenu',
                    'Meilleur choix selon le profil et le cas d’usage',
                    'Comparaison des tarifs et limites',
                    'Outils écartés ou informations insuffisantes',
                    'Comment choisir et déployer la solution',
                    'FAQ',
                    'Conclusion de la sélection',
                ],
                'required_terms' => [['sélection', 'meilleur'], ['tableau', 'comparatif'], ['méthodologie', 'critère'], ['profil', 'cas d’usage'], ['tarif', 'prix'], ['faq', 'questions fréquentes'], ['conclusion']],
            ],
            'alternatives' => [
                'label' => 'Alternatives à un outil',
                'objective' => 'Expliquer pourquoi changer et recommander une alternative différente pour chaque besoin ou contrainte.',
                'target_min' => 3000,
                'target_max' => 4200,
                'minimum_words' => 2400,
                'minimum_h2' => 8,
                'sections' => [
                    'Meilleures alternatives en bref',
                    'Pourquoi chercher une alternative ?',
                    'Tableau comparatif des alternatives',
                    'Critères de sélection',
                    'Analyse détaillée de chaque alternative',
                    'Quelle alternative choisir selon votre profil ?',
                    'Tarifs, limites et coût de migration',
                    'Conseils pour migrer sans interruption',
                    'FAQ',
                    'Conclusion et recommandation finale',
                ],
                'required_terms' => [['alternative'], ['pourquoi'], ['tableau', 'comparatif'], ['critère'], ['profil'], ['tarif', 'prix', 'coût'], ['migration'], ['faq', 'questions fréquentes']],
            ],
            'informational' => [
                'label' => 'Guide informationnel',
                'objective' => 'Répondre complètement à une question, guider l’exécution et relier naturellement le sujet aux solutions documentées.',
                'target_min' => 2400,
                'target_max' => 3400,
                'minimum_words' => 1900,
                'minimum_h2' => 7,
                'sections' => [
                    'Réponse courte et définition',
                    'Pourquoi ce sujet est important',
                    'Méthode détaillée étape par étape',
                    'Exemples et scénarios concrets',
                    'Tableau récapitulatif ou matrice de décision',
                    'Erreurs fréquentes et comment les éviter',
                    'Checklist opérationnelle',
                    'Outils et ressources utiles',
                    'FAQ',
                    'Conclusion et prochaines étapes',
                ],
                'required_terms' => [['définition', 'réponse'], ['étape', 'méthode'], ['exemple', 'scénario'], ['erreur'], ['checklist'], ['outil', 'ressource'], ['faq', 'questions fréquentes'], ['conclusion']],
            ],
        ];
    }
}
