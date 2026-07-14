<?php

namespace App\Services;

use App\Models\Keyword;
use App\Models\SeoProject;
use Illuminate\Support\Str;

final class ProductKeywordMatcher
{
    private array $lexicons = [];

    public function matches(SeoProject $project, Keyword $keyword): bool
    {
        $keywordTokens = $this->tokens($keyword->keyword);
        $productNameTokens = $this->tokens($project->name, false);

        if (array_intersect($keywordTokens, $productNameTokens) !== []) {
            return true;
        }

        $subjectTokens = array_values(array_diff($keywordTokens, $this->genericQueryTerms()));
        if ($subjectTokens === []) {
            return false;
        }

        return array_intersect($subjectTokens, $this->lexicon($project)) !== [];
    }

    private function lexicon(SeoProject $project): array
    {
        if (isset($this->lexicons[$project->id])) {
            return $this->lexicons[$project->id];
        }

        $project->loadMissing(['sourcePages.evidenceChunks']);
        $context = collect([
            $project->name,
            $project->positioning,
            $project->description,
            ...($project->features ?? []),
            ...($project->best_for ?? []),
            ...$project->sourcePages->pluck('title')->all(),
            ...$project->sourcePages->flatMap->evidenceChunks->pluck('source_excerpt')->take(100)->all(),
        ])->filter()->implode(' ');

        $tokens = $this->tokens($context, false);
        foreach ($this->semanticFamilies() as $family) {
            if (array_intersect($tokens, $family) !== []) {
                $tokens = [...$tokens, ...$family];
            }
        }

        return $this->lexicons[$project->id] = array_values(array_unique($tokens));
    }

    private function tokens(string $value, bool $removeStopWords = true): array
    {
        $normalized = Str::ascii(mb_strtolower($value));
        preg_match_all('/[a-z0-9]{2,}/', $normalized, $matches);
        $tokens = array_values(array_unique($matches[0] ?? []));

        return $removeStopWords
            ? array_values(array_diff($tokens, $this->stopWords()))
            : $tokens;
    }

    private function genericQueryTerms(): array
    {
        return [
            'avis', 'test', 'prix', 'tarif', 'tarifs', 'cout', 'gratuit', 'gratuite', 'payant',
            'meilleur', 'meilleure', 'meilleurs', 'comparatif', 'comparaison', 'alternative',
            'alternatives', 'logiciel', 'logiciels', 'outil', 'outils', 'solution', 'solutions',
            'guide', 'choisir', 'entreprise', 'entreprises', 'pme', 'tpe', 'france', 'francais',
        ];
    }

    private function stopWords(): array
    {
        return [
            'avec', 'avoir', 'dans', 'des', 'elle', 'elles', 'entre', 'est', 'faire', 'les', 'leur',
            'leurs', 'mais', 'notre', 'nous', 'par', 'pas', 'pour', 'que', 'quel', 'quelle', 'quels',
            'quelles', 'qui', 'sans', 'ses', 'son', 'sur', 'une', 'vos', 'votre', 'aux', 'du', 'de',
            'la', 'le', 'un', 'ou', 'vs', 'comment', 'pourquoi', '2025', '2026',
        ];
    }

    private function semanticFamilies(): array
    {
        return [
            ['seo', 'referencement', 'serp', 'backlink', 'backlinks', 'mot', 'mots', 'cle', 'cles', 'keyword', 'keywords', 'organique', 'trafic'],
            ['crm', 'client', 'clients', 'prospect', 'prospects', 'lead', 'leads', 'pipeline', 'vente', 'ventes', 'commercial', 'commerciaux'],
            ['emailing', 'email', 'emails', 'newsletter', 'newsletters', 'campagne', 'campagnes', 'delivrabilite', 'automation'],
            ['comptabilite', 'comptable', 'facture', 'factures', 'facturation', 'tresorerie', 'devis'],
            ['projet', 'projets', 'tache', 'taches', 'kanban', 'planning', 'collaboration'],
            ['ecommerce', 'boutique', 'panier', 'catalogue', 'produit', 'produits', 'commande', 'commandes'],
            ['rh', 'recrutement', 'salarie', 'salaries', 'paie', 'conge', 'conges', 'talent'],
        ];
    }
}
