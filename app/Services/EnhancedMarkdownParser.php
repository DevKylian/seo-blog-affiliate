<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;

class EnhancedMarkdownParser
{
    public function parse(string $markdown): string
    {
        // 1. Process custom blocks (e.g., :::quick-answer, :::takeaway, :::case, :::faq)
        $markdown = $this->parseCustomBlocks($markdown);

        // 2. Process shortcodes (e.g., [calculator:tva])
        $markdown = $this->parseShortcodes($markdown);

        // 3. Convert standard markdown to HTML
        return Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    private function parseCustomBlocks(string $markdown): string
    {
        // quick-answer block
        $markdown = preg_replace_callback('/:::quick-answer\s*(.*?)\s*:::/s', function ($matches) {
            $content = Str::markdown(trim($matches[1]));
            return "<div class=\"blog-block quick-answer\">
                <div class=\"block-header\">⚡ Réponse rapide</div>
                <div class=\"block-content\">{$content}</div>
            </div>";
        }, $markdown);

        // takeaway block
        $markdown = preg_replace_callback('/:::takeaway\s*(.*?)\s*:::/s', function ($matches) {
            $content = Str::markdown(trim($matches[1]));
            return "<div class=\"blog-block takeaway\">
                <div class=\"block-header\">✅ En résumé</div>
                <div class=\"block-content\">{$content}</div>
            </div>";
        }, $markdown);

        // case block
        $markdown = preg_replace_callback('/:::case\s*(.*?)\s*:::/s', function ($matches) {
            $content = Str::markdown(trim($matches[1]));
            return "<div class=\"blog-block concrete-case\">
                <div class=\"block-header\">🎯 Cas concret</div>
                <div class=\"block-content\">{$content}</div>
            </div>";
        }, $markdown);

        // faq block
        $markdown = preg_replace_callback('/:::faq\s*(.*?)\s*:::/s', function ($matches) {
            $items = trim($matches[1]);
            $html = '<div class="blog-faq-accordion">';
            
            // split by ## Question
            $parts = preg_split('/^##\s+(.+)$/m', $items, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            
            for ($i = 0; $i < count($parts); $i += 2) {
                if (isset($parts[$i+1])) {
                    $question = trim($parts[$i]);
                    $answer = Str::markdown(trim($parts[$i+1]));
                    $html .= "<details class=\"faq-item\">
                        <summary>{$question}</summary>
                        <div class=\"faq-content\">{$answer}</div>
                    </details>";
                }
            }
            $html .= '</div>';
            return $html;
        }, $markdown);

        return $markdown;
    }

    private function parseShortcodes(string $markdown): string
    {
        return preg_replace_callback('/\[calculator:([a-zA-Z0-9_-]+)\]/', function ($matches) {
            $name = $matches[1];
            if (View::exists("components.calculators.{$name}")) {
                return View::make("components.calculators.{$name}")->render();
            }
            return '';
        }, $markdown);
    }
}
