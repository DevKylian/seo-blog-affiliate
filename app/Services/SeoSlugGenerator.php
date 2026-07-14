<?php

namespace App\Services;

use App\Models\SeoProject;
use Illuminate\Support\Str;

final class SeoSlugGenerator
{
    public function generate(SeoProject $project, array $blueprint, string $title): string
    {
        // Les règles sémantiques ne doivent dépendre que du titre public. Un
        // topic large comme « crm-facturation » était auparavant injecté dans
        // chaque contexte et donnait le même slug à tous les articles facture.
        $context = Str::ascii(mb_strtolower($title));
        $product = Str::slug($project->name) ?: 'outil';

        $tokens = match (true) {
            preg_match('/panorama.*crm|outils?.*relation client/u', $context) === 1 => ['panorama', 'outils', 'crm'],
            preg_match('/evenement|participant|exposant/u', $context) === 1 => [$product, 'crm', 'evenementiel'],
            preg_match('/qualite.*donnee|data.management|gouvernance.*donnee/u', $context) === 1 => [$product, 'qualite', 'donnees', 'crm'],
            preg_match('/saas/u', $context) === 1 && preg_match('/pme|tpe/u', $context) === 1 => ['avantages', 'crm', 'saas', 'pme'],
            preg_match('/conseil|consultant|cabinet/u', $context) === 1 => [$product, 'crm', 'activite', 'conseil'],
            preg_match('/cycle.*vente|pipeline commercial/u', $context) === 1 => [$product, 'etapes', 'cycle', 'vente'],
            preg_match('/prix|tarif|cout/u', $context) === 1 => ['prix', 'logiciel', 'crm', 'criteres'],
            preg_match('/retail|magasin|commerce physique/u', $context) === 1 => [$product, 'crm', 'retail'],
            preg_match('/synchronis.*factur|factur.*synchronis/u', $context) === 1 => [$product, 'synchroniser', 'crm', 'facturation'],
            preg_match('/\bcrm\b/u', $context) === 1 && preg_match('/devis|factur/u', $context) === 1 => [$product, 'automatiser', 'devis', 'factures'],
            preg_match('/ticket|support client|service client/u', $context) === 1 => [$product, 'gerer', 'tickets', 'support'],
            preg_match('/alternative/u', $context) === 1 && preg_match('/e.?commerce|b2c/u', $context) === 1 => ['alternatives', $product, 'crm', 'ecommerce'],
            default => $this->titleTokens($title),
        };

        return implode('-', array_slice(array_values(array_unique(array_filter($tokens))), 0, 5));
    }

    /** @return string[] */
    private function titleTokens(string $title): array
    {
        $tokens = preg_split('/-+/', Str::slug($title)) ?: [];

        return array_values(array_filter($tokens, fn (string $token) => mb_strlen($token) >= 3
            && ! in_array($token, $this->stopWords(), true)));
    }

    private function stopWords(): array
    {
        return [
            'avec', 'comment', 'dans', 'des', 'une', 'pour', 'selon', 'sur', 'les', 'aux', 'par',
            'quel', 'quelle', 'quels', 'quelles',
            'votre', 'vos', 'leur', 'leurs', 'reel', 'activite', 'maitriser', 'optimiser', 'guide',
            'complet', 'complete', 'efficacement', 'efficacite', 'solutions',
        ];
    }
}
