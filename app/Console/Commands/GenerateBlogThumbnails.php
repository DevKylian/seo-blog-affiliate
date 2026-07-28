<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\BlogThumbnailService;
use Illuminate\Console\Command;

class GenerateBlogThumbnails extends Command
{
    protected $signature = 'blog:generate-thumbnails
                            {--slug= : Générer uniquement pour cet article}
                            {--force : Régénérer même si le fichier existe déjà}
                            {--all : Inclure brouillons et articles non publiés}';

    protected $description = 'Génère les miniatures PNG (1200×630) dans public/blog-thumbnails/';

    public function handle(BlogThumbnailService $thumbnails): int
    {
        if (! $this->commandExists('rsvg-convert') && ! extension_loaded('imagick')) {
            $this->error('Installez librsvg pour une conversion SVG→PNG fidèle : sudo apt install librsvg2-bin');
            $this->comment('(Alternative : php-imagick si la policy SVG ImageMagick est ouverte)');

            return self::FAILURE;
        }

        $dir = $thumbnails->directory();
        if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
            $this->error("Impossible de créer le dossier : {$dir}");

            return self::FAILURE;
        }

        $this->info("Dossier cible : {$dir}");

        $query = $this->option('all')
            ? Article::query()->orderBy('id')
            : Article::query()->whereNotNull('published_at')->orderBy('id');

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        }

        $articles = $query->get();

        if ($articles->isEmpty()) {
            $this->warn('Aucun article trouvé.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $ok    = 0;
        $fail  = 0;

        foreach ($articles as $article) {
            try {
                $path = $thumbnails->ensureForArticle($article, $force);
                $size = filesize($path);
                $this->line("  ✓ {$article->slug} ({$size} octets) → blog-thumbnails/{$article->slug}.png");
                $ok++;
            } catch (\Throwable $e) {
                $this->error("  ✗ {$article->slug} : {$e->getMessage()}");
                $fail++;
            }
        }

        $this->newLine();
        $this->info("Terminé : {$ok} miniature(s)" . ($fail ? ", {$fail} échec(s)" : '') . '.');

        if ($ok > 0) {
            $this->comment('Les images sont accessibles via : /blog-thumbnails/{slug}.png');
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function commandExists(string $command): bool
    {
        $which = trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null'));

        return $which !== '';
    }
}
