<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\Category;
use App\Models\Keyword;
use App\Models\SeoProject;
use App\Models\Tag;
use App\Services\EditorialDuplicateDetector;
use App\Services\GeneratedContentSanitizer;
use App\Services\InternalLinkService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class ArticleEditor extends Component
{
    public ?Article $article = null;

    public ?int $projectId = null;

    public string $title = '';

    public string $slug = '';

    public string $excerpt = '';

    public string $body = '';

    public string $metaTitle = '';

    public string $metaDescription = '';

    public string $canonicalUrl = '';

    public string $primaryKeyword = '';

    public string $searchIntent = '';

    public string $contentAngle = '';

    public string $editorialAudience = '';

    public string $uniquePromise = '';

    public string $excludedTopicsText = '';

    public string $type = 'informational';

    public string $status = 'draft';

    public string $scheduledAt = '';

    public string $categoryName = '';

    public string $tagsText = '';

    public array $toolIds = [];

    public bool $includePricing = false;

    public string $changeNote = '';

    public string $message = '';

    public function mount(?Article $article = null): void
    {
        $this->article = $article?->exists ? $article->load(['categories', 'tags', 'tools']) : null;
        if (! $this->article) {
            $this->projectId = SeoProject::query()->value('id');
            $this->toolIds = $this->projectId ? [(string) $this->projectId] : [];

            return;
        }
        $this->projectId = $this->article->seo_project_id;
        foreach (['title', 'slug', 'excerpt', 'body', 'status', 'type'] as $field) {
            $this->{$field} = (string) $this->article->{$field};
        }
        $this->metaTitle = (string) $this->article->meta_title;
        $this->metaDescription = (string) $this->article->meta_description;
        $this->canonicalUrl = (string) $this->article->canonical_url;
        $this->primaryKeyword = (string) $this->article->primary_keyword;
        $this->searchIntent = (string) $this->article->search_intent;
        $this->contentAngle = (string) $this->article->content_angle;
        $this->editorialAudience = (string) $this->article->editorial_audience;
        $this->uniquePromise = (string) $this->article->unique_promise;
        $this->excludedTopicsText = implode("\n", $this->article->excluded_topics ?? []);
        $this->scheduledAt = $this->article->scheduled_at?->format('Y-m-d\TH:i') ?? '';
        $this->categoryName = (string) $this->article->categories->first()?->name;
        $this->tagsText = $this->article->tags->pluck('name')->implode(', ');
        $this->includePricing = collect($this->article->content_blocks)->contains('type', 'pricing_table');
        $this->toolIds = $this->article->tools->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function updatedTitle(): void
    {
        if (! $this->article) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function save(InternalLinkService $links, EditorialDuplicateDetector $duplicates, GeneratedContentSanitizer $sanitizer): void
    {
        $this->body = $sanitizer->sanitize($this->body);
        $this->validate([
            'projectId' => ['required', 'exists:seo_projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('articles', 'slug')->ignore($this->article?->id), function ($attribute, $value, $fail): void {
                if (preg_match('/-\d+$/', (string) $value) === 1) {
                    $fail('Les suffixes numériques sont interdits pour les slugs SEO. Différenciez l’angle éditorial.');
                }
                if (count(array_filter(explode('-', (string) $value))) > 5) {
                    $fail('Le slug SEO doit contenir au maximum 5 mots utiles.');
                }
            }],
            'body' => ['required', 'string', 'min:50'],
            'status' => ['required', 'in:draft,review,scheduled,published,archived'],
            'type' => ['required', 'in:tool_review,pricing,comparison,best_tools,alternatives,informational'],
            'scheduledAt' => ['nullable', 'date'],
            'toolIds' => ['array'],
            'toolIds.*' => ['exists:seo_projects,id'],
            'contentAngle' => ['nullable', 'string', 'max:150'],
            'editorialAudience' => ['nullable', 'string', 'max:150'],
            'uniquePromise' => ['nullable', 'string', 'max:1000'],
            'excludedTopicsText' => ['nullable', 'string', 'max:3000'],
        ]);

        $project = SeoProject::query()->findOrFail($this->projectId);
        $virtualKeyword = new Keyword(['keyword' => $this->primaryKeyword ?: $this->title, 'intent' => $this->searchIntent]);
        $blueprint = $duplicates->blueprint($project, $virtualKeyword, $this->type);
        $blueprint = $duplicates->customizeBlueprint(
            $blueprint,
            $this->editorialAudience,
            $this->contentAngle,
            $this->uniquePromise,
            collect(preg_split('/\R/', $this->excludedTopicsText) ?: [])->map(fn ($topic) => trim($topic))->filter()->values()->all(),
        );
        $duplicateAnalysis = $duplicates->analyzeBlueprint($project, $blueprint, $this->title.' '.$this->body, $this->article?->id);
        $similarArticle = $duplicateAnalysis['article'];
        $hasDuplicateWarning = $similarArticle && $duplicateAnalysis['decision'] !== 'allow';

        if ($this->article) {
            $this->article->versions()->create([
                'user_id' => auth()->id(),
                'version' => ($this->article->versions()->max('version') ?? 0) + 1,
                'title' => $this->article->title,
                'body' => $this->article->body,
                'content_blocks' => $this->article->content_blocks,
                'change_note' => $this->changeNote ?: 'Modification éditoriale',
            ]);
        }

        $blocks = [['type' => 'markdown', 'content' => $this->body]];
        if ($this->includePricing) {
            $blocks[] = ['type' => 'pricing_table', 'project_id' => $this->projectId, 'display' => 'monthly_and_yearly'];
        }
        $blocks[] = ['type' => 'affiliate_disclosure'];
        $blocks[] = ['type' => 'last_verified', 'date' => now()->toDateString()];

        $data = [
            'seo_project_id' => $this->projectId,
            'author_id' => auth()->id(),
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'content_blocks' => $blocks,
            'meta_title' => $this->metaTitle ?: $this->title,
            'meta_description' => $this->metaDescription ?: $this->excerpt,
            'canonical_url' => $this->canonicalUrl ?: null,
            'primary_keyword' => $this->primaryKeyword ?: null,
            'search_intent' => $this->searchIntent ?: null,
            ...$duplicates->articleFingerprintAttributes($blueprint),
            'canonical_article_id' => $hasDuplicateWarning ? $similarArticle->id : null,
            'duplicate_score' => $hasDuplicateWarning ? $duplicateAnalysis['score'] : null,
            'duplicate_status' => $hasDuplicateWarning
                ? (in_array($duplicateAnalysis['decision'], ['block', 'merge_or_reangle'], true) ? 'potential' : 'needs_differentiation')
                : null,
            'type' => $this->type,
            'status' => $this->status,
            'scheduled_at' => $this->status === 'scheduled' ? $this->scheduledAt : null,
            'published_at' => $this->status === 'published' ? ($this->article?->published_at ?? now()) : null,
            'verified_at' => now(),
        ];

        if ($this->article) {
            $this->article->update($data);
            $this->article->refresh();
        } else {
            $this->article = Article::query()->create($data);
        }
        $categoryIds = [];
        if (trim($this->categoryName) !== '') {
            $category = Category::query()->firstOrCreate(['slug' => Str::slug($this->categoryName)], ['name' => trim($this->categoryName)]);
            $categoryIds[] = $category->id;
        }
        $this->article->categories()->sync($categoryIds);
        $tagIds = collect(explode(',', $this->tagsText))->map(fn ($tag) => trim($tag))->filter()->unique()->map(function ($tag) {
            return Tag::query()->firstOrCreate(['slug' => Str::slug($tag)], ['name' => $tag])->id;
        })->all();
        $this->article->tags()->sync($tagIds);
        $this->article->tools()->sync(collect($this->toolIds)->mapWithKeys(fn ($id) => [$id => ['role' => ((int) $id === (int) $this->projectId ? 'featured' : 'compared')]])->all());
        if ($this->article->status === 'published') {
            $links->refreshProject($this->article->seo_project_id);
        } else {
            $links->refresh($this->article);
        }
        $action = $this->status === 'published' ? 'Article publié' : 'Article enregistré';
        $warning = $hasDuplicateWarning
            ? " Avertissement : similarité de {$duplicateAnalysis['score']} % avec « {$similarArticle->title} », conservée pour votre revue éditoriale."
            : '';
        $this->message = $action.' et version archivée.'.$warning;
    }

    public function render()
    {
        return view('livewire.article-editor', [
            'projects' => SeoProject::query()->orderBy('name')->get(),
            'versions' => $this->article?->versions()->latest('version')->limit(10)->get() ?? collect(),
        ])->title($this->article ? 'Modifier l’article' : 'Nouvel article');
    }
}
