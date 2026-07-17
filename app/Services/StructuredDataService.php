<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Plan;
use App\Models\SeoProject;

final class StructuredDataService
{
    /** @return array<string, mixed> */
    public function article(Article $article): array
    {
        $url = $article->public_url;
        $project = $article->project;

        return $this->clean([
            '@context' => 'https://schema.org',
            '@graph' => [
                $this->organization(),
                $this->website(),
                $this->breadcrumb([
                    ['name' => 'Accueil', 'url' => route('home')],
                    ['name' => 'Guides', 'url' => route('blog.index')],
                    ['name' => $article->title, 'url' => $url],
                ], $url),
                [
                    '@type' => 'Article',
                    '@id' => $url.'#article',
                    'mainEntityOfPage' => ['@id' => $url],
                    'headline' => $article->title,
                    'description' => $article->meta_description ?: $article->excerpt,
                    'datePublished' => $article->published_at?->toAtomString(),
                    'dateModified' => $article->updated_at?->toAtomString(),
                    'author' => ['@id' => $this->organizationId()],
                    'publisher' => ['@id' => $this->organizationId()],
                    'isPartOf' => ['@id' => $this->websiteId()],
                    'about' => $project ? $this->softwareApplication($project, route('tools.show', $project->slug), false) : null,
                    'keywords' => $this->articleKeywords($article),
                ],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function tool(SeoProject $tool): array
    {
        $url = route('tools.show', $tool->slug);

        return $this->toolGraph($tool, $url, [
            ['name' => 'Accueil', 'url' => route('home')],
            ['name' => 'Logiciels', 'url' => route('tools.index')],
            ['name' => $tool->name, 'url' => $url],
        ]);
    }

    /** @return array<string, mixed> */
    public function pricing(SeoProject $tool): array
    {
        $url = route('tools.pricing', $tool->slug);

        return $this->toolGraph($tool, $url, [
            ['name' => 'Accueil', 'url' => route('home')],
            ['name' => 'Logiciels', 'url' => route('tools.index')],
            ['name' => $tool->name, 'url' => route('tools.show', $tool->slug)],
            ['name' => 'Tarifs', 'url' => $url],
        ]);
    }

    /** @param array<int, array{name:string,url:string}> $breadcrumbs @return array<string, mixed> */
    private function toolGraph(SeoProject $tool, string $url, array $breadcrumbs): array
    {
        return $this->clean([
            '@context' => 'https://schema.org',
            '@graph' => [
                $this->organization(),
                $this->website(),
                $this->breadcrumb($breadcrumbs, $url),
                $this->softwareApplication($tool, $url, true),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => $this->organizationId(),
            'name' => 'FreelanceOS',
            'url' => route('home'),
        ];
    }

    /** @return array<string, mixed> */
    private function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => $this->websiteId(),
            'name' => 'FreelanceOS',
            'url' => route('home'),
            'publisher' => ['@id' => $this->organizationId()],
        ];
    }

    /** @param array<int, array{name:string,url:string}> $items @return array<string, mixed> */
    private function breadcrumb(array $items, string $url): array
    {
        return [
            '@type' => 'BreadcrumbList',
            '@id' => $url.'#breadcrumb',
            'itemListElement' => collect($items)->values()->map(fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function softwareApplication(SeoProject $tool, string $url, bool $includeOffers): array
    {
        return $this->clean([
            '@type' => 'SoftwareApplication',
            '@id' => route('tools.show', $tool->slug).'#software',
            'name' => $tool->name,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'url' => $tool->website_url ?: $url,
            'description' => $tool->description ?: $tool->positioning,
            'offers' => $includeOffers ? $this->offers($tool, $url) : null,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function offers(SeoProject $tool, string $url): array
    {
        return $tool->plans
            ->filter(fn (Plan $plan): bool => $plan->is_active && $plan->priceMinimum() !== null)
            ->map(fn (Plan $plan): array => $this->clean([
                '@type' => 'Offer',
                'name' => $plan->name,
                'price' => $plan->priceMinimum(),
                'priceCurrency' => $plan->currency ?: $tool->currency ?: 'EUR',
                'url' => $url,
                'availability' => 'https://schema.org/InStock',
            ]))
            ->values()
            ->all();
    }

    private function organizationId(): string
    {
        return route('home').'#organization';
    }

    private function websiteId(): string
    {
        return route('home').'#website';
    }

    private function articleKeywords(Article $article): ?string
    {
        $keywords = collect([
            $article->primary_keyword,
            $article->keyword?->keyword,
            ...$article->categories->pluck('name')->all(),
            ...$article->tags->pluck('name')->all(),
        ])->filter()->unique()->values();

        return $keywords->isNotEmpty() ? $keywords->implode(', ') : null;
    }

    private function clean(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $cleaned = array_map(fn (mixed $item): mixed => $this->clean($item), $value);
        $cleaned = array_filter($cleaned, fn (mixed $item): bool => ! ($item === null || $item === '' || $item === []));

        return array_is_list($value) ? array_values($cleaned) : $cleaned;
    }
}
