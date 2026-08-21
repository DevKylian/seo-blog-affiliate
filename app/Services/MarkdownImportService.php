<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MarkdownImportService
{
    public function import(string $content, int $projectId): Article
    {
        $yaml = [];
        $body = $content;

        // Extract YAML Frontmatter
        if (preg_match('/^---\s*(.*?)\s*---\s*(.*)$/s', $content, $matches)) {
            $yamlString = trim($matches[1]);
            $body = trim($matches[2]);
            
            foreach (explode("\n", $yamlString) as $line) {
                if (str_contains($line, ':')) {
                    $parts = explode(':', $line, 2);
                    $yaml[trim($parts[0])] = trim(str_replace('"', '', $parts[1]));
                }
            }
        }

        // Extract Title from H1
        $title = 'Article importé sans titre';
        if (preg_match('/^#\s+(.+)$/m', $body, $matches)) {
            $title = trim($matches[1]);
            $body = preg_replace('/^#\s+.+\s*$/m', '', $body, 1);
        }

        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;
        while (Article::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $blocks = [
            ['type' => 'markdown', 'content' => trim($body)],
        ];

        // Gestion du statut et de la planification automatique
        $status = 'draft';
        $scheduledAt = null;

        if (!empty($yaml['date-publication']) && $yaml['date-publication'] !== '[YYYY-MM-DD HH:MM:SS]') {
            try {
                $datePub = Carbon::parse($yaml['date-publication']);
                if ($datePub->isFuture()) {
                    $status = 'scheduled';
                    $scheduledAt = $datePub;
                } elseif ($datePub->isPast()) {
                    $status = 'published';
                    $scheduledAt = $datePub;
                }
            } catch (\Exception $e) {
                // If date is invalid, fallback to draft
            }
        }

        $article = Article::create([
            'seo_project_id' => $projectId,
            'title' => $title,
            'slug' => $slug,
            'body' => trim($body),
            'content_blocks' => $blocks,
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'published_at' => $status === 'published' ? $scheduledAt : null,
            'search_intent' => $yaml['intention'] ?? null,
            'primary_keyword' => $yaml['mot-cle'] ?? null,
        ]);

        return $article;
    }
}
