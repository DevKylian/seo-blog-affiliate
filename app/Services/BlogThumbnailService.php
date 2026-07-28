<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class BlogThumbnailService
{
    public const WIDTH  = 1200;
    public const HEIGHT = 630;

    public function directory(): string
    {
        return public_path('blog-thumbnails');
    }

    public function absolutePath(string $slug): string
    {
        return $this->directory() . '/' . $slug . '.png';
    }

    public function publicUrl(string $slug): string
    {
        return route('og-image', ['article' => $slug]);
    }

    public function ogImageUrl(string $slug): string
    {
        $path = $this->absolutePath($slug);
        if (is_file($path) && filesize($path) > 100) {
            return asset('blog-thumbnails/' . $slug . '.png');
        }

        return route('og-image', ['article' => $slug]);
    }

    public function forget(string $slug): void
    {
        $path = $this->absolutePath($slug);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function ensure(string $slug, string $title, string $category = 'BUSINESSKIT', ?string $excerpt = null, ?\DateTimeInterface $updatedAt = null): string
    {
        $path = $this->absolutePath($slug);

        if ($this->isFresh($path, $updatedAt)) {
            return $path;
        }

        $svg = $this->buildSvg($title, $category, $excerpt);
        $png = $this->svgToPng($svg);

        if ($png === null || strlen($png) < 100) {
            throw new \RuntimeException(
                'Conversion SVG → PNG impossible. Installez librsvg : sudo apt install librsvg2-bin '
                . '(recommandé) ou vérifiez php-imagick et la policy SVG de ImageMagick.'
            );
        }

        $this->ensureDirectoryWritable();

        if (file_put_contents($path, $png) === false) {
            throw new \RuntimeException(
                "Impossible d'écrire la miniature : {$path}"
            );
        }

        @chmod($path, 0644);

        return $path;
    }

    public function httpResponse(string $slug, string $title, string $category = 'BUSINESSKIT', ?string $excerpt = null, ?\DateTimeInterface $updatedAt = null): Response
    {
        $path = $this->absolutePath($slug);
        if (!$this->isFresh($path, $updatedAt)) {
            $path = $this->ensure($slug, $title, $category, $excerpt, $updatedAt);
        }
        
        return response(file_get_contents($path), 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function ensureDirectoryWritable(): void
    {
        $dir = $this->directory();

        if (! is_dir($dir)) {
            File::makeDirectory($dir, 02775, true);
        }

        if (! is_writable($dir)) {
            @chmod($dir, 02775);
        }
    }

    public function ensureForArticle(Article $article, bool $force = false): string
    {
        if ($force) {
            $this->forget($article->slug);
        }

        $subtitle = $article->topic_key ? Str::title(str_replace('_', ' ', $article->topic_key)) . ' — 100% gratuit.' : 'Comparatifs, tests et guides — 100% gratuit.';

        return $this->ensure(
            $article->slug,
            $article->title,
            'BUSINESSKIT',
            $subtitle,
            $article->updated_at
        );
    }

    public function buildSvg(string $title, string $category = 'BUSINESSKIT', ?string $excerpt = null): string
    {
        $titleLen = mb_strlen($title);
        $fontSize = $titleLen <= 30 ? 72 : ($titleLen <= 50 ? 62 : 52);
        $maxChars = $titleLen <= 30 ? 20 : ($titleLen <= 50 ? 24 : 28);
        $lineH    = (int) ($fontSize * 1.2);

        $lines  = $this->wrapTitle($title, $maxChars);
        $nLines = count($lines);
        $yStart = 280 - (($nLines - 1) * ($lineH / 2));

        $svgLines = '';
        foreach ($lines as $i => $line) {
            $y       = $yStart + $i * $lineH;
            $escaped = htmlspecialchars($line, ENT_XML1);
            $svgLines .= "<text x=\"80\" y=\"{$y}\" fill=\"#ffffff\" font-size=\"{$fontSize}\" font-weight=\"700\" font-family=\"'Helvetica Neue', Arial, sans-serif\" letter-spacing=\"-0.5\">{$escaped}</text>\n  ";
        }

        $excerptSvg = '';
        if ($excerpt) {
            $escapedEx = htmlspecialchars($excerpt, ENT_XML1);
            $excerptY  = $yStart + (($nLines - 1) * $lineH) + $lineH + 20;
            $excerptSvg = "<text x=\"80\" y=\"{$excerptY}\" fill=\"#b4bccc\" font-size=\"28\" font-family=\"'Helvetica Neue', Arial, sans-serif\">{$escapedEx}</text>";
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <rect width="1200" height="630" fill="#171f2c"/>
  
  <!-- Clipboard watermark (Large outline on the right) -->
  <g transform="translate(680, 50) scale(18)" stroke="rgba(255,255,255,0.08)" stroke-width="1.2" fill="none" stroke-linecap="round" stroke-linejoin="round">
    <rect width="14" height="18" x="5" y="4" rx="2" ry="2"/>
    <path d="M8 2h8v4H8z"/>
    <path d="M9 10h6"/>
    <path d="M9 14h6"/>
    <path d="M9 18h4"/>
  </g>

  <text x="80" y="120" fill="#3b82f6" font-size="22" font-weight="700"
        font-family="'Helvetica Neue', Arial, sans-serif" letter-spacing="2">BUSINESSKIT</text>

  {$svgLines}
  {$excerptSvg}
</svg>
SVG;
    }

    public function svgToPng(string $svg): ?string
    {
        $png = $this->svgToPngViaRsvg($svg);
        if ($png !== null) {
            return $png;
        }

        $png = $this->svgToPngViaImagick($svg);
        if ($png !== null) {
            return $png;
        }

        Log::error('BlogThumbnailService: échec conversion SVG→PNG (rsvg et imagick)');

        return null;
    }

    private function svgToPngViaRsvg(string $svg): ?string
    {
        if (! $this->commandExists('rsvg-convert')) {
            return null;
        }

        $svgFile = tempnam(sys_get_temp_dir(), 'blog_svg_');
        $pngFile = tempnam(sys_get_temp_dir(), 'blog_png_');

        if ($svgFile === false || $pngFile === false) {
            return null;
        }

        try {
            File::put($svgFile, $svg);
            $cmd = sprintf(
                'rsvg-convert -w %d -h %d -f png -o %s %s 2>&1',
                self::WIDTH,
                self::HEIGHT,
                escapeshellarg($pngFile),
                escapeshellarg($svgFile)
            );
            exec($cmd, $output, $code);

            if ($code !== 0 || ! is_readable($pngFile) || filesize($pngFile) < 100) {
                return null;
            }
            return File::get($pngFile);
        } catch (\Throwable $e) {
            return null;
        } finally {
            @unlink($svgFile);
            @unlink($pngFile);
        }
    }

    private function svgToPngViaImagick(string $svg): ?string
    {
        if (! extension_loaded('imagick')) {
            return null;
        }

        try {
            $im = new \Imagick();
            $im->setBackgroundColor(new \ImagickPixel('#171f2c'));
            $im->setResolution(144, 144);
            $im->readImageBlob($svg);
            $im->setImageFormat('png24');
            $im->resizeImage(self::WIDTH, self::HEIGHT, \Imagick::FILTER_LANCZOS, 1, true);

            $blob = $im->getImageBlob();
            $im->clear();
            $im->destroy();

            return (strlen($blob) > 100) ? $blob : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isFresh(string $path, ?\DateTimeInterface $updatedAt): bool
    {
        if (! is_file($path) || filesize($path) < 100) {
            return false;
        }
        if ($updatedAt === null) {
            return true;
        }
        return filemtime($path) >= $updatedAt->getTimestamp();
    }

    private function commandExists(string $command): bool
    {
        $which = trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null'));
        return $which !== '';
    }

    private function wrapTitle(string $title, int $maxChars): array
    {
        $words   = explode(' ', $title);
        $lines   = [];
        $current = '';

        foreach ($words as $word) {
            $test = $current ? "{$current} {$word}" : $word;
            if (mb_strlen($test) <= $maxChars) {
                $current = $test;
            } else {
                if ($current) {
                    $lines[] = $current;
                }
                $current = $word;
            }
        }
        if ($current) {
            $lines[] = $current;
        }

        return array_slice($lines, 0, 3);
    }
}
