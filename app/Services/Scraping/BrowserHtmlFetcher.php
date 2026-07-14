<?php

namespace App\Services\Scraping;

use App\Services\RuntimeBinaryLocator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class BrowserHtmlFetcher
{
    public function __construct(private readonly RuntimeBinaryLocator $binaries) {}

    public function fetch(string $url): ?string
    {
        $capture = $this->fetchPricingData($url);

        return $capture['html_snapshots'][0] ?? null;
    }

    /** @return array{engine: string, html_snapshots: array<int, string>, json_payloads: array<int, array<string, mixed>>, http_status?: int}|null */
    public function fetchPricingData(string $url): ?array
    {
        if (! (bool) config('services.scraping.browser_enabled', true)) {
            return null;
        }

        $binary = $this->binaries->resolveBrowser((string) config('services.scraping.browser_binary'));
        if ($binary === null) {
            return null;
        }

        $node = $this->binaries->resolveNode((string) config('services.scraping.node_binary'));
        $script = base_path('scripts/capture-pricing.mjs');
        if ($node !== null && is_file($script)) {
            try {
                $process = new Process([$node, $script, $url, $binary], base_path());
                $process->setTimeout(55);
                $process->run();
                $payload = json_decode($process->getOutput(), true);
                if ($process->isSuccessful() && is_array($payload) && is_array($payload['html_snapshots'] ?? null)) {
                    return [
                        'engine' => 'playwright',
                        'html_snapshots' => array_values(array_filter($payload['html_snapshots'], fn ($html) => is_string($html) && str_contains(mb_strtolower($html), '<html'))),
                        'json_payloads' => array_values(array_filter($payload['json_payloads'] ?? [], 'is_array')),
                        'http_status' => (int) ($payload['http_status'] ?? 200),
                    ];
                }
            } catch (Throwable $exception) {
                Log::warning('Capture Playwright des tarifs indisponible, repli sur Chrome.', [
                    'url' => $url,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        try {
            $chromeArguments = [
                $binary,
                '--headless=new',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--disable-extensions',
                '--hide-scrollbars',
                '--virtual-time-budget=6000',
                '--dump-dom',
            ];
            if (PHP_OS_FAMILY === 'Linux' && function_exists('posix_geteuid') && posix_geteuid() === 0) {
                $chromeArguments[] = '--no-sandbox';
                $chromeArguments[] = '--disable-setuid-sandbox';
            }
            $chromeArguments[] = $url;
            $process = new Process($chromeArguments);
            $process->setTimeout(35);
            $process->run();
            $html = trim($process->getOutput());

            return $process->isSuccessful() && str_contains(mb_strtolower($html), '<html')
                ? ['engine' => 'chrome', 'html_snapshots' => [$html], 'json_payloads' => [], 'http_status' => 200]
                : null;
        } catch (Throwable $exception) {
            Log::warning('Rendu navigateur de la page tarifaire indisponible.', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
