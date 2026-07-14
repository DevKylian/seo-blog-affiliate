<?php

namespace App\Services;

use Illuminate\Support\Str;

final class GeneratedContentSanitizer
{
    public function sanitize(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = $this->stripSystemManagedAffiliateDisclosure($body);
        $body = $this->keepSingleScenario($body);
        $body = $this->keepSingleDecisionTable($body);
        $body = $this->keepSinglePricingExplanation($body);
        $body = $this->compactListSectionIntroductions($body);
        $body = $this->compactConclusion($body);
        $body = $this->splitDenseParagraphs($body);

        return $this->removeExactDuplicateParagraphs($body);
    }

    /**
     * Conserve exclusivement les sections H2 demandées, dans l'ordre imposé.
     * Les titres sont reconstruits côté application : une réponse Gemini ne peut
     * donc pas ajouter un second plan, une FAQ ou une conclusion parasite.
     *
     * @param  string[]  $requestedHeadings
     */
    public function keepRequestedSections(string $body, array $requestedHeadings): string
    {
        preg_match_all('/^##\s+(.+?)\R(.*?)(?=^##\s+|\z)/msu', $body, $matches, PREG_SET_ORDER);

        $available = collect($matches)->map(fn (array $match): array => [
            'heading' => trim($match[1]),
            'content' => trim($match[2]),
            'used' => false,
        ])->all();
        $result = [];

        foreach ($requestedHeadings as $position => $requestedHeading) {
            $bestIndex = null;
            $bestScore = 0.0;

            foreach ($available as $index => $section) {
                if ($section['used']) {
                    continue;
                }

                $score = $this->headingSimilarity($requestedHeading, $section['heading']);
                if ($score > $bestScore) {
                    $bestIndex = $index;
                    $bestScore = $score;
                }
            }

            if ($bestIndex === null) {
                continue;
            }

            if ($bestScore < 0.72) {
                $unusedIndexes = array_keys(array_filter($available, fn (array $section): bool => ! $section['used']));
                if (count($available) === count($requestedHeadings) && isset($unusedIndexes[$position])) {
                    $bestIndex = $unusedIndexes[$position];
                } elseif ($bestScore < 0.45) {
                    continue;
                }
            }

            $available[$bestIndex]['used'] = true;
            $result[] = '## '.trim($requestedHeading)."\n\n".trim($available[$bestIndex]['content']);
        }

        return $this->sanitize(implode("\n\n", $result));
    }

    private function stripSystemManagedAffiliateDisclosure(string $body): string
    {
        $body = preg_replace(
            '/^##\s+[^\r\n]*(?:transparence|divulgation)[^\r\n]*affili[^\r\n]*\R.*?(?=^##\s+|\z)/imsu',
            '',
            $body,
        ) ?: $body;

        return preg_replace(
            '/(?:^|\R{2})\s*[*_]{0,2}(?:transparence|divulgation)\s+affili(?:ée|e)?[*_]{0,2}\s*:.*?(?=\R{2}|\z)/isu',
            "\n\n",
            $body,
        ) ?: $body;
    }

    private function removeExactDuplicateParagraphs(string $body): string
    {
        $blocks = preg_split('/\n{2,}/u', trim($body)) ?: [];
        $seen = [];
        $kept = [];

        foreach ($blocks as $block) {
            $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($block)) ?: '');
            $fingerprint = $this->normalize($plain);
            $wordCount = preg_match_all('/[\p{L}\p{N}]+/u', $plain);

            if ($wordCount >= 25 && isset($seen[$fingerprint])) {
                continue;
            }

            if ($wordCount >= 25) {
                $seen[$fingerprint] = true;
            }
            $kept[] = trim($block);
        }

        return trim(implode("\n\n", array_filter($kept)));
    }

    private function keepSingleScenario(string $body): string
    {
        $blocks = preg_split('/\n{2,}/u', trim($body)) ?: [];
        $found = false;
        $skipFollowingParagraph = false;
        $kept = [];

        foreach ($blocks as $block) {
            if ($skipFollowingParagraph) {
                $skipFollowingParagraph = false;

                continue;
            }

            if (preg_match('/hypothèse de simulation|simulation hypothétique|scénario illustratif/iu', $block) !== 1) {
                $kept[] = $block;

                continue;
            }

            if (! $found) {
                $found = true;
                $kept[] = $block;

                continue;
            }

            if (preg_match('/^#{2,3}\s+/u', trim($block)) === 1) {
                $skipFollowingParagraph = true;
            }
        }

        return trim(implode("\n\n", $kept));
    }

    private function keepSingleDecisionTable(string $body): string
    {
        preg_match_all(
            '/^\|[^\r\n]*\|\R^\|[\s:|\-]+\|\R(?:^\|[^\r\n]*\|(?:\R|\z))+/mu',
            $body,
            $matches,
            PREG_OFFSET_CAPTURE,
        );
        $tables = $matches[0] ?? [];
        if (count($tables) <= 1) {
            return $body;
        }

        $keepIndex = 0;
        foreach ($tables as $index => [, $offset]) {
            $before = substr($body, 0, $offset);
            preg_match_all('/^##\s+(.+)$/mu', $before, $headings);
            $heading = mb_strtolower((string) collect($headings[1] ?? [])->last());
            if (preg_match('/tableau|matrice|comparatif.*coup/iu', $heading) === 1) {
                $keepIndex = $index;
                break;
            }
        }

        for ($index = count($tables) - 1; $index >= 0; $index--) {
            if ($index === $keepIndex) {
                continue;
            }
            [$table, $offset] = $tables[$index];
            $body = substr_replace($body, '', $offset, strlen($table));
        }

        return $body;
    }

    private function keepSinglePricingExplanation(string $body): string
    {
        $blocks = preg_split('/\n{2,}/u', trim($body)) ?: [];
        $candidates = [];
        foreach ($blocks as $index => $block) {
            if (preg_match('/note sur (?:les coûts|le modèle)|modèle (?:de tarification|économique)|facturation par siège/iu', $block) === 1) {
                $candidates[$index] = preg_match_all('/[\p{L}\p{N}]+/u', $block);
            }
        }
        if (count($candidates) <= 1) {
            return $body;
        }

        arsort($candidates);
        $keepIndex = array_key_first($candidates);

        return trim(collect($blocks)
            ->reject(fn (string $block, int $index): bool => isset($candidates[$index]) && $index !== $keepIndex)
            ->implode("\n\n"));
    }

    private function compactListSectionIntroductions(string $body): string
    {
        return preg_replace_callback(
            '/^##\s+([^\r\n]+)\R(.*?)(?=^##\s+|\z)/msu',
            function (array $match): string {
                if (preg_match('/checklist|outils?.*ressources?|méthode.*étapes?|étapes?/iu', $match[1]) !== 1) {
                    return $match[0];
                }
                if (preg_match('/^(?:###\s+|(?:[-*+]|\d+[.)])\s+)/mu', $match[2], $marker, PREG_OFFSET_CAPTURE) !== 1) {
                    return $match[0];
                }

                $offset = $marker[0][1];
                $intro = trim(substr($match[2], 0, $offset));
                $list = ltrim(substr($match[2], $offset));
                $sentences = preg_split('/(?<=[.!?…])\s+/u', $intro, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $intro = trim(implode(' ', array_slice($sentences, 0, 2)));

                return '## '.trim($match[1])."\n\n".($intro !== '' ? $intro."\n\n" : '').$list;
            },
            $body,
        ) ?: $body;
    }

    private function compactConclusion(string $body): string
    {
        return preg_replace_callback(
            '/^##\s+([^\r\n]*(?:conclusion|verdict final|recommandation finale)[^\r\n]*)\R(.*?)(?=^##\s+|\z)/imsu',
            function (array $section): string {
                $content = preg_split('/^###?\s+/mu', $section[2], 2)[0] ?? '';
                $blocks = collect(preg_split('/\n{2,}/u', trim($content)) ?: [])
                    ->map(fn (string $block): string => trim($block))
                    ->filter(fn (string $block): bool => $block !== '' && preg_match('/^(?:[-*+]|\d+[.)])\s+/u', $block) !== 1)
                    ->take(2)
                    ->values();

                return '## '.trim($section[1])."\n\n".$blocks->implode("\n\n");
            },
            $body,
        ) ?: $body;
    }

    private function splitDenseParagraphs(string $body): string
    {
        $blocks = preg_split('/\n{2,}/u', trim($body)) ?: [];
        $result = [];
        foreach ($blocks as $block) {
            $trimmed = trim($block);
            preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($trimmed), $words);
            if (count($words[0] ?? []) <= 90
                || preg_match('/^(?:#{2,6}\s|[-*+]\s|\d+[.)]\s|\||>|```)/u', $trimmed) === 1) {
                $result[] = $trimmed;

                continue;
            }

            $sentences = preg_split('/(?<=[.!?…])\s+(?=[\p{Lu}\d*_])/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($sentences) < 2) {
                $result[] = $trimmed;

                continue;
            }

            $chunk = [];
            $chunkWords = 0;
            foreach ($sentences as $sentence) {
                $sentenceWords = preg_match_all('/[\p{L}\p{N}]+/u', $sentence);
                if ($chunk !== [] && ($chunkWords + $sentenceWords > 80 || count($chunk) >= 3)) {
                    $result[] = implode(' ', $chunk);
                    $chunk = [];
                    $chunkWords = 0;
                }
                $chunk[] = trim($sentence);
                $chunkWords += $sentenceWords;
            }
            if ($chunk !== []) {
                $result[] = implode(' ', $chunk);
            }
        }

        return trim(implode("\n\n", array_filter($result)));
    }

    private function headingSimilarity(string $first, string $second): float
    {
        $first = $this->tokens($first);
        $second = $this->tokens($second);
        if ($first === [] || $second === []) {
            return 0.0;
        }

        $intersection = array_intersect($first, $second);
        $union = array_unique([...$first, ...$second]);

        return count($intersection) / count($union);
    }

    /** @return string[] */
    private function tokens(string $value): array
    {
        return array_values(array_unique(array_filter(
            preg_split('/\s+/u', $this->normalize($value)) ?: [],
            fn (string $token): bool => mb_strlen($token) >= 3,
        )));
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(Str::ascii($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }
}
