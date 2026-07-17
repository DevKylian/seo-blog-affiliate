<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\SeoProject;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class BlogController extends Controller
{
    public function home(): View
    {
        return view('blog.home', [
            'latestArticles' => Article::query()->with(['project', 'categories'])->where('status', 'published')->latest('published_at')->limit(6)->get(),
            'categories' => Category::query()->withCount(['articles' => fn ($query) => $query->where('status', 'published')])->orderBy('name')->get(),
        ]);
    }

    public function index(): View
    {
        $cluster = request('cluster');

        return view('blog.index', [
            'articles' => Article::query()
                ->with(['project', 'categories', 'keyword'])
                ->where('status', 'published')
                ->when($cluster, fn ($query) => $query->whereHas('keyword', fn ($keywordQuery) => $keywordQuery->where('affiliate_cluster', $cluster)))
                ->latest('published_at')
                ->paginate(12),
            'categories' => Category::query()->withCount(['articles' => fn ($query) => $query->where('status', 'published')])->orderBy('name')->get(),
            'cluster' => $cluster,
        ]);
    }

    public function show(string $slug): View
    {
        $category = Category::query()->where('slug', $slug)->first();
        if ($category) {
            return view('blog.category', [
                'category' => $category,
                'articles' => $category->articles()->with('project')->where('status', 'published')->latest('published_at')->paginate(12),
            ]);
        }

        return $this->article($slug);
    }

    public function article(string $slug, ?string $type = null): View
    {
        $article = Article::query()->with([
            'project.plans' => fn ($query) => $query->where('is_active', true)->orderBy('position'),
            'project.competitorPlans' => fn ($query) => $query->where('is_active', true)->orderBy('competitor_name')->orderBy('position'),
            'project.sourcePages' => fn ($query) => $query->where('status', 'verified')->where('type', 'pricing')->orderBy('competitor_name'),
            'keyword',
            'contentCluster',
            'categories',
            'tags',
            'sources',
            'internalLinks.target',
        ])
            ->where('status', 'published')->where('slug', $slug)
            ->when($type, fn ($query) => $query->where('type', $type))->firstOrFail();

        return view('blog.show', compact('article'));
    }

    public function preview(Article $article): View
    {
        $article->load([
            'project.plans' => fn ($query) => $query->where('is_active', true)->orderBy('position'),
            'project.competitorPlans' => fn ($query) => $query->where('is_active', true)->orderBy('competitor_name')->orderBy('position'),
            'project.sourcePages' => fn ($query) => $query->where('status', 'verified')->where('type', 'pricing')->orderBy('competitor_name'),
            'keyword',
            'contentCluster',
            'categories',
            'tags',
            'sources',
            'internalLinks.target',
        ]);

        return view('blog.show', compact('article'));
    }

    public function comparison(string $slug): View
    {
        return $this->article($slug, 'comparison');
    }

    public function alternatives(string $slug): View
    {
        return $this->article($slug, 'alternatives');
    }

    public function bestTools(string $slug): View
    {
        return $this->article($slug, 'best_tools');
    }

    public function tools(): View
    {
        return view('tools.index', ['tools' => SeoProject::query()->withCount(['plans', 'articles'])->where('status', 'active')->orderBy('name')->get()]);
    }

    public function tool(string $slug): View
    {
        $tool = SeoProject::query()->with(['plans' => fn ($query) => $query->where('is_active', true)->orderBy('position'), 'sourcePages' => fn ($query) => $query->where('status', 'verified')])->where('slug', $slug)->firstOrFail();

        return view('tools.show', compact('tool'));
    }

    public function pricing(string $slug): View
    {
        $tool = SeoProject::query()->with(['plans' => fn ($query) => $query->where('is_active', true)->with('snapshots')->orderBy('position')])->where('slug', $slug)->firstOrFail();

        return view('tools.pricing', compact('tool'));
    }

    public function freeTools(): View
    {
        return view('tools.free-index', ['tools' => $this->freeToolCatalog()]);
    }

    public function freeTool(string $slug): View
    {
        $tool = collect($this->freeToolCatalog())->firstWhere('slug', $slug);
        abort_unless($tool, 404);

        return view('tools.free-show', compact('tool'));
    }

    public function sitemap(): Response
    {
        $articles = Article::query()->where('status', 'published')->get(['slug', 'type', 'updated_at']);
        $tools = SeoProject::query()->where('status', 'active')->get(['slug', 'updated_at']);

        return response()->view('blog.sitemap', compact('articles', 'tools'))->header('Content-Type', 'application/xml');
    }

    private function freeToolCatalog(): array
    {
        return [
            [
                'slug' => 'calculateur-tjm-freelance',
                'title' => 'Calculateur TJM freelance',
                'description' => 'Estimez le tarif journalier nécessaire pour atteindre votre objectif annuel.',
                'type' => 'tjm',
                'cta' => 'Recevoir mon résultat détaillé',
            ],
            [
                'slug' => 'calculateur-revenu-freelance',
                'title' => 'Calculateur revenu freelance',
                'description' => 'Estimez ce qu’il reste après charges pour mieux piloter votre activité.',
                'type' => 'revenu',
                'cta' => 'Calculer mon revenu net',
            ],
            [
                'slug' => 'checklist-creation-micro-entreprise',
                'title' => 'Checklist création micro-entreprise',
                'description' => 'Les étapes à suivre après le SIRET pour éviter les oublis administratifs.',
                'type' => 'checklist',
                'cta' => 'Voir la checklist',
            ],
        ];
    }
}
