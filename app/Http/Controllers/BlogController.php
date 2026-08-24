<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\EditorialIdea;
use App\Models\EditorialPlan;
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

        $metiers = json_decode(file_get_contents(public_path('metiers.json')), true);

        return view('blog.home', [
            'intention' => $intention,
            'latestArticles' => Article::query()->with(['project', 'categories'])->where('status', 'published')->latest('published_at')->limit(6)->get(),
            'hubs' => Article::where('type', 'pilier')->where('status', 'published')->get(),
            'categories' => $this->getCategoriesWithCounts(),
            'freeTools' => $this->freeToolCatalog(),
            'projects' => SeoProject::where('status', 'active')->get(),
            'metiers' => collect($metiers)->map(function ($m) {
                // Generate slug from the title to construct URLs properly
                $slug = \Illuminate\Support\Str::slug($m['nom']);
                $m['slug'] = $slug;
                $m['url'] = route('hubs.show', $slug);
                return $m;
            })->toArray()
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
            'categories' => $this->getCategoriesWithCounts(),
            'cluster' => $cluster,
        ]);
    }

    public function show(string $slug): View
    {
        $category = Category::query()->where('slug', $slug)->first();
        if ($category) {
            $articles = Article::query()
                ->with(['project', 'categories'])
                ->where('status', 'published')
                ->where(function ($query) use ($category) {
                    $query->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id));
                    if (in_array($category->slug, ['comparatifs', 'comparatif'], true)) {
                        $query->orWhere('type', 'comparison')->orWhere('slug', 'like', '%-vs-%');
                    }
                })
                ->latest('published_at')
                ->paginate(12);

            return view('blog.category', compact('category', 'articles'));
        }

        return $this->article($slug);
    }

    private function getCategoriesWithCounts()
    {
        return Category::query()
            ->withCount(['articles' => fn ($query) => $query->where('status', 'published')])
            ->orderBy('name')
            ->get()
            ->map(function ($cat) {
                if (in_array($cat->slug, ['comparatifs', 'comparatif'], true)) {
                    $comparisonCount = Article::query()
                        ->where('status', 'published')
                        ->where(function ($q) use ($cat) {
                            $q->whereHas('categories', fn ($c) => $c->where('categories.id', $cat->id))
                              ->orWhere('type', 'comparison')
                              ->orWhere('slug', 'like', '%-vs-%');
                        })
                        ->count();
                    $cat->articles_count = max((int) $cat->articles_count, $comparisonCount);
                }
                return $cat;
            });
    }

    public function article(string $slug, ?string $type = null): View
    {
        $types = match ($type) {
            'review', 'tool_review' => ['review', 'tool_review'],
            'guide', 'informational' => ['guide', 'informational'],
            'comparison', 'duels' => ['comparison', 'duels'],
            'best_tools', 'alternatives' => ['best_tools', 'alternatives'],
            default => $type ? [$type] : null,
        };

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
            ->where('status', 'published')
            ->where('slug', $slug)
            ->when($types, fn ($query) => $query->whereIn('type', $types))
            ->first();

        if ($article) {
            $article->increment('views');
            return view('blog.show', compact('article'));
        }

        $draftArticle = Article::query()
            ->where('slug', $slug)
            ->when($types, fn ($query) => $query->whereIn('type', $types))
            ->first();

        if ($draftArticle) {
            if (auth()->check()) {
                return $this->preview($draftArticle);
            }
            
            abort(404);
        }

        $normalizedSlug = str_replace('-', ' ', $slug);
        $idea = EditorialIdea::query()
            ->where(function ($query) use ($slug, $normalizedSlug) {
                $query->where('primary_keyword', $normalizedSlug)
                    ->orWhere('title', 'like', '%' . str_replace('-', '%', $slug) . '%');
            })
            ->when($types, fn ($query) => $query->whereIn('content_type', $types))
            ->latest('id')
            ->first();

        if ($idea) {
            $title = $idea->title;
            return view('blog.generating', compact('title', 'slug'));
        }

        if ($type === 'comparison' || str_contains($slug, '-vs-')) {
            $parts = explode('-vs-', $slug, 2);
            if (count($parts) === 2) {
                $projectSlug = $parts[0];
                $competitorName = ucfirst($parts[1]);
                $project = SeoProject::query()->where('slug', $projectSlug)->first();

                if ($project) {
                    $title = ucfirst($project->name) . ' vs ' . $competitorName;

                    return view('blog.missing_comparison', compact('title', 'slug'));
                }
            }
        }

        if ($type === 'review' || str_ends_with($slug, '-avis') || str_starts_with($slug, 'avis-')) {
            $toolSlug = str_replace(['-avis', 'avis-'], '', $slug);
            $project = SeoProject::query()->where('slug', $toolSlug)->first();
            $title = "Avis complet " . ucfirst($project?->name ?: $toolSlug);
            return view('blog.missing_comparison', compact('title', 'slug'));
        }

        abort(404);
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
        $tools = SeoProject::query()
            ->with(['plans' => fn ($q) => $q->where('is_active', true)->orderBy('position'), 'sourcePages'])
            ->withCount(['plans', 'articles'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $pricingGroups = $tools->mapWithKeys(fn ($tool) => [$tool->name => $tool->plans]);
        $comparisonRows = app(\App\Services\PricingComparisonPresenter::class)->rows($pricingGroups);

        return view('tools.index', compact('tools', 'comparisonRows'));
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

        $latestArticles = Article::query()
            ->with(['project', 'categories'])
            ->where('status', 'published')
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('tools.free-show', compact('tool', 'latestArticles'));
    }

    public function sitemap(): Response
    {
        $articles = Article::query()->where('status', 'published')->get(['slug', 'type', 'updated_at']);
        $tools = SeoProject::query()->where('status', 'active')->get(['slug', 'updated_at']);
        $categories = Category::query()->get(['slug', 'updated_at']);
        $freeTools = $this->freeToolCatalog();

        return response()->view('blog.sitemap', compact('articles', 'tools', 'categories', 'freeTools'))->header('Content-Type', 'application/xml');
    }

    private function freeToolCatalog(): array
    {
        return [
            [
                'slug' => 'generateur-facture-devis',
                'title' => 'Générateur de Devis et Facture PDF gratuit',
                'description' => 'Créez vos factures et devis conformes en 1 minute. Téléchargez-les directement en PDF.',
                'type' => 'generateur-facture',
                'category' => 'Facturation',
                'cta' => 'Créer une facture',
                'conversion_title' => 'Vous venez de faire une facture manuellement...',
                'conversion_text' => 'Saviez-vous qu\'Indy vous permet d\'en faire en illimité, gratuitement, avec archivage légal et transformation devis -> facture en 1 clic ?',
                'conversion_cta' => 'Passer à la facturation illimitée',
                'conversion_link' => '/go/indy',
                'conversion_color' => '#F75A77',
                'faq' => [
                    [
                        'question' => 'Ce générateur de facture est-il vraiment gratuit ?',
                        'answer' => 'Oui, notre outil est 100% gratuit et ne nécessite aucune création de compte. Vous pouvez générer autant de factures et de devis que vous le souhaitez en format PDF.'
                    ],
                    [
                        'question' => 'Les factures générées sont-elles conformes à la loi française ?',
                        'answer' => 'Absolument. Le modèle intègre toutes les mentions légales obligatoires (SIRET, numérotation, TVA, indemnités de retard) pour les indépendants et entreprises basées en France.'
                    ],
                    [
                        'question' => 'Mes données sont-elles conservées sur vos serveurs ?',
                        'answer' => 'Non. Tout le traitement se fait localement sur votre navigateur (via Javascript). Aucune de vos données ou données clients n\'est envoyée ni sauvegardée sur nos serveurs, garantissant une totale confidentialité.'
                    ]
                ]
            ],
            [
                'slug' => 'simulateur-cotisations-micro-entreprise',
                'title' => 'Simulateur de charges Micro-entreprise',
                'description' => 'Estimez vos cotisations URSSAF et votre impôt sur le revenu en fonction de votre Chiffre d\'Affaires.',
                'type' => 'simulateur-micro',
                'category' => 'Micro-entreprise',
                'cta' => 'Simuler mes cotisations',
                'conversion_title' => "Ces calculs vous prennent du temps chaque mois ?",
                'conversion_text' => 'Un logiciel comme Indy automatise vos déclarations URSSAF et vous alerte avant chaque échéance, 100% gratuitement.',
                'conversion_cta' => 'Automatiser mon URSSAF avec Indy',
                'conversion_link' => '/go/indy',
                'conversion_color' => '#F75A77',
                'faq' => [
                    [
                        'question' => 'Quels sont les taux de cotisations de la micro-entreprise en 2026 ?',
                        'answer' => 'Les taux officiels 2026 sont de 12,3% pour la vente de marchandises (BIC) et de 21,2% pour les prestations de services (BNC) et les professions libérales. Notre simulateur intègre toujours les taux URSSAF en vigueur les plus récents.'
                    ],
                    [
                        'question' => 'Le versement libératoire est-il pris en compte dans le calcul ?',
                        'answer' => 'Oui, si vous y êtes éligible, vous pouvez l\'inclure dans vos charges. Le taux varie de 1% à 2,2% supplémentaire selon votre type d\'activité.'
                    ]
                ]
            ],
            [
                'slug' => 'calculateur-tva',
                'title' => 'Calculateur de TVA en ligne (HT / TTC)',
                'description' => 'Convertissez instantanément vos montants HT en TTC avec les taux de 20%, 10%, 5.5% ou 2.1%.',
                'type' => 'calculateur-tva',
                'category' => 'Facturation',
                'cta' => 'Calculer ma TVA',
                'conversion_title' => "Envie d'automatiser ce calcul directement sur vos factures ?",
                'conversion_text' => 'Indy génère vos devis et factures conformes et calcule automatiquement la TVA à déclarer. Fini les erreurs de saisie !',
                'conversion_cta' => 'Essayer Indy gratuitement',
                'conversion_link' => '/go/indy',
                'conversion_color' => '#F75A77',
                'faq' => [
                    [
                        'question' => 'Comment calculer le prix TTC à partir du HT ?',
                        'answer' => 'La formule est simple : Montant HT x (1 + (Taux TVA / 100)). Par exemple, pour 100€ HT avec une TVA à 20%, le calcul est 100 x 1,20 = 120€ TTC.'
                    ],
                    [
                        'question' => 'Comment retrouver le montant HT à partir du TTC (calcul inversé) ?',
                        'answer' => 'Pour retrouver le HT, divisez le montant TTC par (1 + (Taux TVA / 100)). Par exemple, 120€ TTC / 1,20 = 100€ HT.'
                    ]
                ]
            ],
            [
                'slug' => 'calculateur-tjm-freelance',
                'title' => 'Calculateur de TJM Freelance',
                'description' => 'Déterminez le taux journalier idéal pour atteindre votre objectif de salaire net.',
                'type' => 'calculateur-tjm',
                'category' => 'Gestion',
                'cta' => 'Calculer mon TJM',
                'conversion_title' => "Pilotez votre rentabilité en temps réel",
                'conversion_text' => "Une fois votre TJM fixé, suivez l'évolution de votre chiffre d'affaires et de vos charges directement depuis le tableau de bord d'Indy.",
                'conversion_cta' => 'Découvrir Indy',
                'conversion_link' => '/go/indy',
                'conversion_color' => '#F75A77',
            ],
            [
                'slug' => 'calculateur-frais-kilometriques',
                'title' => 'Calculateur de Barème Kilométrique (IK)',
                'description' => 'Calculez instantanément le montant déductible de vos frais de déplacement selon le barème officiel 2026.',
                'type' => 'calculateur-ik',
                'category' => 'Gestion',
                'cta' => 'Calculer mes IK',
                'conversion_title' => 'Automatisez vos frais kilométriques !',
                'conversion_text' => 'Saisissez simplement vos trajets dans l\'application Indy et le logiciel calcule et déduit vos IK automatiquement.',
                'conversion_cta' => 'Gérer mes IK avec Indy',
                'conversion_link' => '/go/indy',
                'conversion_color' => '#F75A77',
            ],
            [
                'slug' => 'comparateur-micro-sasu',
                'title' => 'Comparateur de revenus Micro vs SASU',
                'description' => 'Pour 50 000€ facturés, découvrez le statut juridique qui vous laisse le plus d\'argent net dans la poche.',
                'type' => 'comparateur-statuts',
                'category' => 'Création',
                'cta' => 'Comparer les statuts',
                'conversion_title' => "Vous envisagez de passer en société ?",
                'conversion_text' => 'Indy vous accompagne gratuitement dans la création de votre SASU ou EURL, de la rédaction des statuts au dépôt du capital.',
                'conversion_cta' => 'Créer ma société avec Indy',
                'conversion_link' => '/go/indy',
                'conversion_color' => '#F75A77',
            ],
            [
                'slug' => 'simulateur-micro-vs-reel',
                'title' => 'Simulateur Micro-Entreprise vs Régime Réel',
                'description' => 'Comparez l\'abattement forfaitaire de la micro-entreprise avec la déduction de vos charges au réel.',
                'type' => 'simulateur-micro-vs-reel',
                'category' => 'Création',
                'cta' => 'Lancer la simulation',
                'conversion_title' => 'Le passage au régime réel simplifié',
                'conversion_text' => 'Passer au régime réel demande une vraie comptabilité. Indy automatise toute votre liasse fiscale sans expert-comptable.',
                'conversion_cta' => 'Découvrir la compta au réel avec Indy',
                'conversion_link' => '/go/indy',
                'conversion_color' => '#F75A77',
            ],
            [
                'slug' => 'simulateur-sasu-dividendes',
                'title' => 'Simulateur SASU : Salaire vs Dividendes',
                'description' => 'Optimisez votre rémunération en SASU en comparant la distribution de dividendes et le versement d\'un salaire.',
                'type' => 'simulateur-sasu-dividendes',
                'category' => 'Société',
                'cta' => 'Optimiser ma SASU',
                'conversion_title' => 'Pilotez la comptabilité de votre SASU',
                'conversion_text' => 'Indy édite vos fiches de paie de dirigeant, gère votre IS et génère le bilan de votre SASU automatiquement.',
                'conversion_cta' => 'Gérer ma SASU avec Indy',
                'conversion_link' => '/go/indy',
                'conversion_color' => '#F75A77',
            ],
            [
                'slug' => 'seuil-tva-micro-entreprise',
                'title' => 'Calculateur dépassement seuil TVA',
                'description' => 'Vérifiez en 2 clics si vous devez facturer la TVA suite au dépassement des seuils de la franchise en base.',
                'type' => 'seuil-tva',
                'category' => 'Micro-entreprise',
                'cta' => 'Vérifier mes seuils',
                'conversion_title' => "Peur de rater le passage à la TVA ?",
                'conversion_text' => "Indy suit votre chiffre d'affaires en temps réel et vous alerte automatiquement quand vous dépassez le seuil de franchise en base.",
                'conversion_cta' => 'Sécuriser ma facturation avec Indy',
                'conversion_link' => '/go/indy',
                'conversion_color' => '#F75A77',
            ],
            [
                'slug' => 'calculateur-tva-sur-marge',
                'title' => 'Calculateur de TVA sur marge',
                'description' => 'Biens d\'occasion, friperies, antiquaires : calculez précisément la TVA à reverser sur votre marge nette.',
                'type' => 'calculateur-tva-marge',
                'category' => 'E-commerce',
                'cta' => 'Calculer ma TVA sur marge',
                'conversion_title' => 'Besoin d\'un outil qui gère nativement la TVA sur marge ?',
                'conversion_text' => 'Attention : Indy ne gère pas la TVA sur marge. Pennylane est indispensable pour les vendeurs de seconde main.',
                'conversion_cta' => 'Découvrir Pennylane',
                'conversion_link' => '/go/pennylane',
                'conversion_color' => '#3b82f6',
            ],
            [
                'slug' => 'simulateur-seuil-oss',
                'title' => 'Simulateur Seuil OSS (Ventes intra-UE)',
                'description' => 'Vérifiez si vous dépassez le seuil des 10 000€ pour la TVA sur vos ventes à distance en Europe.',
                'type' => 'simulateur-seuil-oss',
                'category' => 'E-commerce',
                'cta' => 'Vérifier mon seuil OSS',
                'conversion_title' => 'Simplifiez votre comptabilité E-commerce',
                'conversion_text' => 'Pennylane se connecte directement à Shopify et Stripe pour gérer vos taux de TVA européens sans effort.',
                'conversion_cta' => 'Connecter ma boutique à Pennylane',
                'conversion_link' => '/go/pennylane',
                'conversion_color' => '#3b82f6',
            ],
            [
                'slug' => 'generateur-penalites-retard',
                'title' => 'Générateur de pénalités de retard',
                'description' => 'Calculez légalement les indemnités et pénalités de retard applicables sur une facture impayée.',
                'type' => 'penalites-retard',
                'category' => 'Facturation',
                'cta' => 'Calculer les pénalités',
                'conversion_title' => "Fatigué de courir après les impayés ?",
                'conversion_text' => 'Avec Indy, activez le suivi des paiements et relancez vos clients en un clic avec calcul automatique des pénalités.',
                'conversion_cta' => 'Gérer mes factures avec Indy',
                'conversion_link' => '/go/indy',
                'conversion_color' => '#F75A77',
            ]
        ];
    }
}
