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
use App\Services\PrePublishAuditService;
use App\Services\SearchIndexingSubmissionLauncher;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class ArticleEditor extends Component
{
    public ?Article $article = null;

    public string $title = '';

    public ?string $thumbnailTitle = null;

    public string $slug = '';

    public string $excerpt = '';

    public string $body = '';

    public string $metaTitle = '';

    public string $metaDescription = '';

    public string $canonicalUrl = '';

    public string $primaryKeyword = '';

    public string $searchIntent = '';

    public string $type = 'informational';

    public string $status = 'draft';

    public string $scheduledAt = '';

    public string $categoryName = '';

    public string $tagsText = '';

    public array $toolIds = [];

    public bool $includePricing = false;

    public string $message = '';

    public string $contentAngle = '';

    public string $editorialAudience = '';

    public string $uniquePromise = '';

    public string $excludedTopicsText = '';

    public function mount(?Article $article = null): void
    {
        $this->article = $article?->exists ? $article->load(['categories', 'tags', 'tools']) : null;
        if (! $this->article) {
            return;
        }
        foreach (['title', 'slug', 'excerpt', 'body', 'status', 'type'] as $field) {
            $this->{$field} = (string) $this->article->{$field};
        }
        $this->thumbnailTitle = (string) $this->article->thumbnail_title;
        $this->body = app(GeneratedContentSanitizer::class)->stripSourceMarkers($this->body);
        $this->metaTitle = (string) $this->article->meta_title;
        $this->metaDescription = (string) $this->article->meta_description;
        $this->canonicalUrl = (string) $this->article->canonical_url;
        $this->primaryKeyword = (string) $this->article->primary_keyword;
        $this->searchIntent = (string) $this->article->search_intent;
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

    public function save(InternalLinkService $links, EditorialDuplicateDetector $duplicates, GeneratedContentSanitizer $sanitizer, SearchIndexingSubmissionLauncher $indexing, PrePublishAuditService $audits): void
    {
        $this->body = $sanitizer->sanitize($this->body);
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('articles', 'slug')->ignore($this->article?->id), function ($attribute, $value, $fail): void {
                if ($this->looksLikeDuplicateNumericSlug((string) $value)) {
                    $fail('Les suffixes numériques sont interdits pour les slugs SEO. Différenciez l’angle éditorial.');
                }
            }],
            'body' => ['required', 'string', 'min:50'],
            'status' => ['required', 'in:draft,review,scheduled,published,archived'],
            'type' => ['required', 'in:tool_review,pricing,comparison,best_tools,alternatives,informational,question,pilier,metier'],
            'scheduledAt' => ['nullable', 'date'],
            'toolIds' => ['array'],
            'toolIds.*' => ['exists:seo_projects,id'],
            'contentAngle' => ['nullable', 'string', 'max:1000'],
            'editorialAudience' => ['nullable', 'string', 'max:1000'],
            'uniquePromise' => ['nullable', 'string', 'max:1000'],
            'excludedTopicsText' => ['nullable', 'string', 'max:3000'],
        ]);

        $project = new \App\Models\SeoProject();
        $virtualKeyword = new Keyword(['keyword' => $this->primaryKeyword ?: $this->title, 'intent' => $this->searchIntent]);
        $blueprint = $duplicates->blueprint($project, $virtualKeyword, $this->type);
        $blueprint = $duplicates->customizeBlueprint(
            $blueprint,
            $this->editorialAudience,
            $this->contentAngle,
            $this->uniquePromise,
            collect(preg_split('/\R/', $this->excludedTopicsText) ?: [])->map(fn ($topic) => trim($topic))->filter()->values()->all(),
        );
        $duplicateAnalysis = $duplicates->analyzeBlueprint($project, $blueprint, $this->article?->id);
        $similarArticle = $duplicateAnalysis['article'];
        $hasDuplicateWarning = $similarArticle && $duplicateAnalysis['decision'] !== 'allow';

        if ($this->article) {
            }

        $existingBlocks = $this->article?->content_blocks ?? [];
        $ctaBlocks = collect($existingBlocks)->filter(fn($b) => ($b['type'] ?? '') === 'affiliate_cta')->values();
        
        $blocks = [];
        if ($ctaBlocks->isNotEmpty()) {
            $blocks[] = $ctaBlocks->first();
        }
        
        $blocks[] = ['type' => 'markdown', 'content' => $this->body];
        
        if ($ctaBlocks->count() > 1) {
            $blocks[] = ['type' => 'affiliate_cta', 'position' => 'after_intro']; // Force bottom CTA to use after_intro style
        }
        
        $blocks[] = ['type' => 'affiliate_disclosure'];
        $blocks[] = ['type' => 'last_verified', 'date' => now()->toDateString()];

        $data = [
            'author_id' => auth()->id(),
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'content_blocks' => $blocks,
            'meta_title' => $this->metaTitle ?: $this->title,
            'meta_description' => $this->metaDescription ?: $this->excerpt,
            'canonical_url' => $this->canonicalUrl ?: null,
            'thumbnail_title' => $this->thumbnailTitle ?: null,
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
        $this->article->tools()->sync(collect($this->toolIds)->mapWithKeys(fn ($id) => [$id => ['role' => 'compared']])->all());
        $audit = $audits->audit($this->article->fresh());
        if (in_array($this->status, ['published', 'scheduled'], true) && $audit->status === 'blocked') {
            $this->article->update([
                'status' => 'review',
                'published_at' => null,
                'scheduled_at' => null,
                'refresh_status' => 'needs_review',
                'refresh_reason' => 'Publication bloquée par l’audit pré-publication.',
            ]);
            $this->status = 'review';
            $this->addError('status', 'Publication bloquée par l’audit : '.implode(' ', array_slice($audit->blocking_reasons ?? [], 0, 3)));

            return;
        }

        if ($this->article->status === 'published') {
            try {
                $indexing->launch($this->article->id);
            } catch (\Throwable $exception) {
                report($exception);
            }
        } else {
            $links->refresh($this->article);
        }
        $action = $this->status === 'published' ? 'Article publié' : 'Article enregistré';
        $warning = $hasDuplicateWarning
            ? " Avertissement : similarité de {$duplicateAnalysis['score']} % avec « {$similarArticle->title} », conservée pour votre revue éditoriale."
            : '';
        $this->message = $action.' et version archivée.'.$warning;
    }

    public function regenerateThumbnail(\App\Services\BlogThumbnailService $thumbnailService)
    {
        if ($this->article) {
            if (empty($this->thumbnailTitle)) {
                $key = \App\Models\Setting::value('gemini_api_key', config('services.gemini.key'));
                if ($key) {
                    $response = \Illuminate\Support\Facades\Http::timeout(15)->withHeaders([
                        'x-goog-api-key' => trim($key),
                        'Content-Type' => 'application/json',
                    ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', [
                        'contents' => [['parts' => [['text' => "Tu es un expert SEO. Résume ce titre d'article pour une miniature d'image (7 mots MAXIMUM, très percutant). Règle ABSOLUE : tu dois uniquement renvoyer le titre brut, sans introduction, sans choix multiples, sans puces, et sans points ni guillemets. Titre d'origine : {$this->title}"]]]],
                        'generationConfig' => ['temperature' => 0.2]
                    ]);
                    if ($response->successful()) {
                        $text = $response->json('candidates.0.content.parts.0.text');
                        if ($text) {
                            $cleanText = trim(str_replace(['"', '*'], '', $text));
                            $this->thumbnailTitle = \Illuminate\Support\Str::limit($cleanText, 200, '');
                            $this->article->update(['thumbnail_title' => $this->thumbnailTitle]);
                        }
                    }
                }
            }

            $thumbnailService->forget($this->article->slug);
            $thumbnailService->ensure(
                $this->article->slug,
                $this->thumbnailTitle ?? $this->title,
                'BUSINESSKIT',
                null,
                $this->article->updated_at
            );
            $this->message = "Miniature régénérée avec succès par l'IA !";
        }
    }

    public function render()
    {
        return view('livewire.article-editor', [
            
            
        ])->title($this->article ? 'Modifier l’article' : 'Nouvel article');
    }

    private function looksLikeDuplicateNumericSlug(string $slug): bool
    {
        if (preg_match('/-(\d{1,2})$/', $slug) !== 1) {
            return false;
        }

        $base = preg_replace('/-\d{1,2}$/', '', $slug) ?: '';
        if ($base === '') {
            return false;
        }

        return Article::query()
            ->where('slug', $base)
            ->when($this->article?->id, fn ($query) => $query->whereKeyNot($this->article->id))
            ->exists();
    }
}
