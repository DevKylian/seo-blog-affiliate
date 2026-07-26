<?php

namespace App\Livewire;

use App\Models\AdminAccessLog;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\SeoProject;
use App\Models\Setting;
use App\Models\SourcePage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Services\ConfigSyncService;
use Illuminate\Support\Facades\Cache;

#[Layout('layouts.admin')]
class Dashboard extends Component
{
    public bool $isImporting = false;
    public int $importProgress = 0;
    public function render()
    {
        return view('livewire.dashboard', [
            'stats' => [
                'projects' => SeoProject::count(),
                'sources' => SourcePage::where('status', 'verified')->count(),
                'keywords' => Keyword::count(),
                'articles' => Article::count(),
            ],
            'projects' => SeoProject::query()->withCount(['sourcePages', 'keywords', 'articles'])->latest()->limit(5)->get(),
            'articles' => Article::query()->with('project')->latest()->limit(5)->get(),
            'logs' => AdminAccessLog::query()->with('user')->latest('created_at')->limit(5)->get(),
            'hasApiKey' => (bool) Setting::value('gemini_api_key', config('services.gemini.key')),
        ])->title('Tableau de bord');
    }

    public function exportConfig(ConfigSyncService $exporter)
    {
        $filename = 'config_export_' . date('Y_m_d_His') . '.json';
        $content = $exporter->export();

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function receiveConfigChunk($uploadId, $chunkData, $isLast, ConfigSyncService $importer)
    {
        $this->isImporting = true;
        
        $cacheKey = "config_upload_{$uploadId}";
        $currentData = Cache::get($cacheKey, '');
        $currentData .= $chunkData;
        Cache::put($cacheKey, $currentData, now()->addMinutes(10));

        if ($isLast) {
            try {
                $importer->import($currentData);
                $this->dispatch('notify', ['message' => 'Configuration importée avec succès !', 'type' => 'success']);
            } catch (\Exception $e) {
                $this->dispatch('notify', ['message' => 'Erreur lors de l\'import : ' . $e->getMessage(), 'type' => 'error']);
            }
            Cache::forget($cacheKey);
            $this->isImporting = false;
            $this->importProgress = 100;
        }
    }
}
