<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MarkdownImportService
{
    public function import(string $content, ?string $filename = null): Article
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

        $slug = !empty($yaml['slug']) ? Str::slug($yaml['slug']) : ($filename ? Str::slug($filename) : Str::slug($title));
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
            'title' => $title,
            'type' => Str::slug($yaml['type'] ?? 'article'),
            'slug' => $slug,
            'body' => trim($body),
            'content_blocks' => $blocks,
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'published_at' => $status === 'published' ? $scheduledAt : null,
            'search_intent' => $yaml['intention'] ?? null,
            'primary_keyword' => $yaml['mot-cle'] ?? null,
            'meta_title' => $yaml['meta-title'] ?? null,
            'thumbnail_title' => $yaml['titre-miniature'] ?? null,
            'meta_description' => $yaml['meta-description'] ?? null,
        ]);

        // Attachement de la catégorie (silo)
        if (!empty($yaml['silo'])) {
            $categoryName = trim($yaml['silo']);
            $category = Category::query()->firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName]
            );
            $article->categories()->sync([$category->id]);
        }

        // Attachement des tags
        if (!empty($yaml['tags'])) {
            $tagIds = collect(explode(',', $yaml['tags']))
                ->map(fn ($tag) => trim($tag))
                ->filter()
                ->unique()
                ->map(function ($tag) {
                    return Tag::query()->firstOrCreate(
                        ['slug' => Str::slug($tag)],
                        ['name' => $tag]
                    )->id;
                })->all();
            $article->tags()->sync($tagIds);
        }

        return $article;
    }
}
