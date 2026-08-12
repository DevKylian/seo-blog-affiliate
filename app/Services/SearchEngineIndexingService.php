<?php

namespace App\Services;

use App\Models\Article;
use App\Models\SearchIndexingSubmission;
use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class SearchEngineIndexingService
{
    public function submitArticle(Article $article, string $reason = 'published'): array
    {
        $article->loadMissing('project');
        $url = $article->public_url;

        return [
            ...$this->submitUrlToIndexNow($url, $article, $reason),
            ...$this->submitUrlToGoogle($url, $article, $reason),
        ];
    }

    public function submitSitemap(?string $reason = 'manual'): array
    {
        return [
            ...$this->submitSitemapToGoogle($reason),
        ];
    }

    public function configuredSummary(): array
    {
        return [
            'auto_enabled' => $this->booleanSetting('indexing_auto_enabled', true),
            'indexnow_enabled' => $this->indexNowEnabled(),
            'indexnow_key' => $this->indexNowKey(),
            'indexnow_key_location' => $this->indexNowKey() ? route('indexnow.key', ['key' => $this->indexNowKey()]) : null,
            'google_enabled' => $this->googleEnabled(),
            'google_site_url' => $this->googleSiteUrl(),
            'google_has_credentials' => $this->googleCredentials() !== null,
            'google_mode' => 'sitemap_and_url_inspection',
        ];
    }

    public function indexNowKey(): ?string
    {
        $key = trim((string) Setting::value('indexnow_key', config('services.indexnow.key')));

        return $key !== '' ? $key : null;
    }

    public function indexNowKeyIsValid(string $key): bool
    {
        return $this->indexNowKey() !== null && hash_equals($this->indexNowKey(), $key);
    }

    private function submitUrlToIndexNow(string $url, ?Article $article, string $reason): array
    {
        if (! $this->booleanSetting('indexing_auto_enabled', true) || ! $this->indexNowEnabled()) {
            return [];
        }

        $key = $this->indexNowKey();
        if (! $key) {
            return [$this->record('indexnow', 'url', $url, 'skipped', null, null, 'Clé IndexNow absente.', $article)->toArray()];
        }

        $host = parse_url($url, PHP_URL_HOST) ?: parse_url(config('app.url'), PHP_URL_HOST);
        if (! $host) {
            return [$this->record('indexnow', 'url', $url, 'failed', null, null, 'Host introuvable pour IndexNow.', $article)->toArray()];
        }

        try {
            $response = Http::timeout(8)->acceptJson()->asJson()->post('https://api.indexnow.org/indexnow', [
                'host' => $host,
                'key' => $key,
                'keyLocation' => route('indexnow.key', ['key' => $key]),
                'urlList' => [$url],
            ]);

            return [$this->recordResponse('indexnow', 'url', $url, $response, $article, ['reason' => $reason])->toArray()];
        } catch (Throwable $exception) {
            report($exception);

            return [$this->record('indexnow', 'url', $url, 'failed', null, null, $exception->getMessage(), $article)->toArray()];
        }
    }

    private function submitUrlToGoogle(string $url, ?Article $article, string $reason): array
    {
        if (! $this->booleanSetting('indexing_auto_enabled', true) || ! $this->googleEnabled()) {
            return [];
        }

        $results = $this->submitSitemapToGoogle($reason.'_article', $article);
        $results = [
            ...$results,
            ...$this->inspectGoogleUrl($url, $article, $reason),
        ];

        return $results;
    }

    private function submitSitemapToGoogle(?string $reason = 'manual', ?Article $article = null): array
    {
        if (! $this->googleEnabled()) {
            return [];
        }

        $sitemap = $this->sitemapUrl();
        $siteUrl = $this->googleSiteUrl();
        if (! $siteUrl) {
            return [$this->record('google_search_console', 'sitemap', $sitemap, 'skipped', null, null, 'Propriété Search Console absente.', $article)->toArray()];
        }

        try {
            $token = $this->googleAccessToken();
            $endpoint = 'https://www.googleapis.com/webmasters/v3/sites/'
                .rawurlencode($siteUrl)
                .'/sitemaps/'
                .rawurlencode($sitemap);
            $response = Http::timeout(10)->withToken($token)->send('PUT', $endpoint);

            return [$this->recordResponse('google_search_console', 'sitemap', $sitemap, $response, $article, ['reason' => $reason])->toArray()];
        } catch (Throwable $exception) {
            report($exception);

            return [$this->record('google_search_console', 'sitemap', $sitemap, 'failed', null, null, $exception->getMessage(), $article)->toArray()];
        }
    }

    private function inspectGoogleUrl(string $url, ?Article $article, string $reason): array
    {
        if (! $this->booleanSetting('google_url_inspection_enabled', true)) {
            return [];
        }

        $siteUrl = $this->googleSiteUrl();
        if (! $siteUrl) {
            return [];
        }

        try {
            $token = $this->googleAccessToken();
            $response = Http::timeout(10)->withToken($token)->acceptJson()->asJson()
                ->post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
                    'inspectionUrl' => $url,
                    'siteUrl' => $siteUrl,
                    'languageCode' => 'fr-FR',
                ]);

            return [$this->recordResponse('google_search_console', 'inspection', $url, $response, $article, ['reason' => $reason])->toArray()];
        } catch (Throwable $exception) {
            report($exception);

            return [$this->record('google_search_console', 'inspection', $url, 'failed', null, null, $exception->getMessage(), $article)->toArray()];
        }
    }

    public function googleAccessToken(): string
    {
        $credentials = $this->googleCredentials();
        if (! $credentials) {
            throw new RuntimeException('JSON de compte de service Google absent.');
        }

        $clientEmail = (string) ($credentials['client_email'] ?? '');
        $privateKey = (string) ($credentials['private_key'] ?? '');
        if ($clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Le compte de service Google doit contenir client_email et private_key.');
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/webmasters',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $payload = $this->base64Url(json_encode($header, JSON_THROW_ON_ERROR))
            .'.'
            .$this->base64Url(json_encode($claims, JSON_THROW_ON_ERROR));
        $signature = '';
        if (! openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Signature OAuth Google impossible. Vérifiez la clé privée du compte de service.');
        }

        $assertion = $payload.'.'.$this->base64Url($signature);
        $response = Http::timeout(10)->asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('OAuth Google refuse : '.($response->json('error_description') ?: $response->body()));
        }

        return (string) $response->json('access_token');
    }

    public function googleCredentials(): ?array
    {
        $json = trim((string) Setting::value('google_service_account_json', config('services.google_search_console.service_account_json')));
        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    public function googleEnabled(): bool
    {
        return $this->booleanSetting('google_search_console_enabled', false)
            && $this->googleCredentials() !== null
            && $this->googleSiteUrl() !== null;
    }

    private function indexNowEnabled(): bool
    {
        return $this->booleanSetting('indexnow_enabled', false);
    }

    public function googleSiteUrl(): ?string
    {
        $siteUrl = trim((string) Setting::value('google_search_console_site_url', config('services.google_search_console.site_url')));
        if ($siteUrl === '') {
            return null;
        }

        return str_starts_with($siteUrl, 'sc-domain:') ? $siteUrl : rtrim($siteUrl, '/').'/';
    }

    private function sitemapUrl(): string
    {
        return route('sitemap');
    }

    private function recordResponse(string $provider, string $type, string $url, Response $response, ?Article $article = null, array $extra = []): SearchIndexingSubmission
    {
        $body = $response->json();
        if (! is_array($body)) {
            $body = ['body' => Str::limit($response->body(), 4000)];
        }

        return $this->record(
            $provider,
            $type,
            $url,
            $response->successful() ? 'submitted' : 'failed',
            $response->status(),
            array_merge($extra, $body),
            $response->successful() ? null : Str::limit($response->body(), 2000),
            $article,
        );
    }

    private function record(string $provider, string $type, string $url, string $status, ?int $httpStatus, ?array $response, ?string $error, ?Article $article = null): SearchIndexingSubmission
    {
        return SearchIndexingSubmission::query()->create([
            'article_id' => $article?->id,
            'provider' => $provider,
            'type' => $type,
            'url' => $url,
            'status' => $status,
            'http_status' => $httpStatus,
            'response' => $response,
            'error_message' => $error,
            'submitted_at' => now(),
        ]);
    }

    private function booleanSetting(string $key, bool $default): bool
    {
        $value = Setting::value($key);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
