<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\ContentRefreshTask;
use App\Models\SeoProject;
use App\Services\ContentRefreshPlanner;
use App\Services\PrePublishAuditService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.admin')]
class PrePublishAudits extends Component
{
    use WithPagination;

    public string $search = '';

    public string $auditStatus = '';

    public ?int $projectId = null;

    public string $message = '';

    public string $error = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAuditStatus(): void
    {
        $this->resetPage();
    }

    public function updatedProjectId(): void
    {
        $this->resetPage();
    }

    public function auditArticle(int $articleId, PrePublishAuditService $audits): void
    {
        $this->message = '';
        $this->error = '';

        try {
            $article = Article::query()->findOrFail($articleId);
            $audit = $audits->audit($article);
            $this->message = "Audit terminé pour « {$article->title} » : {$audit->status} ({$audit->score} / 100).";
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception->getMessage();
        }
    }

    public function auditVisible(PrePublishAuditService $audits): void
    {
        $count = 0;
        $this->message = '';
        $this->error = '';

        try {
            $this->filteredQuery()->limit(25)->get()->each(function (Article $article) use ($audits, &$count): void {
                $audits->audit($article);
                $count++;
            });
            $this->message = "{$count} article(s) audité(s).";
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception->getMessage();
        }
    }

    public function planRefresh(ContentRefreshPlanner $planner): void
    {
        $project = $this->projectId ? SeoProject::query()->findOrFail($this->projectId) : null;
        $result = $planner->plan($project, 100);
        $this->message = "Refresh planifié : {$result['created']} tâche(s) créée(s), {$result['skipped']} déjà en file.";
        $this->error = '';
    }

    public function render()
    {
        $articles = $this->filteredQuery()
            ->with(['project', 'keyword', 'latestAudit'])
            ->latest()
            ->paginate(15);

        return view('livewire.pre-publish-audits', [
            'articles' => $articles,
            'projects' => SeoProject::query()->orderBy('name')->get(),
            'refreshTasks' => ContentRefreshTask::query()
                ->with(['project', 'article', 'sourcePage'])
                ->latest()
                ->limit(12)
                ->get(),
        ])->title('Audits pré-publication');
    }

    private function filteredQuery(): Builder
    {
        return Article::query()
            ->where('status', '!=', 'archived')
            ->when($this->projectId, fn ($query) => $query->where('seo_project_id', $this->projectId))
            ->when($this->search, fn ($query) => $query->where(function ($subQuery): void {
                $subQuery->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('primary_keyword', 'like', '%'.$this->search.'%');
            }))
            ->when($this->auditStatus, fn ($query) => $query->where('prepublish_status', $this->auditStatus));
    }
}
