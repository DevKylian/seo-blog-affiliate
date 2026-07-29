<?php

namespace App\Services;

use App\Models\AffiliateBlock;
use App\Models\Article;
use App\Models\SeoProject;

final class AffiliateBlockService
{
    public function resolveBlock(Article $article, string $position): ?AffiliateBlock
    {
        $article->loadMissing(['project', 'keyword']);
        if (! $article->project || ! $this->targetUrl($article->project)) {
            return null;
        }

        $cluster = $article->keyword?->affiliate_cluster
            ?: $article->contentCluster?->affiliate_cluster
            ?: $this->clusterFromArticle($article);
        $intentType = $article->intent_type ?: $article->keyword?->intent_type ?: 'information';

        $query = AffiliateBlock::query()
            ->where(fn ($query) => $query->where('seo_project_id', $article->seo_project_id)->orWhereNull('seo_project_id'))
            ->where('is_active', true)
            ->where('position', $position);

        $block = (clone $query)
            ->where('intent_type', $intentType)
            ->where('affiliate_cluster', $cluster)
            ->latest('seo_project_id')
            ->first();

        $block ??= (clone $query)
            ->where('intent_type', $intentType)
            ->whereNull('affiliate_cluster')
            ->latest('seo_project_id')
            ->first();

        $block ??= (clone $query)
            ->whereNull('intent_type')
            ->where(fn ($query) => $query->where('affiliate_cluster', $cluster)->orWhereNull('affiliate_cluster'))
            ->latest('seo_project_id')
            ->first();

        return $block ?: $this->fallback($article, $position, $cluster, $intentType);
    }

    public function trackedUrl(Article $article, ?AffiliateBlock $block, string $position): string
    {
        return route('affiliate.redirect', [
            'project' => $article->project,
            'article' => $article->id,
            'block' => $block?->id,
            'position' => $position,
        ]);
    }

    public function targetUrl(SeoProject $project, ?Article $article = null, ?AffiliateBlock $block = null): ?string
    {
        $url = $project->affiliate_url ?: $project->website_url ?: null;

        if ($url && mb_strtolower($project->name) === 'indy' && $article) {
            $cluster = $block?->affiliate_cluster
                ?: $article->keyword?->affiliate_cluster
                ?: $article->contentCluster?->affiliate_cluster
                ?: $this->clusterFromArticle($article);
                
            $context = mb_strtolower($article->title . ' ' . $article->primary_keyword);

            if (str_contains($context, 'création') || str_contains($context, 'statut') || str_contains($context, 'sasu') || str_contains($context, 'auto-entreprise')) {
                return 'https://urls.fr/QDk1cj'; // Créer son entreprise
            }
            if ($cluster === 'facturation') {
                return 'https://urls.fr/qAxSuF'; // Facturation
            }
            if (str_contains($context, 'compte pro') || str_contains($context, 'banque')) {
                return 'https://urls.fr/OJ8ERj'; // Compte pro
            }
            
            return 'https://www.indy.fr/?ae=1776'; // Default Indy link
        }

        return $url;
    }

    private function fallback(Article $article, string $position, ?string $cluster, string $intentType): AffiliateBlock
    {
        $projectName = $article->project->name;
        $strong = in_array($intentType, ['solution', 'money'], true);
        
        $isIndy = mb_strtolower($projectName) === 'indy';
        
        $title = $isIndy ? 'Essayez Indy gratuitement' : "Essayez {$projectName} gratuitement";
        $description = $isIndy 
            ? "🎁 1er mois offert sans engagement\nCentralisez votre facturation, vos dépenses et votre comptabilité dans un seul outil pensé pour les indépendants. Gagnez du temps dès aujourd'hui avec une solution simple et rapide à prendre en main."
            : "Centralisez votre facturation, vos dépenses et votre comptabilité dans un seul outil pensé pour les indépendants. Gagnez du temps dès aujourd'hui avec une solution simple, rapide à prendre en main et disponible en version gratuite.\n\n• Pensé pour les indépendants\n• Prise en main immédiate\n• Support et assistance\n• Version gratuite disponible";
            
        $cta = "👉 Créer mon compte gratuit";

        if ($isIndy) {
            $context = mb_strtolower($article->title . ' ' . $article->primary_keyword);
            if (str_contains($context, 'création') || str_contains($context, 'statut') || str_contains($context, 'sasu') || str_contains($context, 'auto-entreprise')) {
                $cta = "👉 Créer son entreprise gratuitement";
            } elseif ($cluster === 'facturation') {
                $cta = "👉 Recevoir et faire ses factures gratuitement";
            } elseif (str_contains($context, 'compte pro') || str_contains($context, 'banque')) {
                $cta = "👉 Ouvrir un compte pro gratuit";
            } elseif ($cluster === 'comptabilite' || $cluster === 'declarations') {
                $cta = "👉 Automatiser sa compta et ses déclarations";
            }
        }

        return new AffiliateBlock([
            'seo_project_id' => $article->seo_project_id,
            'affiliate_cluster' => $cluster,
            'intent_type' => $intentType,
            'position' => $position,
            'title' => $title,
            'description' => $description,
            'cta' => $cta,
            'style' => $strong ? 'strong' : 'soft',
            'is_active' => true,
        ]);
    }

    private function clusterFromArticle(Article $article): ?string
    {
        $context = mb_strtolower(implode(' ', [
            $article->title,
            $article->primary_keyword,
            $article->topic_key,
            $article->content_angle,
        ]));

        return match (true) {
            str_contains($context, 'factur') || str_contains($context, 'devis') => 'facturation',
            str_contains($context, 'compta') => 'comptabilite',
            str_contains($context, 'tva') => 'tva',
            str_contains($context, 'urssaf') || str_contains($context, 'charges') => 'declarations',
            str_contains($context, 'depense') || str_contains($context, 'frais') => 'depenses',
            str_contains($context, 'logiciel') || str_contains($context, 'outil') => 'outils',
            default => null,
        };
    }
}
