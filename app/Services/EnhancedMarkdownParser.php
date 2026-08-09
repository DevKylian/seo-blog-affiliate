<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;

class EnhancedMarkdownParser
{
    public function parse(string $markdown): string
    {
        // 0. Auto-format standard markdown FAQs into custom blocks
        $markdown = $this->autoFormatFaq($markdown);

        // 1. Process custom blocks (e.g., :::quick-answer, :::takeaway, :::case, :::faq)
        $markdown = $this->parseCustomBlocks($markdown);

        // 2. Process shortcodes (e.g., [calculator:tva])
        $markdown = $this->parseShortcodes($markdown);

        // 3. Convert standard markdown to HTML (allow HTML so our custom block divs aren't stripped)
        return Str::markdown($markdown, ['html_input' => 'allow', 'allow_unsafe_links' => false]);
    }

    private function autoFormatFaq(string $markdown): string
    {
        // Match standard FAQs (## Foire aux questions, ## FAQ, etc) up to the next H2 or end of string
        return preg_replace_callback('/^##\s+(?:FAQ|Foire aux questions|Questions fr[ée]quentes|Questions Fr[ée]quentes).*?(?=(?:^##\s)|\z)/ism', function ($matches) {
            $faqSection = $matches[0];
            
            // Extract the title
            if (!preg_match('/^##\s+(.+)$/m', $faqSection, $titleMatch)) {
                return $faqSection;
            }
            $title = trim($titleMatch[1]);
            
            $html = "<div class=\"blog-faq-accordion\">\n";
            $html .= "<h2>" . htmlspecialchars($title) . "</h2>\n";
            
            // Split by H3 to get questions and answers
            $parts = preg_split('/^###\s+(.+)$/m', $faqSection, -1, PREG_SPLIT_DELIM_CAPTURE);
            
            for ($i = 1; $i < count($parts); $i += 2) {
                $question = trim($parts[$i]);
                $answerMarkdown = trim($parts[$i + 1] ?? '');
                
                if ($answerMarkdown) {
                    $answerHtml = Str::markdown($answerMarkdown, ['html_input' => 'allow', 'allow_unsafe_links' => false]);
                    $html .= "<details class=\"faq-item\">\n";
                    $html .= "    <summary>" . htmlspecialchars($question) . "</summary>\n";
                    $html .= "    <div class=\"faq-content\">\n" . trim($answerHtml) . "\n    </div>\n";
                    $html .= "</details>\n";
                }
            }
            
            $html .= "</div>\n\n";
            
            return $html;
        }, $markdown);
    }

    private function parseCustomBlocks(string $markdown): string
    {
        // quick-answer block
        $markdown = preg_replace_callback('/:::quick-answer\s*(.*?)\s*:::/s', function ($matches) {
            $content = Str::markdown(trim($matches[1]));
            return "<div class=\"blog-block quick-answer\">
                <div class=\"block-header\">⚡ Réponse rapide</div>
                <div class=\"block-content\">{$content}</div>
            </div>\n\n";
        }, $markdown);

        // takeaway block
        $markdown = preg_replace_callback('/:::takeaway\s*(.*?)\s*:::/s', function ($matches) {
            $content = Str::markdown(trim($matches[1]));
            return "<div class=\"blog-block takeaway\">
                <div class=\"block-header\">✅ En résumé</div>
                <div class=\"block-content\">{$content}</div>
            </div>\n\n";
        }, $markdown);

        // case block
        $markdown = preg_replace_callback('/:::case\s*(.*?)\s*:::/s', function ($matches) {
            $content = Str::markdown(trim($matches[1]));
            return "<div class=\"blog-block concrete-case\">
                <div class=\"block-header\">🎯 Cas concret</div>
                <div class=\"block-content\">{$content}</div>
            </div>\n\n";
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
            $html .= '</div>' . "\n\n";
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
