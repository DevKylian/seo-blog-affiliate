<?php

namespace App\Livewire;

use App\Models\SearchIndexingSubmission;
use App\Models\Setting;
use App\Services\GeminiContentGenerator;
use App\Services\SearchEngineIndexingService;
use App\Services\SearchPerformanceImportService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin')]
class Settings extends Component
{
    public string $apiKey = '';

    public string $semrushApiKey = '';

    public string $model = 'gemini-2.5-flash-lite';

    public bool $indexingAutoEnabled = true;

    public bool $indexNowEnabled = false;

    public string $indexNowKey = '';

    public bool $googleSearchConsoleEnabled = false;

    public bool $googleUrlInspectionEnabled = true;

    public string $googleSearchConsoleSiteUrl = '';

    public string $googleServiceAccountJson = '';

    public bool $bingWebmasterEnabled = false;

    public string $bingWebmasterSiteUrl = '';

    public string $bingWebmasterApiKey = '';

    public string $message = '';

    public string $error = '';

    public bool $hasSavedKey = false;

    public bool $hasSavedSemrushKey = false;

    public bool $hasSavedGoogleServiceAccount = false;

    public bool $hasSavedBingWebmasterApiKey = false;

    public function mount(): void
    {
        $this->model = (string) Setting::value('gemini_model', 'gemini-2.5-flash-lite');
        if (! in_array($this->model, ['gemini-2.5-flash-lite', 'gemini-2.5-flash'], true)) {
            $this->model = 'gemini-2.5-flash-lite';
        }
        $this->hasSavedKey = (bool) Setting::value('gemini_api_key', config('services.gemini.key'));
        $this->hasSavedSemrushKey = (bool) Setting::value('semrush_api_key', config('services.semrush.key'));
        $this->indexingAutoEnabled = $this->booleanSetting('indexing_auto_enabled', true);
        $this->indexNowEnabled = $this->booleanSetting('indexnow_enabled', false);
        $this->indexNowKey = (string) Setting::value('indexnow_key', config('services.indexnow.key', ''));
        $this->googleSearchConsoleEnabled = $this->booleanSetting('google_search_console_enabled', false);
        $this->googleUrlInspectionEnabled = $this->booleanSetting('google_url_inspection_enabled', true);
        $this->googleSearchConsoleSiteUrl = (string) Setting::value('google_search_console_site_url', config('services.google_search_console.site_url', ''));
        $this->hasSavedGoogleServiceAccount = (bool) Setting::value('google_service_account_json', config('services.google_search_console.service_account_json'));
        $this->bingWebmasterEnabled = $this->booleanSetting('bing_webmaster_enabled', false);
        $this->bingWebmasterSiteUrl = (string) Setting::value('bing_webmaster_site_url', config('services.bing_webmaster.site_url', ''));
        $this->hasSavedBingWebmasterApiKey = (bool) Setting::value('bing_webmaster_api_key', config('services.bing_webmaster.api_key'));
    }

    public function save(): void
    {
        $this->validate([
            'apiKey' => ['nullable', 'string', 'min:20', 'max:500'],
            'semrushApiKey' => ['nullable', 'string', 'min:10', 'max:500'],
            'model' => ['required', 'in:gemini-2.5-flash-lite,gemini-2.5-flash'],
            'indexNowKey' => ['nullable', 'string', 'min:8', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'googleSearchConsoleSiteUrl' => ['nullable', 'string', 'max:255'],
            'googleServiceAccountJson' => ['nullable', 'string', 'max:12000'],
            'bingWebmasterSiteUrl' => ['nullable', 'string', 'max:255'],
            'bingWebmasterApiKey' => ['nullable', 'string', 'min:8', 'max:500'],
        ]);

        if ($this->apiKey !== '') {
            Setting::put('gemini_api_key', trim($this->apiKey), true);
            $this->apiKey = '';
            $this->hasSavedKey = true;
        }

        if ($this->semrushApiKey !== '') {
            Setting::put('semrush_api_key', trim($this->semrushApiKey), true);
            $this->semrushApiKey = '';
            $this->hasSavedSemrushKey = true;
        }

        Setting::put('gemini_model', $this->model);
        Setting::put('indexing_auto_enabled', $this->indexingAutoEnabled ? '1' : '0');
        Setting::put('indexnow_enabled', $this->indexNowEnabled ? '1' : '0');
        if ($this->indexNowKey !== '') {
            Setting::put('indexnow_key', trim($this->indexNowKey), true);
        }
        Setting::put('google_search_console_enabled', $this->googleSearchConsoleEnabled ? '1' : '0');
        Setting::put('google_url_inspection_enabled', $this->googleUrlInspectionEnabled ? '1' : '0');
        Setting::put('google_search_console_site_url', trim($this->googleSearchConsoleSiteUrl));
        Setting::put('bing_webmaster_enabled', $this->bingWebmasterEnabled ? '1' : '0');
        Setting::put('bing_webmaster_site_url', trim($this->bingWebmasterSiteUrl));
        if (trim($this->bingWebmasterApiKey) !== '') {
            Setting::put('bing_webmaster_api_key', trim($this->bingWebmasterApiKey), true);
            $this->bingWebmasterApiKey = '';
            $this->hasSavedBingWebmasterApiKey = true;
        }
        if (trim($this->googleServiceAccountJson) !== '') {
            $decoded = json_decode($this->googleServiceAccountJson, true);
            if (! is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
                $this->error = 'Le JSON Google doit etre un compte de service valide avec client_email et private_key.';

                return;
            }
            Setting::put('google_service_account_json', trim($this->googleServiceAccountJson), true);
            $this->googleServiceAccountJson = '';
            $this->hasSavedGoogleServiceAccount = true;
        }
        $this->message = 'Reglages enregistres. Les cles sont chiffrees avec APP_KEY.';
        $this->error = '';
    }

    public function generateIndexNowKey(): void
    {
        $this->indexNowKey = Str::lower(Str::random(32));
        $this->indexNowEnabled = true;
        Setting::put('indexnow_key', $this->indexNowKey, true);
        Setting::put('indexnow_enabled', '1');
        $this->message = 'Cle IndexNow generee. Enregistrez les reglages pour conserver les autres changements.';
        $this->error = '';
    }

    public function submitSitemap(SearchEngineIndexingService $indexing): void
    {
        $this->message = '';
        $this->error = '';

        try {
            $results = collect($indexing->submitSitemap('manual_settings'));
            $submitted = $results->where('status', 'submitted')->count();
            $failed = $results->where('status', 'failed')->count();
            $skipped = $results->where('status', 'skipped')->count();
            $this->message = "Soumission lancee : {$submitted} OK, {$failed} erreur(s), {$skipped} ignoree(s).";
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception->getMessage();
        }
    }

    public function test(GeminiContentGenerator $generator): void
    {
        $this->message = '';
        $this->error = '';

        try {
            $key = $this->apiKey !== '' ? $this->apiKey : null;
            $generator->testConnection($key, $this->model);
            $this->message = 'Connexion IA validee avec succes.';
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.settings', [
            'indexingSummary' => app(SearchEngineIndexingService::class)->configuredSummary(),
            'searchPerformanceSummary' => app(SearchPerformanceImportService::class)->configuredSummary(),
            'recentIndexingSubmissions' => SearchIndexingSubmission::query()->with('article')->latest('submitted_at')->limit(8)->get(),
        ])->title('Reglages API');
    }

    private function booleanSetting(string $key, bool $default): bool
    {
        $value = Setting::value($key);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
