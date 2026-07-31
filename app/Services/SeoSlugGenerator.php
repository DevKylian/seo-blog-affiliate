<?php

namespace App\Services;

use App\Models\SeoProject;
use Illuminate\Support\Str;

final class SeoSlugGenerator
{
    public function generate(SeoProject $project, array $blueprint, string $title): string
    {
        $slug = Str::slug($title);
        if (mb_strlen($slug) >= 5 && mb_strlen($slug) <= 80) {
            return $slug;
        }

        $tokens = $this->titleTokens($title);
        $product = Str::slug($project->name) ?: 'outil';

        if (!in_array($product, $tokens, true)) {
            array_unshift($tokens, $product);
        }

        return implode('-', array_slice(array_values(array_unique(array_filter($tokens))), 0, 7));
    }

    /** @return string[] */
    private function titleTokens(string $title): array
    {
        $tokens = preg_split('/-+/', Str::slug($title)) ?: [];

        return array_values(array_filter($tokens, fn (string $token) => (mb_strlen($token) >= 3 || in_array($token, ['vs', 'ia', 'rh', 'ux', 'ui'], true))
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
