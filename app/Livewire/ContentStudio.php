<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\Keyword;
use App\Models\SeoProject;
use App\Models\Setting;
use App\Services\GeminiContentGenerator;
use App\Services\GeneratedContentSanitizer;
use App\Services\InternalLinkService;
use App\Services\PrePublishAuditService;
use App\Services\SearchIndexingSubmissionLauncher;
use App\Services\SeoContentStructure;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin')]
class ContentStudio extends Component
{
    public ?int $projectId = null;

    public ?int $keywordId = null;

    public ?int $selectedArticleId = null;

    public string $type = 'tool_review';

    public string $instructions = '';

    public string $message = '';

    public string $error = '';

    public function mount(): void
    {
        $this->projectId = SeoProject::query()->value('id');
        $this->keywordId = $this->projectId ? Keyword::query()->where('seo_project_id', $this->projectId)->orderByDesc('opportunity_score')->value('id') : null;
    }

    public function updatedProjectId(): void
    {
        $this->keywordId = Keyword::query()->where('seo_project_id', $this->projectId)->orderByDesc('opportunity_score')->value('id');
        $this->selectedArticleId = null;
    }

    public function generate(GeminiContentGenerator $generator): void
    {
        $this->validate([
            'projectId' => ['required', 'exists:seo_projects,id'],
            'keywordId' => ['nullable', 'exists:keywords,id'],
            'type' => ['required', 'in:tool_review,pricing,comparison,best_tools,alternatives,informational'],
            'instructions' => ['nullable', 'string', 'max:3000'],
        ]);
        $this->message = '';
        $this->error = '';
        try {
            $project = SeoProject::query()->findOrFail($this->projectId);
            $keyword = $this->keywordId ? Keyword::query()->where('seo_project_id', $project->id)->findOrFail($this->keywordId) : null;
            $article = $generator->generate($project, $this->type, $keyword, $this->instructions);
            $this->selectedArticleId = $article->id;
            $this->message = 'Le brouillon a été généré. Une validation humaine reste obligatoire avant publication.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception->getMessage();
        }
    }

    public function selectArticle(int $articleId): void
    {
        $this->selectedArticleId = $articleId;
    }

    public function publish(int $articleId, InternalLinkService $links, SearchIndexingSubmissionLauncher $indexing, PrePublishAuditService $audits): void
    {
        $article = Article::query()->findOrFail($articleId);
        if ($article->status === 'archived' || $article->duplicate_status === 'merged') {
            $this->error = 'Un doublon fusionné ne peut pas être republié. Modifiez l’article canonique associé.';

            return;
        }
        $audit = $audits->audit($article);
        if ($audit->status === 'blocked') {
            $this->error = 'Publication bloquée par l’audit pré-publication : '.implode(' ', array_slice($audit->blocking_reasons ?? [], 0, 3));

            return;
        }

        $article->update(['status' => 'published', 'published_at' => now()]);
        $links->refreshProject($article->seo_project_id);
        try {
            $indexing->launch($article->id);
        } catch (Throwable $exception) {
            report($exception);
        }
        $this->selectedArticleId = $article->id;
        $this->message = 'Article publie sur le blog Laravel. Soumission Google/Bing lancee en arriere-plan.';
    }

    public function previewHtml(?Article $article): string
    {
        if (! $article) {
            return '';
        }

        $body = app(GeneratedContentSanitizer::class)->stripSourceMarkers($article->body);

        return Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public function render()
    {
        $articles = Article::query()->with(['project', 'brief'])->where('status', '!=', 'archived')->when($this->projectId, fn ($query) => $query->where('seo_project_id', $this->projectId))->latest()->limit(20)->get();
        $selectedArticle = $this->selectedArticleId ? Article::query()->with('brief')->find($this->selectedArticleId) : $articles->first();

        return view('livewire.content-studio', [
            'projects' => SeoProject::query()->orderBy('name')->get(),
            'keywords' => Keyword::query()->when($this->projectId, fn ($query) => $query->where('seo_project_id', $this->projectId))->orderByDesc('opportunity_score')->limit(100)->get(),
            'articles' => $articles,
            'selectedArticle' => $selectedArticle,
            'hasApiKey' => (bool) Setting::value('gemini_api_key', config('services.gemini.key')),
            'contentStructure' => app(SeoContentStructure::class)->for($this->type),
        ])->title('Studio de contenu');
    }
}
