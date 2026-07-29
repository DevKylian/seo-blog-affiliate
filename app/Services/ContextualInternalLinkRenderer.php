<?php

namespace App\Services;

use App\Models\Article;
use App\Models\InternalLink;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use Illuminate\Support\Str;

final class ContextualInternalLinkRenderer
{
    public function render(string $markdown, Article $article): string
    {
        $markdown = app(GeneratedContentSanitizer::class)->stripSourceMarkers($markdown);
        $html = app(EnhancedMarkdownParser::class)->parse($markdown);
        $links = $article->internalLinks
            ->filter(fn (InternalLink $link): bool => $link->target?->status === 'published')
            ->take(3)
            ->values();
        if ($links->isEmpty() || trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="businesskit-content">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $root = $document->getElementById('businesskit-content');
        if (! $root) {
            return $html;
        }

        $usedParagraphs = [];
        foreach ($links as $link) {
            if ($this->alreadyLinksToTarget($xpath, $link)) {
                continue;
            }

            if ($this->linkExistingPhrase($document, $xpath, $link)) {
                continue;
            }

            $paragraph = $this->bestParagraph($xpath, $link, $usedParagraphs);
            if ($paragraph) {
                $usedParagraphs[] = spl_object_id($paragraph);
                $this->appendContextSentence($document, $paragraph, $link);
            }
        }

        return $this->innerHtml($root);
    }

    private function alreadyLinksToTarget(DOMXPath $xpath, InternalLink $link): bool
    {
        $targetUrl = $link->target?->public_url;
        if (! is_string($targetUrl) || $targetUrl === '') {
            return false;
        }

        $anchors = $xpath->query('//*[@id="businesskit-content"]//a[@href]');
        foreach ($anchors ?: [] as $anchor) {
            if ($anchor instanceof DOMElement && rtrim($anchor->getAttribute('href'), '/') === rtrim($targetUrl, '/')) {
                $anchor->setAttribute('class', trim($anchor->getAttribute('class').' contextual-internal-link'));

                return true;
            }
        }

        return false;
    }

    private function linkExistingPhrase(DOMDocument $document, DOMXPath $xpath, InternalLink $link): bool
    {
        $anchor = trim($link->anchor_text);
        if (mb_strlen($anchor) < 4) {
            return false;
        }

        $nodes = $xpath->query('//*[@id="businesskit-content"]//*[self::p or self::li]//text()[not(ancestor::a)]');
        foreach ($nodes ?: [] as $node) {
            if (! $node instanceof DOMText) {
                continue;
            }
            $position = mb_stripos($node->nodeValue, $anchor);
            if ($position === false) {
                continue;
            }

            $before = mb_substr($node->nodeValue, 0, $position);
            $match = mb_substr($node->nodeValue, $position, mb_strlen($anchor));
            $after = mb_substr($node->nodeValue, $position + mb_strlen($anchor));
            $fragment = $document->createDocumentFragment();
            if ($before !== '') {
                $fragment->appendChild($document->createTextNode($before));
            }
            $element = $document->createElement('a');
            $element->setAttribute('href', $link->target->public_url);
            $element->setAttribute('class', 'contextual-internal-link');
            $element->appendChild($document->createTextNode($match));
            $fragment->appendChild($element);
            if ($after !== '') {
                $fragment->appendChild($document->createTextNode($after));
            }
            $node->parentNode?->replaceChild($fragment, $node);

            return true;
        }

        return false;
    }

    /** @param int[] $usedParagraphs */
    private function bestParagraph(DOMXPath $xpath, InternalLink $link, array $usedParagraphs): ?DOMElement
    {
        $targetText = implode(' ', [
            $link->target->title,
            $link->target->primary_keyword,
            $link->target->topic_key,
            $link->target->content_angle,
            $link->target->unique_promise,
        ]);
        $targetTokens = $this->tokens($targetText);
        $best = null;
        $bestScore = -1;
        $paragraphs = $xpath->query('//*[@id="businesskit-content"]//p[not(ancestor::blockquote)]');
        foreach ($paragraphs ?: [] as $paragraph) {
            if (! $paragraph instanceof DOMElement || in_array(spl_object_id($paragraph), $usedParagraphs, true)) {
                continue;
            }
            $text = trim($paragraph->textContent);
            if (mb_strlen($text) < 90 || $paragraph->getElementsByTagName('a')->length > 0) {
                continue;
            }
            $score = count(array_intersect($targetTokens, $this->tokens($text)));
            if ($score > $bestScore) {
                $best = $paragraph;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function appendContextSentence(DOMDocument $document, DOMElement $paragraph, InternalLink $link): void
    {
        $paragraph->appendChild($document->createTextNode(' '.$this->sentenceStart($link)));
        $anchor = $document->createElement('a');
        $anchor->setAttribute('href', $link->target->public_url);
        $anchor->setAttribute('class', 'contextual-internal-link');
        $anchor->appendChild($document->createTextNode($link->anchor_text));
        $paragraph->appendChild($anchor);
        $paragraph->appendChild($document->createTextNode($this->sentenceEnd($link)));
    }

    private function sentenceStart(InternalLink $link): string
    {
        return match ($link->target->type) {
            'pricing' => 'Pour anticiper vos coûts, notre guide sur ',
            'comparison' => 'Si vous hésitez sur le choix de votre outil, notre comparatif détaillé sur ',
            'alternatives' => 'Si cette solution ne vous correspond pas tout à fait, notre sélection d\'alternatives concernant ',
            'best_tools' => 'Pour faire le meilleur choix pour votre activité, découvrez notre classement sur ',
            'tool_review' => 'Pour aller plus loin, nous avons testé en détail cette solution dans notre avis sur ',
            default => 'Pour en savoir plus sur ce sujet, n\'hésitez pas à consulter ',
        };
    }

    private function sentenceEnd(InternalLink $link): string
    {
        return match ($link->target->type) {
            'pricing' => ' explique les différences de prix et ce que chaque abonnement inclut réellement.',
            'comparison' => ' vous aidera à y voir plus clair selon votre profil.',
            'alternatives' => ' vous présentera des options plus adaptées à vos besoins.',
            'best_tools' => ' qui regroupe les outils les plus performants du marché.',
            'tool_review' => ' où nous partageons notre retour d\'expérience concret.',
            default => ' qui approfondit ces concepts avec des exemples concrets.',
        };
    }

    /** @return string[] */
    private function tokens(string $value): array
    {
        $tokens = preg_split('/[^a-z0-9]+/', mb_strtolower(Str::ascii($value))) ?: [];

        return array_values(array_unique(array_filter($tokens, fn (string $token): bool => strlen($token) >= 4)));
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?: '';
        }

        return $html;
    }
}
