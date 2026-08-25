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
        
        if (! $article->project) {
            $recommendedProject = null;
            if (method_exists($article, 'tools')) {
                $recommendedProject = $article->tools()->first();
            }
            if (!$recommendedProject) {
                $content = mb_strtolower($article->title . ' ' . ($article->body ?? ''));
                $tools = ['pennylane', 'shine', 'qonto', 'blank', 'tiime', 'abby', 'indy'];
                $counts = [];
                foreach ($tools as $tool) {
                    $counts[$tool] = substr_count($content, $tool);
                }
                arsort($counts);
                $bestTool = key($counts);
                
                $projectName = '';
                if ($counts[$bestTool] > 0) {
                    $projectName = $bestTool;
                } else {
                    $slug = $article->slug ?? '';
                    if (str_contains($slug, 'pennylane')) {
                        $projectName = 'pennylane';
                    } elseif (str_contains($slug, 'shine')) {
                        $projectName = 'shine';
                    } elseif (str_contains($slug, 'qonto')) {
                        $projectName = 'qonto';
                    } elseif (str_contains($slug, 'blank')) {
                        $projectName = 'blank';
                    } elseif (str_contains($slug, 'tiime')) {
                        $projectName = 'tiime';
                    } elseif (str_contains($slug, 'abby')) {
                        $projectName = 'abby';
                    } else {
                        $projectName = 'indy'; // Default fallback
                    }
                }
                $recommendedProject = SeoProject::where('slug', $projectName)->first();
            }
            
            if ($recommendedProject) {
                $article->setRelation('project', $recommendedProject);
                $article->seo_project_id = $recommendedProject->id;
            }
        }

        if (! $article->project || ! $this->targetUrl($article->project)) {
            return null;
        }

        if ($this->isIndyProject($article->project)) {
            $context = mb_strtolower($article->title . ' ' . $article->primary_keyword . ' ' . $article->topic_key);
            if (str_contains($context, 'association') || str_contains($context, 'associatif')) {
                return null;
            }
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

        $block = $block ?: $this->fallback($article, $position, $cluster, $intentType);

        if ($block && $this->isIndyProject($article->project)) {
            $projectName = $article->project->name;
            if ($projectName !== 'Indy') {
                $variants = [
                    $projectName,
                    'Blog & Guides Généraux',
                    'Blog & Guides Generaux',
                    'blog & guides généraux',
                    'blog & guides generaux',
                ];
                foreach ($variants as $variant) {
                    $block->title = str_ireplace($variant, 'Indy', $block->title);
                    $block->description = str_ireplace($variant, 'Indy', $block->description);
                    $block->cta = str_ireplace($variant, 'Indy', $block->cta);
                }
            }
            
            $nonIndyDesc = "Centralisez votre facturation, vos dépenses et votre comptabilité dans un seul outil pensé pour les indépendants. Gagnez du temps dès aujourd'hui avec une solution simple, rapide à prendre en main et disponible en version gratuite.\n\n• Pensé pour les indépendants\n• Prise en main immédiate\n• Support et assistance\n• Version gratuite disponible";
            if (trim($block->description) === trim($nonIndyDesc)) {
                $block->description = "🎁 1er mois offert sans engagement\nCentralisez votre facturation, vos dépenses et votre comptabilité dans un seul outil pensé pour les indépendants. Gagnez du temps dès aujourd'hui avec une solution simple et rapide à prendre en main.";
            }
        }

        return $block;
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

        if ($url && $this->isIndyProject($project) && $article) {
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
        $isIndy = $this->isIndyProject($article->project);
        if ($isIndy) {
            $projectName = 'Indy';
        }
        $strong = in_array($intentType, ['solution', 'money'], true);
        
        $title = $isIndy ? 'Essayez Indy gratuitement' : (strtolower($projectName) === 'pennylane' ? "Découvrez {$projectName} dès 14€/mois" : "Essayez {$projectName} gratuitement");
        $description = $isIndy 
            ? "✅ 1er mois offert sans engagement\nCentralisez votre facturation, vos dépenses et votre comptabilité dans un seul outil pensé pour les indépendants. Gagnez du temps dès aujourd'hui avec une solution simple et rapide à prendre en main."
            : (strtolower($projectName) === 'pennylane' ? "Centralisez votre facturation, vos dépenses et votre comptabilité dans un seul outil. Gagnez du temps dès aujourd'hui.\n\n✅ Pensé pour les dirigeants\n✅ Prise en main immédiate\n✅ Support et assistance\n✅ Dès 14€/mois" : "Centralisez votre facturation, vos dépenses et votre comptabilité dans un outil pensé pour les indépendants.\n\n✅ Pensé pour les indépendants\n✅ Prise en main immédiate\n✅ Support et assistance");
            
        $cta = (strtolower($projectName) === 'pennylane') ? "🚀 Découvrir Pennylane" : "🚀 Créer mon compte gratuit";

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

    private function isIndyProject(?SeoProject $project): bool
    {
        if (! $project) {
            return false;
        }

        $name = \Illuminate\Support\Str::lower($project->name);
        $slug = \Illuminate\Support\Str::lower($project->slug);

        return $name === 'indy'
            || in_array($slug, ['indy', 'indy-1', 'indy-fr'], true)
            || in_array($name, ['blog & guides généraux', 'blog & guides generaux', 'guides généraux', 'guides generaux'], true)
            || str_contains($name, 'blog')
            || str_contains($name, 'guide')
            || str_contains($name, 'généraux')
            || str_contains($name, 'generaux')
            || str_contains($slug, 'blog')
            || str_contains($slug, 'guide')
            || str_contains($slug, 'generaux');
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
