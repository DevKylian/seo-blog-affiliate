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
    ];

    private const SYNTHETIC_NAMES = [
        'invoiceflow' => 'InvoiceFlow',
        'quickbill' => 'QuickBill',
        'legalinvoice' => 'LegalInvoice',
        'invoicemaster' => 'InvoiceMaster',
        'quotepro' => 'QuotePro',
        'easycompta' => 'EasyCompta',
        'financecore' => 'FinanceCore',
    ];

    private const KNOWN_NON_COMPETITOR_PHRASES = [
        'Compte Pro',
        'Chorus Pro',
        'Portail Public de Facturation',
        'Plateforme de Dematerialisation Partenaire',
        'Plateforme de Dématérialisation Partenaire',
    ];

    /** @return string[] */
    public function competitorsFor(SeoProject $project): array
    {
        $context = $this->projectContext($project);
        $markets = $this->detectedMarkets($project, $context);
        $explicit = $this->cleanNames($project->competitors ?? []);
        if ($explicit !== []) {
            $verticalNames = in_array('construction', $markets, true) ? self::MARKET_COMPETITORS['construction'] : [];

            return $this->withoutProjectName($project, $this->cleanNames([...$explicit, ...$verticalNames]));
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
    public function allowedEntities(SeoProject $project): array
    {
        return $this->cleanNames([$project->name, ...$this->competitorsFor($project)]);
    }

    /** @return string[] */
    public function mentionedCompetitors(SeoProject $project, string $text): array
    {
        return collect($this->competitorsFor($project))
            ->filter(fn (string $name): bool => $this->containsName($text, $name))
            ->values()
            ->all();
    }

    /** @return string[] */
    public function mentionedAllowedEntities(SeoProject $project, string $text): array
    {
        return collect($this->allowedEntities($project))
            ->filter(fn (string $name): bool => $this->containsName($text, $name))
            ->values()
            ->all();
    }

    /** @return string[] */
    public function invalidComparedProducts(SeoProject $project, array $products): array
    {
        $allowed = $this->allowedEntities($project);

        return collect($products)
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->reject(fn (string $name): bool => $this->isAllowedName($name, $allowed))
            ->unique(fn (string $name): string => $this->compactKey($name))
            ->values()
            ->all();
    }

    /** @return string[] */
    public function unknownCompetitorMentions(SeoProject $project, string $text): array
    {
        $allowed = $this->allowedEntities($project);
        $unknown = [];
        $compactText = $this->compactKey($text);

        foreach (self::SYNTHETIC_NAMES as $needle => $label) {
            if (str_contains($compactText, $needle) && ! $this->isAllowedName($label, $allowed)) {
                $unknown[] = $this->bestMentionLabel($text, $label);
            }
        }

        preg_match_all('/\b[A-Z][A-Za-z0-9]{2,}(?:\s+(?:[A-Z][A-Za-z0-9]{2,}|Max|Advanced|Evolution|Builder|Enterprise|Suite|Pro)){0,3}\b/u', $text, $matches);
        foreach ($matches[0] ?? [] as $candidate) {
            $candidate = trim($candidate);
            if (
                $candidate === ''
                || $this->isAllowedName($candidate, $allowed)
                || $this->isKnownNonCompetitorPhrase($candidate)
                || $this->isCommonTitlePhrase($candidate)
            ) {
                continue;
            }

            $compact = $this->compactKey($candidate);
            $looksSynthetic = preg_match('/(?:invoice|bill|quote|legal|flow|master|builder|compta|finance|core)/u', $compact) === 1
                || preg_match('/(?:max|advanced|evolution|builder|enterprise|suite|pro|business|plus)$/u', $compact) === 1;

            if ($looksSynthetic) {
                $unknown[] = $candidate;
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
    private function isAllowedName(string $candidate, array $allowed): bool
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
            'basic', 'abonnement', 'abonnements',
        ];

        return $words !== [] && count(array_diff($words, $common)) === 0;
    }
}
