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
        // Détection de l'intention (CRO dynamique)
        $referer = request()->headers->get('referer', '');
        $utmCampaign = request('utm_campaign', '');
        $intention = 'general';

        if (str_contains(strtolower($referer . $utmCampaign), 'tva') || str_contains(strtolower($referer . $utmCampaign), 'sasu')) {
            $intention = 'tva_sasu';
        } elseif (str_contains(strtolower($referer . $utmCampaign), 'deduire')) {
            $intention = 'deductions';
        }

        return view('blog.home', [
            'intention' => $intention,
            'latestArticles' => Article::query()->with(['project', 'categories'])->where('status', 'published')->latest('published_at')->limit(6)->get(),
            'categories' => Category::query()->withCount(['articles' => fn ($query) => $query->where('status', 'published')])->orderBy('name')->get(),
            'freeTools' => $this->freeToolCatalog(),
            'projects' => SeoProject::where('status', 'active')->get()
        ]);
    }

    public function about(): View
    {
        return view('blog.author');
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

    public function review(string $slug): View
    {
        return $this->article($slug, 'review');
    }

    public function guide(string $slug): View
    {
        return $this->article($slug, 'guide');
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
                'slug' => 'calculateur-tva',
                'title' => 'Calculateur de TVA en ligne (HT / TTC)',
                'description' => 'Convertissez instantanément vos montants HT en TTC avec les taux de 20%, 10%, 5.5% ou 2.1%.',
                'type' => 'calculateur-tva',
                'cta' => 'Calculer ma TVA',
            ],
            [
                'slug' => 'calculateur-tjm-freelance',
                'title' => 'Calculateur de TJM Freelance',
                'description' => 'Déterminez le taux journalier idéal pour atteindre votre objectif de salaire net.',
                'type' => 'calculateur-tjm',
                'cta' => 'Calculer mon TJM',
            ],
            [
                'slug' => 'simulateur-cotisations-micro-entreprise',
                'title' => 'Simulateur de charges Micro-entreprise',
                'description' => 'Estimez vos cotisations URSSAF et votre impôt sur le revenu en fonction de votre Chiffre d\'Affaires.',
                'type' => 'simulateur-micro',
                'cta' => 'Simuler mes cotisations',
            ],
            [
                'slug' => 'seuil-tva-micro-entreprise',
                'title' => 'Calculateur dépassement seuil TVA',
                'description' => 'Vérifiez en 2 clics si vous devez facturer la TVA suite au dépassement des seuils de la franchise en base.',
                'type' => 'seuil-tva',
                'cta' => 'Vérifier mes seuils',
            ],
            [
                'slug' => 'generateur-penalites-retard',
                'title' => 'Générateur de pénalités de retard',
                'description' => 'Calculez légalement les indemnités et pénalités de retard applicables sur une facture impayée.',
                'type' => 'penalites-retard',
                'cta' => 'Calculer les pénalités',
            ],
            [
                'slug' => 'comparateur-micro-sasu',
                'title' => 'Comparateur de revenus Micro vs SASU',
                'description' => 'Pour 50 000€ facturés, découvrez le statut juridique qui vous laisse le plus d\'argent net dans la poche.',
                'type' => 'comparateur-statuts',
                'cta' => 'Comparer les statuts',
            ],
        ];
    }
}
