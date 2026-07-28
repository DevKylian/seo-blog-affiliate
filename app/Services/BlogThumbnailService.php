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
        return storage_path('app/public/blog-thumbnails');
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
            return asset('storage/blog-thumbnails/' . $slug . '.png');
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

    public function httpResponse(string $slug, string $title, string $category = 'BUSINESSKIT', ?string $excerpt = null, ?\DateTimeInterface $updatedAt = null, ?string $thumbnailTitle = null): Response
    {
        $path = $this->ensure($slug, $thumbnailTitle ?? $title, $category, $excerpt, $updatedAt);
        
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

    public function ensureForArticle($article, bool $force = false): string
    {
        $category = \Illuminate\Support\Str::upper($article->topic_key ?? 'BUSINESSKIT');
        if ($force) {
            $this->forget($article->slug);
        }

        return $this->ensure(
            $article->slug,
            $article->thumbnail_title ?? $article->title,
            $category,
            null,
            $article->updated_at
        );
    }

    public function buildSvg(string $title, string $category = 'BUSINESSKIT', ?string $excerpt = null): string
    {
        $category = mb_strtoupper($category ?: 'BUSINESSKIT');

        $titleLen = mb_strlen($title);
        $fontSize = $titleLen <= 30 ? 72 : ($titleLen <= 50 ? 62 : 52);
        $maxChars = $titleLen <= 30 ? 18 : ($titleLen <= 50 ? 22 : 26);
        $lineH    = (int) ($fontSize * 1.18);

        $lines  = $this->wrapTitle($title, $maxChars);
        $nLines = count($lines);
        $yStart = self::HEIGHT - 200 - ($nLines - 1) * $lineH;

        $svgLines = '';
        foreach ($lines as $i => $line) {
            $y       = $yStart + $i * $lineH;
            $escaped = htmlspecialchars($line, ENT_XML1);
            $svgLines .= "<text x=\"72\" y=\"{$y}\" fill=\"#ffffff\" font-size=\"{$fontSize}\" font-weight=\"700\" font-family=\"'Arial Black', 'Helvetica Neue', Arial, sans-serif\" letter-spacing=\"-0.5\">{$escaped}</text>\n  ";
        }

        $excerptSvg = '';
        if ($nLines === 1 && $excerpt) {
            $short     = mb_strlen($excerpt) > 80 ? mb_substr($excerpt, 0, 77) . '…' : $excerpt;
            $escapedEx = htmlspecialchars($short, ENT_XML1);
            $excerptY  = $yStart + $lineH + 10;
            $excerptSvg = "<text x=\"72\" y=\"{$excerptY}\" fill=\"rgba(255,255,255,0.72)\" font-size=\"28\" font-family=\"'Helvetica Neue', Arial, sans-serif\">{$escapedEx}</text>";
        }

        $escapedCat = htmlspecialchars($category, ENT_XML1);
        $pillW      = mb_strlen($category) * 12 + 32; // Simplified pill width

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0f172a"/>
      <stop offset="60%" stop-color="#1e3a8a"/>
      <stop offset="100%" stop-color="#3b82f6"/>
    </linearGradient>
    <linearGradient id="bottom-glow" x1="0%" y1="100%" x2="0%" y2="0%">
      <stop offset="0%" stop-color="#ffffff" stop-opacity="0.28"/>
      <stop offset="33%" stop-color="#ffffff" stop-opacity="0"/>
    </linearGradient>
  </defs>

  <!-- Background -->
  <rect width="1200" height="630" fill="url(#bg)"/>
  <!-- Bottom white glow -->
  <rect width="1200" height="630" fill="url(#bottom-glow)"/>

  <!-- Document / Note watermark (Clean & Pro) -->
  <g transform="translate(750, 40) rotate(15) scale(20)" opacity="0.12"
     fill="none" stroke="white" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
    <path d="M10 9H8"/>
    <path d="M16 13H8"/>
    <path d="M16 17H8"/>
  </g>

  <!-- Category pill -->
  <rect x="68" y="112" width="{$pillW}" height="34" rx="17" fill="rgba(255,255,255,0.18)"/>
  <text x="84" y="135" fill="#ffffff" font-size="17" font-weight="700"
        font-family="'Helvetica Neue', Arial, sans-serif" letter-spacing="1.5">{$escapedCat}</text>

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
