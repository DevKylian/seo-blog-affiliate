<?php

namespace App\Services;

use App\Models\Article;
use App\Models\SearchPerformanceSnapshot;
use App\Models\SeoProject;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class SearchPerformanceImportService
{
    public function __construct(private readonly SearchEngineIndexingService $searchConsole) {}

    /** @return array{google:int,bing:int,errors:array<int,string>} */
    public function import(CarbonInterface|string|null $from = null, CarbonInterface|string|null $to = null): array
    {
        $toDate = $to ? Carbon::parse($to) : now()->subDays(2);
        $fromDate = $from ? Carbon::parse($from) : $toDate->copy()->subDays(27);
        $stats = ['google' => 0, 'bing' => 0, 'errors' => []];

        try {
            $stats['google'] = $this->importGoogle($fromDate, $toDate);
        } catch (Throwable $exception) {
            report($exception);
            $stats['errors'][] = 'Google Search Console : '.$exception->getMessage();
        }

        try {
            $stats['bing'] = $this->importBing($fromDate, $toDate);
        } catch (Throwable $exception) {
            report($exception);
            $stats['errors'][] = 'Bing Webmaster : '.$exception->getMessage();
        }

        if ($stats['google'] > 0 || $stats['bing'] > 0) {
            Setting::put('search_performance_last_import_at', now()->toDateTimeString());
        }

        return $stats;
    }

    public function importGoogle(CarbonInterface $from, CarbonInterface $to): int
    {
        if (! $this->searchConsole->googleEnabled()) {
            return 0;
        }

        $siteUrl = $this->searchConsole->googleSiteUrl();
        if (! $siteUrl) {
            return 0;
        }

        $response = Http::timeout(20)
            ->withToken($this->searchConsole->googleAccessToken())
            ->acceptJson()
            ->asJson()
            ->post('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query', [
                'startDate' => $from->toDateString(),
                'endDate' => $to->toDateString(),
                'dimensions' => ['query', 'page', 'device', 'country'],
                'rowLimit' => 25000,
                'dataState' => 'final',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Search Analytics refuse : '.Str::limit($response->body(), 600));
        }

        $count = 0;
        foreach ((array) $response->json('rows', []) as $row) {
            $keys = (array) ($row['keys'] ?? []);
            $query = (string) ($keys[0] ?? '');
            $page = (string) ($keys[1] ?? '');
            if ($query === '' && $page === '') {
                continue;
            }

            $this->storeSnapshot(
                provider: 'google_search_console',
                siteUrl: $siteUrl,
                pageUrl: $page,
                query: $query,
                country: (string) ($keys[3] ?? ''),
                device: (string) ($keys[2] ?? ''),
                from: $from,
                to: $to,
                clicks: (int) ($row['clicks'] ?? 0),
                impressions: (int) ($row['impressions'] ?? 0),
                ctr: (float) ($row['ctr'] ?? 0),
                position: (float) ($row['position'] ?? 0),
                metadata: ['raw' => $row],
            );
            $count++;
        }

        Setting::put('search_performance_last_import_at', now()->toDateTimeString());

        return $count;
    }

    public function importBing(CarbonInterface $from, CarbonInterface $to): int
    {
        if (! $this->bingEnabled()) {
            return 0;
        }

        $siteUrl = $this->bingSiteUrl();
        $apiKey = $this->bingApiKey();
        if ($siteUrl === '' || $apiKey === '') {
            return 0;
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->get('https://ssl.bing.com/webmaster/api.svc/json/GetQueryStats', [
                'siteUrl' => $siteUrl,
                'apikey' => $apiKey,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Bing Webmaster refuse : '.Str::limit($response->body(), 600));
        }

        $count = 0;
        foreach ((array) $response->json('d', []) as $row) {
            $date = $this->bingDate((string) ($row['Date'] ?? '')) ?: $to;
            if ($date->lt($from) || $date->gt($to)) {
                continue;
            }

            $this->storeSnapshot(
                provider: 'bing_webmaster',
                siteUrl: $siteUrl,
                pageUrl: null,
                query: (string) ($row['Query'] ?? ''),
                country: null,
                device: null,
                from: $date,
                to: $date,
                clicks: (int) ($row['Clicks'] ?? 0),
                impressions: (int) ($row['Impressions'] ?? 0),
                ctr: $this->safeCtr((int) ($row['Clicks'] ?? 0), (int) ($row['Impressions'] ?? 0)),
                position: (float) ($row['AvgImpressionPosition'] ?? $row['AvgClickPosition'] ?? 0),
                metadata: ['raw' => $row],
            );
            $count++;
        }

        return $count;
    }

    public function configuredSummary(): array
    {
        return [
            'google_performance_enabled' => $this->searchConsole->googleEnabled(),
            'bing_performance_enabled' => $this->bingEnabled(),
            'bing_site_url' => $this->bingSiteUrl(),
            'bing_has_api_key' => $this->bingApiKey() !== '',
            'last_import_at' => Setting::value('search_performance_last_import_at'),
        ];
    }

    private function storeSnapshot(
        string $provider,
        ?string $siteUrl,
        ?string $pageUrl,
        ?string $query,
        ?string $country,
        ?string $device,
        CarbonInterface $from,
        CarbonInterface $to,
        int $clicks,
        int $impressions,
        float $ctr,
        ?float $position,
        array $metadata = [],
    ): SearchPerformanceSnapshot {
        $article = $pageUrl ? $this->articleForUrl($pageUrl) : null;
        $project = $article?->project ?: $this->projectForSite($siteUrl);
        $query = trim((string) $query);
        $pageUrl = $pageUrl ? trim($pageUrl) : null;

        return SearchPerformanceSnapshot::query()->updateOrCreate(
            [
                'provider' => $provider,
                'site_url' => $siteUrl,
                'url_hash' => sha1($pageUrl ?: 'site-wide'),
                'query_hash' => sha1(Str::ascii(mb_strtolower($query ?: 'unknown'))),
                'country' => $country ?: null,
                'device' => $device ?: null,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            [
                'seo_project_id' => $project?->id,
                'article_id' => $article?->id,
                'page_url' => $pageUrl,
                'query' => $query,
                'clicks' => max(0, $clicks),
                'impressions' => max(0, $impressions),
                'ctr' => round(max(0, $ctr), 5),
                'position' => $position !== null ? round(max(0, $position), 2) : null,
                'metadata' => $metadata,
                'imported_at' => now(),
            ],
        );
    }

    private function articleForUrl(string $url): ?Article
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $slug = collect(explode('/', $path))->filter()->last();
        if (! $slug) {
            return null;
        }

        return Article::query()->with('project')->where('slug', $slug)->first();
    }

    private function projectForSite(?string $siteUrl): ?SeoProject
    {
        if (! $siteUrl) {
            return SeoProject::query()->where('status', 'active')->first();
        }
        $host = Str::ascii(mb_strtolower((string) parse_url(str_replace('sc-domain:', 'https://', $siteUrl), PHP_URL_HOST)));

        return SeoProject::query()
            ->where('status', 'active')
            ->get()
            ->first(fn (SeoProject $project): bool => str_contains(Str::ascii(mb_strtolower($project->website_url)), $host));
    }

    private function bingEnabled(): bool
    {
        return filter_var(Setting::value('bing_webmaster_enabled', false), FILTER_VALIDATE_BOOLEAN)
            && $this->bingApiKey() !== ''
            && $this->bingSiteUrl() !== '';
    }

    private function bingApiKey(): string
    {
        return trim((string) Setting::value('bing_webmaster_api_key', config('services.bing_webmaster.api_key', '')));
    }

    private function bingSiteUrl(): string
    {
        $value = trim((string) Setting::value('bing_webmaster_site_url', config('services.bing_webmaster.site_url', '')));

        return $value !== '' ? rtrim($value, '/') : '';
    }

    private function bingDate(string $value): ?Carbon
    {
        if (preg_match('/\/Date\((\d+)/', $value, $match) === 1) {
            return Carbon::createFromTimestampMs((int) $match[1])->startOfDay();
        }

        return $value !== '' ? Carbon::parse($value)->startOfDay() : null;
    }

    private function safeCtr(int $clicks, int $impressions): float
    {
        return $impressions > 0 ? $clicks / $impressions : 0.0;
    }
}
