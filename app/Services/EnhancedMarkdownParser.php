<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;

class EnhancedMarkdownParser
{
    public function parse(string $markdown): string
    {
        // 0. Remove raw JSON-LD scripts and Obsidian artifacts from markdown
        $markdown = preg_replace('/<!--\s*🔍\s*Données structurées.*?-->/is', '', $markdown);
        $markdown = preg_replace('/<script type="application\/ld\+json">.*?<\/script>/is', '', $markdown);
        $markdown = preg_replace('/⬆️\s*Stratégie parente.*$/im', '', $markdown);
        // Clean up any trailing horizontal rules left at the very end of the file
        $markdown = preg_replace('/(?:\s*---)+\s*$/is', '', $markdown);

        // 1. Auto-format standard markdown FAQs into custom blocks
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
        // Match standard FAQs up to the next H2 or end of string
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

        // "Ma recommandation" CTA block
        $markdown = preg_replace_callback('/(?:^|\n)#*\s*⭐\s*Ma recommandation\s*\r?\n(.*?)\r?\n\[([^\]]+)\]\(([^)]+)\)/is', function ($matches) {
            $content = Str::markdown(trim($matches[1]));
            $btnText = trim($matches[2]);
            $btnUrl = trim($matches[3]);
            
            return "<div class=\"premium-reco-block no-print\">
                <style>
                    .premium-reco-block { background: linear-gradient(145deg, #ffffff, #f8fafc); border: 1px solid #e2e8f0; border-radius: 24px; padding: 48px 32px; margin: 48px 0; text-align: center; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.05); position: relative; overflow: hidden; }
                    .premium-reco-block::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 6px; background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899); }
                    .premium-reco-icon { display: inline-flex; align-items: center; justify-content: center; width: 72px; height: 72px; background: #eff6ff; border-radius: 50%; color: #3b82f6; margin-bottom: 24px; box-shadow: 0 0 0 10px rgba(239,246,255,0.6); }
                    .premium-reco-title { color: #0f172a; font-size: 28px; font-weight: 800; margin-bottom: 16px; font-family: 'Manrope', sans-serif; letter-spacing: -0.5px; }
                    .premium-reco-text { color: #475569; font-size: 16px; line-height: 1.8; margin-bottom: 32px; max-width: 650px; margin-left: auto; margin-right: auto; }
                    .premium-reco-text p { margin-bottom: 16px; }
                    .premium-reco-text p:last-child { margin-bottom: 0; }
                    .premium-reco-btn { display: inline-flex; align-items: center; justify-content: center; background: #0f172a; color: white !important; font-weight: 700; font-size: 16px; padding: 16px 32px; border-radius: 12px; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 10px 20px rgba(15,23,42,0.2); }
                    .premium-reco-btn:hover { transform: translateY(-2px); box-shadow: 0 15px 25px rgba(15,23,42,0.3); }
                </style>
                <div class=\"premium-reco-icon\">
                    <svg width=\"32\" height=\"32\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\" stroke-width=\"2.5\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M5 13l4 4L19 7\"></path></svg>
                </div>
                <h3 class=\"premium-reco-title\">Ma recommandation</h3>
                <div class=\"premium-reco-text\">{$content}</div>
                <a href=\"{$btnUrl}\" class=\"premium-reco-btn\" target=\"_blank\" rel=\"sponsored noopener\">{$btnText}</a>
            </div>\n\n";
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
