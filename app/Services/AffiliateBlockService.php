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

    public function targetUrl(SeoProject $project): ?string
    {
        return $project->affiliate_url ?: $project->website_url ?: null;
    }

    private function fallback(Article $article, string $position, ?string $cluster, string $intentType): AffiliateBlock
    {
        $projectName = $article->project->name;
        $strong = in_array($intentType, ['solution', 'money'], true);

        return new AffiliateBlock([
            'seo_project_id' => $article->seo_project_id,
            'affiliate_cluster' => $cluster,
            'intent_type' => $intentType,
            'position' => $position,
            'title' => $strong ? "Simplifier votre gestion avec {$projectName}" : 'Une prochaine étape possible',
            'description' => $strong
                ? "{$projectName} permet de rapprocher facturation, dépenses et obligations dans un même outil.\n\n✅ Centralisez vos outils\n✅ Automatisez vos relances\n✅ Gagnez du temps"
                : "Quand votre gestion devient répétitive, un outil comme {$projectName} peut vous aider à automatiser les tâches administratives.\n\n✅ Pensé pour les indépendants\n✅ Prise en main immédiate\n✅ Support et assistance",
            'cta' => "🎁 Profiter de l'offre gratuite",
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
