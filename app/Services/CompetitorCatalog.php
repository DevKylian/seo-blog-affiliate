<?php

namespace App\Services;

use App\Models\SeoProject;
use Illuminate\Support\Str;

final class CompetitorCatalog
{
    private const MARKET_COMPETITORS = [
        'billing' => [
            'Pennylane', 'Abby', 'Freebe', 'Henrri', 'Sinao', 'Evoliz', 'Facture.net',
            'Axonaut', 'Sellsy', 'QuickBooks', 'Sage', 'Cegid', 'Tiime', 'Dougs',
            'Qonto', 'Shine', 'Blank', 'Odoo',
        ],
        'crm' => [
            'HubSpot', 'Salesforce', 'Pipedrive', 'Zoho CRM', 'Sellsy', 'Axonaut',
            'Odoo', 'noCRM.io', 'Monday CRM', 'Brevo', 'ActiveCampaign', 'Klaviyo',
        ],
        'seo' => [
            'Semrush', 'Ahrefs', 'SE Ranking', 'Sistrix', 'Moz', 'Ubersuggest',
            'Mangools', 'Ranxplorer', 'Yooda Insight', 'Majestic',
        ],
        'emailing' => [
            'Brevo', 'Mailchimp', 'MailerLite', 'ActiveCampaign', 'Klaviyo',
            'Sarbacane', 'GetResponse', 'HubSpot',
        ],
        'project' => [
            'Asana', 'Trello', 'Monday.com', 'ClickUp', 'Notion', 'Jira', 'Wrike',
        ],
        'construction' => [
            'Obat', 'Tolteck', 'Costructor', 'ProGBat', 'EBP Bâtiment',
            'Sage Batigest', 'Mediabat', 'Batappli', 'iXbat',
        ],
        'banking' => [
            'Qonto', 'Shine', 'Blank', 'BNP Paribas', 'Société Générale', 'LCL',
            'Boursorama', 'Crédit Agricole', 'La Banque Postale', 'Revolut', 'CMB',
        ],
    ];

    private const SYNTHETIC_NAMES = [
        'invoiceflow' => 'InvoiceFlow',
        'quickbill' => 'QuickBill',
        'legalinvoice' => 'LegalInvoice',
        'invoicemaster' => 'InvoiceMaster',
        'quotepro' => 'QuotePro',
        'easycompta' => 'EasyCompta',
        'financecore' => 'FinanceCore',
        'cashmaster' => 'CashMaster',
        'cashflowanalytics' => 'Cashflow Analytics',
        'digicube' => 'Digicube',
        'editionsdp' => 'Editions DP',
        'b2bnetwork' => 'B2B Network',
    ];

    private const KNOWN_NON_COMPETITOR_PHRASES = [
        'Compte Pro',
        'Chorus Pro',
        'Portail Public de Facturation',
        'Plateforme de Dematerialisation Partenaire',
        'Plateforme de Dématérialisation Partenaire',
        'Google', 'Excel', 'Word', 'Urssaf', 'Sasu', 'Eurl', 'Sarl', 'Sas',
        'Mac', 'Windows', 'Apple', 'Android', 'Ios', 'Api', 'Bnc', 'Bic',
        'Tva', 'Cfe', 'Pme', 'Tpe', 'Cpam', 'Rsi', 'Ssi', 'Cipav', 'Kbis',
        'Siret', 'Siren', 'Cnil', 'Rgpd', 'Insee', 'France', 'Paris', 'Cae', 'Sci',
        'Facebook', 'Instagram', 'Linkedin', 'Twitter', 'Tiktok', 'Youtube',
        'Internet', 'Web', 'Sms', 'App',
    ];

    /** @return string[] */
    public function competitorsFor(SeoProject $project, ?string $candidateText = null): array
    {
        $context = $this->projectContext($project);
        if ($candidateText) {
            $context .= ' ' . $candidateText;
        }
        $markets = $this->detectedMarkets($project, $context);
        $explicit = $this->cleanNames($project->competitors ?? []);
        if ($explicit !== []) {
            $verticalNames = in_array('construction', $markets, true) ? self::MARKET_COMPETITORS['construction'] : [];
            $bankingNames = in_array('banking', $markets, true) ? self::MARKET_COMPETITORS['banking'] : [];

            return $this->withoutProjectName($project, $this->cleanNames([...$explicit, ...$verticalNames, ...$bankingNames]));
        }

        $names = [];
        foreach ($markets as $market) {
            $names = [...$names, ...self::MARKET_COMPETITORS[$market]];
        }

        foreach (self::MARKET_COMPETITORS as $competitors) {
            foreach ($competitors as $competitor) {
                if ($this->containsName($context, $competitor)) {
                    $names[] = $competitor;
                }
            }
        }

        return $this->withoutProjectName($project, $this->cleanNames($names));
    }

    /** @return string[] */
    public function allowedEntities(SeoProject $project, ?string $candidateText = null): array
    {
        $explicit = $this->cleanNames($project->competitors ?? []);
        $extracted = $this->extractBrandsFromKeywordsAndSources($project);

        return $this->cleanNames([
            $project->name,
            ...$this->competitorsFor($project, $candidateText),
            ...$explicit,
            ...$extracted,
        ]);
    }

    /** @return string[] */
    public function mentionedCompetitors(SeoProject $project, string $text): array
    {
        return collect($this->competitorsFor($project, $text))
            ->filter(fn (string $name): bool => $this->containsName($text, $name))
            ->values()
            ->all();
    }

    /** @return string[] */
    public function mentionedAllowedEntities(SeoProject $project, string $text): array
    {
        return collect($this->allowedEntities($project, $text))
            ->filter(fn (string $name): bool => $this->containsName($text, $name))
            ->values()
            ->all();
    }

    /** @return string[] */
    public function invalidComparedProducts(SeoProject $project, array $products, ?string $contextText = null): array
    {
        $allowed = $this->allowedEntities($project, $contextText);

        return collect($products)
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->reject(fn (string $name): bool => $this->isAllowedName($name, $allowed) || $this->isPlanTierPhrase($name, $allowed))
            ->unique(fn (string $name): string => $this->compactKey($name))
            ->values()
            ->all();
    }

    /** @return string[] */
    public function unknownCompetitorMentions(SeoProject $project, string $text): array
    {
        $allowed = $this->allowedEntities($project, $text);
        $unknown = [];
        $compactText = $this->compactKey($text);

        foreach (self::SYNTHETIC_NAMES as $needle => $label) {
            if (str_contains($compactText, $needle) && ! $this->isAllowedName($label, $allowed)) {
                $unknown[] = $this->bestMentionLabel($text, $label);
            }
        }

        if (preg_match_all('/\b([A-Z][a-zA-Z0-9]*(?:\s+[A-Z][a-zA-Z0-9]+)*\s*(?:Pro|Cloud|Master|Advanced|Analytics|Core|Plus|Enterprise|Ultimate|V\d+|Module))\b/u', $text, $matches)) {
            foreach ($matches[1] as $suspicious) {
                // To avoid matching generic terms like "Compte Pro", "Chorus Pro", check if it's explicitly non-competitor
                $clean = trim($suspicious);
                if (! $this->isAllowedName($clean, $allowed) && ! in_array(mb_strtolower($clean), array_map('mb_strtolower', self::KNOWN_NON_COMPETITOR_PHRASES), true)) {
                    $unknown[] = $clean;
                }
            }
        }

        return collect($unknown)
            ->filter()
            ->unique(fn (string $name): string => $this->compactKey($name))
            ->values()
            ->all();
    }

    public function promptDirective(SeoProject $project): string
    {
        $competitors = $this->competitorsFor($project);
        $list = $competitors === [] ? 'Aucun concurrent reel configure.' : implode(', ', $competitors);
        $allowed = implode(', ', $this->allowedEntities($project));

        return <<<TEXT
CONCURRENTS REELS AUTORISES
Produit affilie : {$project->name}
Concurrents autorises : {$list}
- Entites autorisees dans le texte, les tableaux, la FAQ et compared_products : {$allowed}
- N'invente jamais de concurrent, de marque ou de version logicielle.
- N'invente jamais de nom propre pour un client, une entreprise exemple, un artisan, une agence ou un persona. Ecris "une entreprise de plomberie", "un artisan couvreur" ou "une PME du batiment", jamais "Plomberie Pro", "BatiMax", "Devis Express" ou un autre nom fictif.
- Les types comparison, alternatives et best_tools utilisent uniquement {$project->name} et un ou plusieurs concurrents de cette liste.
- Pour un comparatif verrouille, reprends uniquement les outils deja cites dans le H1, le brief ou la liste autorisee. N'ajoute jamais une autre marque pour completer un tableau.
- Les sections de type "outils ecartes", limites, alternatives ou FAQ restent elles aussi dans cette liste autorisee.
- Si aucun concurrent autorise ne convient au mot-cle, choisis un format mono-produit ou informationnel plutot qu'un faux comparatif.
- Ne transforme jamais un mot-cle contenant une marque inconnue en article tarifaire, avis ou comparatif.

DONNEES FACTUELLES CONCURRENTIELLES (NE JAMAIS ECRIRE "Non specifie" POUR CES OUTILS)
- Abby : Dispose d'un plan gratuit et d'une période d'essai limitée.
- Pennylane : Propose un essai gratuit de 15 jours sans carte bancaire, mais aucun plan gratuit permanent pour la comptabilité globale.
TEXT;
    }

    /** @param string[] $names @return string[] */
    private function cleanNames(array $names): array
    {
        return collect($names)
            ->flatMap(function ($name): array {
                if (is_array($name)) {
                    return $name;
                }

                return preg_split('/[\r\n;,]+/u', (string) $name) ?: [];
            })
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->unique(fn (string $name): string => $this->compactKey($name))
            ->values()
            ->all();
    }

    /** @param string[] $names @return string[] */
    private function withoutProjectName(SeoProject $project, array $names): array
    {
        return collect($names)
            ->reject(fn (string $name): bool => $this->compactKey($name) === $this->compactKey($project->name))
            ->values()
            ->all();
    }

    private function projectContext(SeoProject $project): string
    {
        $pieces = [
            $project->name,
            $project->positioning,
            $project->description,
            ...($project->features ?? []),
            ...($project->best_for ?? []),
        ];

        if ($project->exists) {
            $pieces = [
                ...$pieces,
                ...$project->keywords()->latest('id')->limit(120)->pluck('keyword')->all(),
                ...$project->sourcePages()->latest('id')->limit(12)->pluck('title')->all(),
            ];
        }

        return implode(' ', array_filter($pieces));
    }

    /** @return string[] */
    private function detectedMarkets(SeoProject $project, string $context): array
    {
        $text = ' '.$this->key($project->name.' '.$context).' ';
        $markets = [];

        if (preg_match('/\b(?:indy|facturation|facture|factures|devis|comptabilite|comptable|tresorerie|auto entrepreneur|independant)\b/u', $text) === 1) {
            $markets[] = 'billing';
        }
        if (preg_match('/\b(?:crm|pipeline|prospect|prospects|leads?|vente|commercial|relation client)\b/u', $text) === 1) {
            $markets[] = 'crm';
        }
        if (preg_match('/\b(?:seo|referencement|serp|backlinks?|keywords?|mots cles|trafic organique)\b/u', $text) === 1) {
            $markets[] = 'seo';
        }
        if (preg_match('/\b(?:emailing|newsletter|campagne email|delivrabilite|marketing automation)\b/u', $text) === 1) {
            $markets[] = 'emailing';
        }
        if (preg_match('/\b(?:gestion projet|kanban|planning|collaboration|taches?)\b/u', $text) === 1) {
            $markets[] = 'project';
        }
        if (preg_match('/\b(?:btp|batiment|chantier|chantiers|construction|travaux|devis facture batiment|facturation btp)\b/u', $text) === 1) {
            $markets[] = 'construction';
        }
        if (preg_match('/\b(?:compte pro|banque|banques|banquiers?|revolut|qonto|shine|blank|finances?)\b/u', $text) === 1) {
            $markets[] = 'banking';
        }

        foreach (self::MARKET_COMPETITORS as $market => $competitors) {
            foreach ($competitors as $competitor) {
                if ($this->containsName($project->name, $competitor)) {
                    $markets[] = $market;
                }
            }
        }

        return array_values(array_unique($markets));
    }

    private function containsName(string $haystack, string $name): bool
    {
        $normalizedHaystack = ' '.$this->key($haystack).' ';
        $normalizedName = $this->key($name);
        if ($normalizedName === '') {
            return false;
        }

        return preg_match('/(?<![a-z0-9])'.preg_quote($normalizedName, '/').'(?![a-z0-9])/u', $normalizedHaystack) === 1;
    }

    /** @param string[] $allowed */
    public function isAllowedName(string $candidate, array $allowed): bool
    {
        $candidateKey = $this->compactKey($candidate);
        if ($candidateKey === '') {
            return false;
        }

        foreach ($allowed as $name) {
            $allowedKey = $this->compactKey($name);
            if ($candidateKey === $allowedKey || str_contains($candidateKey, $allowedKey) || str_contains($allowedKey, $candidateKey)) {
                return true;
            }
        }

        return false;
    }

    private function key(string $value): string
    {
        $ascii = Str::ascii(mb_strtolower($value));
        $normalized = preg_replace('/[^a-z0-9]+/u', ' ', $ascii) ?: '';

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?: '');
    }

    private function compactKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/u', '', $this->key($value)) ?: '';
    }

    private function bestMentionLabel(string $text, string $fallback): string
    {
        if (preg_match('/\b'.preg_quote($fallback, '/').'(?:\s+(?:Max|Advanced|Evolution|Builder|Enterprise|Suite|Pro))?\b/iu', $text, $match) === 1) {
            return trim($match[0]);
        }

        return $fallback;
    }

    private function isKnownNonCompetitorPhrase(string $candidate): bool
    {
        $candidateKey = $this->compactKey($candidate);

        foreach (self::KNOWN_NON_COMPETITOR_PHRASES as $phrase) {
            $phraseKey = $this->compactKey($phrase);
            if ($candidateKey === $phraseKey || str_contains($candidateKey, $phraseKey)) {
                return true;
            }
        }

        return false;
    }

    private function isCommonTitlePhrase(string $candidate): bool
    {
        $words = preg_split('/\s+/u', $this->key($candidate)) ?: [];
        $common = [
            'logiciel', 'logiciels', 'facturation', 'facture', 'factures', 'devis',
            'guide', 'complet', 'ultime', 'meilleur', 'meilleurs', 'meilleure',
            'tarifs', 'tarif', 'prix', 'fonctionnalites', 'explique', 'expliques',
            'choisir', 'gestion', 'gratuit', 'gratuite', 'auto', 'entrepreneur',
            'pme', 'tpe', 'btp', 'batiment', 'presentation', 'processus',
            'compte', 'comptes', 'pro', 'professionnel', 'professionnels',
            'ensuite', 'comptable', 'comptabilite', 'entreprise', 'entreprises',
            'pour', 'une', 'optimale', 'licence', 'alternatives', 'ligne', 'avis',
            'plan', 'offre', 'offres', 'plus', 'premium', 'starter', 'standard', 
            'basic', 'abonnement', 'abonnements', 'cependant', 'ainsi', 'les', 'des',
            'comment', 'pourquoi', 'quel', 'quelle', 'quels', 'quelles', 'qui', 'que',
            'quoi', 'quand', 'bien', 'tres', 'tout', 'tous', 'toute', 'toutes',
            'dans', 'sur', 'avec', 'sans', 'par', 'est', 'sont', 'faire', 'fait',
            'etre', 'avoir', 'votre', 'notre', 'vos', 'nos', 'ces', 'ses', 'ces',
            'cet', 'cette', 'celui', 'ceux', 'celle', 'celles', 'ici', 'la', 'bas',
            'finances', 'publiques', 'comptables', 'plus', 'nouveau', 'nouvelle',
            'systeme', 'creation', 'micro', 'astuces', 'conseils', 'etapes',
            'avantage', 'avantages', 'inconvenients', 'methode', 'solution', 'solutions'
        ];

        return $words !== [] && count(array_diff($words, $common)) === 0;
    }

    /** @param string[] $allowed */
    private function isPlanTierPhrase(string $candidate, array $allowed): bool
    {
        $words = preg_split('/\s+/u', $this->key($candidate)) ?: [];
        if ($words === []) {
            return true;
        }

        $planModifiers = [
            'pro', 'plus', 'business', 'premium', 'starter', 'standard', 'enterprise',
            'evolution', 'advanced', 'max', 'suite', 'lite', 'basic', 'flex', 'one',
            'essential', 'essentiel', 'expert', 'cloud', 'online', 'express', 'mini',
            'offre', 'offres', 'plan', 'plans', 'formule', 'formules', 'tarif', 'tarifs',
            'pack', 'packs', 'version', 'versions', 'option', 'options', 'gratuit', 'gratuite',
            'module', 'modules', 'logiciel', 'solution', 'outil', 'plateforme',
        ];

        $remainingWords = array_values(array_diff($words, $planModifiers));

        // If removing plan modifiers leaves nothing (e.g. "Plan Plus", "Offre Pro", "Formule Premium"), it's not a competitor brand
        if ($remainingWords === []) {
            return true;
        }

        // If remaining words match an allowed entity (e.g. "Pennylane" from "Pennylane Plus"), it's an allowed plan tier
        $remainingCandidate = implode(' ', $remainingWords);

        return $this->isAllowedName($remainingCandidate, $allowed);
    }

    /** @return string[] */
    private function extractBrandsFromKeywordsAndSources(SeoProject $project): array
    {
        if (! $project->exists) {
            return [];
        }

        $entities = [];

        $keywords = $project->keywords()->latest('id')->limit(200)->pluck('keyword')->all();
        foreach ($keywords as $kw) {
            preg_match_all('/\b[A-Z][A-Za-z0-9]{1,}\b|\b(?:ebp|indy|qonto|shine|blank|dougs|tiime|cegid|sage|pennylane|axonaut|sellsy|henrri|freebe|sinao|evoliz|quickbooks|hubspot|salesforce|pipedrive|zoho|brevo|mailchimp|trello|monday|asana|notion|clickup|jira|obat|tolteck|costructor|progbat|mediabat|batappli|easycompta|financecore|accountpro)\b/iu', $kw, $matches);
            foreach ($matches[0] ?? [] as $m) {
                $clean = trim($m);
                if (mb_strlen($clean) >= 2 && ! $this->isCommonWord(mb_strtolower($clean))) {
                    $entities[] = $clean;
                }
            }
        }

        $sourcePages = $project->sourcePages()->where('status', 'verified')->get(['title', 'competitor_name']);
        foreach ($sourcePages as $page) {
            if ($page->competitor_name) {
                $entities[] = $page->competitor_name;
            }
            if ($page->title) {
                preg_match_all('/\b[A-Z][A-Za-z0-9]{1,}\b/u', $page->title, $matches);
                foreach ($matches[0] ?? [] as $m) {
                    $clean = trim($m);
                    if (mb_strlen($clean) >= 2 && ! $this->isCommonWord(mb_strtolower($clean))) {
                        $entities[] = $clean;
                    }
                }
            }
        }

        return $entities;
    }

    private function isCommonWord(string $word): bool
    {
        $common = [
            'logiciel', 'logiciels', 'comptabilite', 'comptable', 'facturation', 'facture', 'factures',
            'devis', 'gestion', 'gratuit', 'gratuite', 'en', 'ligne', 'pour', 'les', 'des', 'une', 'qui',
            'est', 'sur', 'avec', 'sans', 'dans', 'par', 'pas', 'avis', 'tarif', 'tarifs', 'prix',
            'comparatif', 'alternative', 'alternatives', 'meilleur', 'meilleurs', 'meilleure', 'guide',
            'ultime', 'complet', 'auto', 'entrepreneur', 'independant', 'freelance', 'pme', 'tpe',
            'btp', 'batiment', 'micro', 'entreprise', 'societe', 'assujetti', 'tva', 'code', 'naf',
            'siret', 'siren', 'choisir', 'analyse', 'application', 'plateforme', 'solution', 'outil',
        ];

        return in_array(mb_strtolower($word), $common, true);
    }
}
