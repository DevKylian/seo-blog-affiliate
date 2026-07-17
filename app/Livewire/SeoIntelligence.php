<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\SearchPerformanceSnapshot;
use App\Models\SeoActionItem;
use App\Models\SeoProject;
use App\Models\SerpDifferentiationBrief;
use App\Models\Setting;
use App\Services\BackgroundArtisanLauncher;
use App\Services\SeoIntelligenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.admin')]
class SeoIntelligence extends Component
{
    use WithPagination;

    public string $status = 'queued';

    public string $type = '';

    public ?int $projectId = null;

    public string $search = '';

    public string $message = '';

    public string $error = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedProjectId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function launchLoop(BackgroundArtisanLauncher $launcher): void
    {
        $this->message = '';
        $this->error = '';

        try {
            $launcher->launch('seo:intelligence', 1, 'seo-intelligence.log');
            $this->message = 'Boucle SEO lancée en arrière-plan : import GSC/Bing, analyse, actions et refreshs.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception->getMessage();
        }
    }

    public function analyzeWithoutImport(SeoIntelligenceService $intelligence): void
    {
        $this->message = '';
        $this->error = '';

        try {
            $result = $intelligence->analyze(28);
            $this->message = "Analyse locale terminée : {$result['actions']} action(s), {$result['briefs']} brief(s), {$result['refresh_tasks']} refresh(s).";
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception->getMessage();
        }
    }

    public function completeAction(int $id): void
    {
        SeoActionItem::query()->whereKey($id)->update([
            'status' => 'done',
            'completed_at' => now(),
        ]);
        $this->message = 'Action marquée comme terminée.';
        $this->error = '';
    }

    public function dismissAction(int $id): void
    {
        SeoActionItem::query()->whereKey($id)->update([
            'status' => 'dismissed',
            'completed_at' => now(),
        ]);
        $this->message = 'Action ignorée.';
        $this->error = '';
    }

    public function buildBrief(int $articleId, SeoIntelligenceService $intelligence): void
    {
        $this->message = '';
        $this->error = '';

        try {
            $article = Article::query()->with(['project', 'keyword'])->findOrFail($articleId);
            $brief = $intelligence->buildDifferentiationBrief($article->project, $article->keyword, $article);
            $this->message = $brief
                ? "Brief de différenciation généré pour « {$article->title} »."
                : 'Impossible de générer un brief exploitable pour cet article.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception->getMessage();
        }
    }

    public function render()
    {
        $since = now()->subDays(28)->toDateString();
        $snapshots = SearchPerformanceSnapshot::query()->where('date_to', '>=', $since);
        $impressions = (int) (clone $snapshots)->sum('impressions');
        $clicks = (int) (clone $snapshots)->sum('clicks');

        return view('livewire.seo-intelligence', [
            'projects' => SeoProject::query()->orderBy('name')->get(),
            'stats' => [
                'snapshots' => (clone $snapshots)->count(),
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                'position' => round((float) (clone $snapshots)->where('position', '>', 0)->avg('position'), 2),
                'queued_actions' => SeoActionItem::query()->where('status', 'queued')->count(),
                'last_import' => Setting::value('search_performance_last_import_at'),
            ],
            'actionItems' => $this->actionsQuery()->paginate(12),
            'topQueries' => $this->topQueries($since),
            'briefs' => SerpDifferentiationBrief::query()
                ->with(['project', 'article', 'keyword'])
                ->latest('generated_at')
                ->limit(8)
                ->get(),
        ])->title('SEO Intelligence');
    }

    private function actionsQuery(): Builder
    {
        return SeoActionItem::query()
            ->with(['project', 'article', 'keyword'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when($this->projectId, fn ($query) => $query->where('seo_project_id', $this->projectId))
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($subQuery): void {
                    $subQuery->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhereHas('article', fn ($articleQuery) => $articleQuery->where('title', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('keyword', fn ($keywordQuery) => $keywordQuery->where('keyword', 'like', '%'.$this->search.'%'));
                });
            })
            ->orderBy('priority')
            ->orderByDesc('created_at');
    }

    private function topQueries(string $since)
    {
        return SearchPerformanceSnapshot::query()
            ->select([
                'query',
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('AVG(position) as position'),
            ])
            ->where('date_to', '>=', $since)
            ->whereNotNull('query')
            ->where('query', '!=', '')
            ->groupBy('query')
            ->orderByDesc('impressions')
            ->limit(12)
            ->get();
    }
}
