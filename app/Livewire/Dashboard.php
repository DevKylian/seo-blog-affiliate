<?php

namespace App\Livewire;

use App\Models\AdminAccessLog;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\SeoProject;
use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.dashboard', [
            'stats' => [
                'projects' => SeoProject::count(),
                'keywords' => Keyword::count(),
                'articles' => Article::count(),
            ],
            'projects' => SeoProject::query()->withCount(['sourcePages', 'keywords'])->latest()->limit(5)->get(),
            'articles' => Article::query()->latest()->limit(5)->get(),
            'logs' => AdminAccessLog::query()->with('user')->latest('created_at')->limit(5)->get(),
            'hasApiKey' => (bool) Setting::value('gemini_api_key', config('services.gemini.key')),
        ])->title('Tableau de bord');
    }
}
