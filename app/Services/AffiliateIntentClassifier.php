<?php

namespace App\Services;

use Illuminate\Support\Str;

final class AffiliateIntentClassifier
{
    /** @return array{affiliate_cluster:string,intent_type:string,affiliate_priority:float,user_moment:string,problem_label:string,solution_label:string} */
    public function classify(string $keyword, string $brand = '', ?string $semrushIntent = null, float $volume = 0, float $difficulty = 0, ?float $cpc = null): array
    {
        $normalized = $this->normalize($keyword);
        $cluster = $this->cluster($normalized);
        $intentType = $this->intentType($normalized, $brand);
        $priority = $this->priority($cluster, $intentType, $normalized, $semrushIntent, $volume, $difficulty, (float) ($cpc ?? 0), $brand);

        return [
            'affiliate_cluster' => $cluster,
            'intent_type' => $intentType,
            'affiliate_priority' => $priority,
            'user_moment' => match ($intentType) {
                'money' => 'pret-a-s-inscrire',
                'solution' => 'cherche-un-outil',
                default => 'cherche-une-reponse',
            },
            'problem_label' => $this->problemLabel($cluster),
            'solution_label' => $this->solutionLabel($cluster),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function defaultSeeds(): array
    {
        return [
            ['seed' => 'facture freelance', 'affiliate_cluster' => 'facturation', 'intent_type' => 'information', 'indy_fit' => 5, 'variations' => ['facture freelance + metier', 'facture freelance + TVA', 'facture freelance + statut']],
            ['seed' => 'facture auto entrepreneur', 'affiliate_cluster' => 'facturation', 'intent_type' => 'information', 'indy_fit' => 5],
            ['seed' => 'logiciel facture freelance', 'affiliate_cluster' => 'facturation', 'intent_type' => 'solution', 'indy_fit' => 5],
            ['seed' => 'devis freelance', 'affiliate_cluster' => 'facturation', 'intent_type' => 'information', 'indy_fit' => 5],
            ['seed' => 'comptabilite freelance', 'affiliate_cluster' => 'comptabilite', 'intent_type' => 'information', 'indy_fit' => 5],
            ['seed' => 'comptabilite auto entrepreneur', 'affiliate_cluster' => 'comptabilite', 'intent_type' => 'information', 'indy_fit' => 5],
            ['seed' => 'logiciel comptabilite freelance', 'affiliate_cluster' => 'comptabilite', 'intent_type' => 'solution', 'indy_fit' => 5],
            ['seed' => 'creer micro entreprise', 'affiliate_cluster' => 'creation', 'intent_type' => 'information', 'indy_fit' => 4],
            ['seed' => 'devenir auto entrepreneur', 'affiliate_cluster' => 'creation', 'intent_type' => 'information', 'indy_fit' => 4],
            ['seed' => 'declaration chiffre affaires micro entreprise', 'affiliate_cluster' => 'declarations', 'intent_type' => 'information', 'indy_fit' => 4],
            ['seed' => 'declaration urssaf freelance', 'affiliate_cluster' => 'declarations', 'intent_type' => 'information', 'indy_fit' => 4],
            ['seed' => 'charges freelance', 'affiliate_cluster' => 'declarations', 'intent_type' => 'information', 'indy_fit' => 4],
            ['seed' => 'TVA freelance', 'affiliate_cluster' => 'tva', 'intent_type' => 'information', 'indy_fit' => 5],
            ['seed' => 'seuil TVA micro entreprise', 'affiliate_cluster' => 'tva', 'intent_type' => 'information', 'indy_fit' => 5],
            ['seed' => 'suivi depenses freelance', 'affiliate_cluster' => 'depenses', 'intent_type' => 'solution', 'indy_fit' => 4],
            ['seed' => 'note de frais freelance', 'affiliate_cluster' => 'depenses', 'intent_type' => 'information', 'indy_fit' => 4],
            ['seed' => 'compte bancaire freelance', 'affiliate_cluster' => 'banque', 'intent_type' => 'information', 'indy_fit' => 3],
            ['seed' => 'micro entreprise freelance', 'affiliate_cluster' => 'statuts', 'intent_type' => 'information', 'indy_fit' => 4],
            ['seed' => 'SASU freelance', 'affiliate_cluster' => 'statuts', 'intent_type' => 'information', 'indy_fit' => 4],
            ['seed' => 'freelance developpeur', 'affiliate_cluster' => 'metiers', 'intent_type' => 'information', 'indy_fit' => 4, 'variations' => ['comptabilite + metier', 'facture + metier', 'statut + metier', 'charges + metier']],
            ['seed' => 'logiciel freelance', 'affiliate_cluster' => 'outils', 'intent_type' => 'solution', 'indy_fit' => 5],
            ['seed' => 'outil administratif freelance', 'affiliate_cluster' => 'outils', 'intent_type' => 'solution', 'indy_fit' => 5],
            ['seed' => 'Indy avis', 'affiliate_cluster' => 'outils', 'intent_type' => 'money', 'indy_fit' => 5],
            ['seed' => 'Indy tarif', 'affiliate_cluster' => 'outils', 'intent_type' => 'money', 'indy_fit' => 5],
            ['seed' => 'Indy alternatives', 'affiliate_cluster' => 'outils', 'intent_type' => 'money', 'indy_fit' => 5],
            ['seed' => 'faut il freelance', 'affiliate_cluster' => 'questions', 'intent_type' => 'information', 'indy_fit' => 4],
            ['seed' => 'comment freelance', 'affiliate_cluster' => 'questions', 'intent_type' => 'information', 'indy_fit' => 4],
        ];
    }

    private function intentType(string $normalized, string $brand): string
    {
        $brandKey = $this->normalize($brand);
        if ($brandKey !== '' && str_contains($normalized, $brandKey)) {
            return 'money';
        }
        if (preg_match('/\b(?:avis|prix|tarifs?|cout|code promo|essai|alternative|alternatives|vs|versus)\b/u', $normalized) === 1) {
            return 'money';
        }
        if (preg_match('/\b(?:logiciel|outil|application|comparatif|meilleurs?|solution|generateur|createur)\b/u', $normalized) === 1) {
            return 'solution';
        }

        return 'information';
    }

    private function cluster(string $normalized): string
    {
        return match (true) {
            preg_match('/\b(?:facture|factures|facturation|devis)\b/u', $normalized) === 1 => 'facturation',
            preg_match('/\b(?:comptabilite|comptable|recettes|depenses|tresorerie)\b/u', $normalized) === 1 => 'comptabilite',
            preg_match('/\b(?:creer|ouvrir|devenir|debuter|siret|formalites|inscription)\b/u', $normalized) === 1 => 'creation',
            preg_match('/\b(?:urssaf|declaration|declarer|charges|cotisations|chiffre affaires|obligations)\b/u', $normalized) === 1 => 'declarations',
            preg_match('/\b(?:tva|franchise|seuil|assujetti|recuperer)\b/u', $normalized) === 1 => 'tva',
            preg_match('/\b(?:note frais|frais professionnel|justificatif|scanner)\b/u', $normalized) === 1 => 'depenses',
            preg_match('/\b(?:banque|compte bancaire|compte pro|neobanque)\b/u', $normalized) === 1 => 'banque',
            preg_match('/\b(?:statut|sasu|ei|portage|micro entreprise)\b/u', $normalized) === 1 => 'statuts',
            preg_match('/\b(?:developpeur|graphiste|consultant|designer|coach|formateur|redacteur|photographe)\b/u', $normalized) === 1 => 'metiers',
            preg_match('/\b(?:logiciel|outil|application|indy|alternative|comparatif)\b/u', $normalized) === 1 => 'outils',
            preg_match('/\b(?:comment|faut il|peut on|combien|quand|pourquoi)\b/u', $normalized) === 1 => 'questions',
            default => 'general',
        };
    }

    private function priority(string $cluster, string $intentType, string $normalized, ?string $semrushIntent, float $volume, float $difficulty, float $cpc, string $brand): float
    {
        $base = match ($cluster) {
            'facturation', 'comptabilite', 'tva', 'outils' => 74,
            'declarations', 'depenses' => 66,
            'creation', 'statuts', 'metiers' => 58,
            'banque' => 45,
            default => 38,
        };
        $intentBoost = match ($intentType) {
            'money' => 18,
            'solution' => 11,
            default => 0,
        };
        $commercialBoost = preg_match('/commercial|transaction|achat|buy|c\b/iu', (string) $semrushIntent) === 1 ? 5 : 0;
        $cpcBoost = min(8, $cpc * 1.5);
        $volumeBoost = min(6, log10($volume + 10) * 1.4);
        $difficultyPenalty = min(10, $difficulty * .08);
        $brandBoost = $brand !== '' && str_contains($normalized, $this->normalize($brand)) ? 6 : 0;

        return round(min(100, max(0, $base + $intentBoost + $commercialBoost + $cpcBoost + $volumeBoost + $brandBoost - $difficultyPenalty)), 2);
    }

    private function problemLabel(string $cluster): string
    {
        return match ($cluster) {
            'facturation' => 'creer et suivre des factures conformes',
            'comptabilite' => 'tenir une comptabilite freelance fiable',
            'creation' => 'lancer son activite sans oublier les demarches',
            'declarations' => 'declarer et anticiper ses obligations',
            'tva' => 'comprendre les seuils et factures avec TVA',
            'depenses' => 'suivre ses frais et justificatifs',
            'banque' => 'choisir un compte adapte a son activite',
            'statuts' => 'choisir le bon statut juridique',
            'metiers' => 'adapter la gestion administrative a son metier',
            'outils' => 'choisir le bon outil de gestion freelance',
            default => 'resoudre un probleme administratif freelance',
        };
    }

    private function solutionLabel(string $cluster): string
    {
        return match ($cluster) {
            'facturation' => 'automatiser factures, devis et suivi de paiement',
            'comptabilite' => 'centraliser recettes, depenses et comptabilite',
            'creation' => 'suivre une checklist de demarrage claire',
            'declarations' => 'eviter les erreurs de declaration',
            'tva' => 'surveiller les seuils et mentions obligatoires',
            'depenses' => 'automatiser le suivi des depenses',
            'banque' => 'relier le compte et le suivi administratif',
            'statuts' => 'comparer les options avant de se lancer',
            'metiers' => 'utiliser des modeles adaptes au metier',
            'outils' => 'comparer les logiciels utiles',
            default => 'avancer avec des guides et outils pratiques',
        };
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/u', ' ', Str::ascii(mb_strtolower($value))) ?: '');
    }
}
