<?php

namespace App\Livewire;

use App\Exceptions\DuplicateContentException;
use App\Exceptions\PlannedContentRejectedException;
use App\Livewire\Concerns\WithBulkSelection;
use App\Models\ContentRun;
use App\Models\EditorialPlan;
use App\Models\Keyword;
use App\Models\SeoProject;
use App\Models\Setting;
use App\Models\SourcePage;
use App\Services\ContentRunProcessor;
use App\Services\ContentRunWorkerLauncher;
use App\Services\EditorialPlanBuilder;
use App\Services\EditorialPlanWorkerLauncher;
use App\Services\GeminiContentGenerator;
use App\Services\CompetitorPricingUrlParser;
use App\Services\SemrushCsvImporter;
use App\Services\SourceCrawlWorkerLauncher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.admin')]
class Automation extends Component
{
    use WithBulkSelection, WithFileUploads;

    public string $mode = 'new';

    public ?int $existingProjectId = null;

    public ?int $projectId = null;

    public string $name = '';

    public string $websiteUrl = '';

    public string $pricingUrl = '';

    public string $affiliateUrl = '';

    public string $country = 'FR';

    public string $currency = 'EUR';

    public string $extraSourceUrls = '';

    public string $competitorsText = '';

    public string $competitorPricingUrlsText = '';

    public string $apiKey = '';

    public $csv;

    public string $pastedKeywords = '';

    public int $contentCount = 5;

    public ?int $publicationDays = null;

    public string $instructions = "Appliquer strictement la stratégie éditoriale BusinessKit :\n1. Pages business / conversion (forte intention)\n2. Pages informationnelles / autorité (guides experts)\n3. Pages comparatives / affiliation\n4. Pages outils / trafic récurrent\nGarantir une expertise de haut niveau (auteur: Créateur de BusinessKit).";

    public ?int $activeRunId = null;

    public ?int $activePlanId = null;

    public bool $workspaceReady = false;

    public bool $sourcesCollecting = false;

    public string $message = '';

    public string $error = '';

    public array $crawlErrors = [];

    public function mount(): void
    {
        $active = ContentRun::query()
            ->where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'processing'])
            ->latest('id')
            ->first();
        if ($active) {
            $this->activeRunId = $active->id;
            $this->activePlanId = $active->editorial_plan_id;
            $this->projectId = $active->seo_project_id;
            $this->existingProjectId = $active->seo_project_id;
            $this->competitorsText = implode("\n", $active->project?->competitors ?? []);
            $this->competitorPricingUrlsText = app(CompetitorPricingUrlParser::class)->format($active->project?->competitor_pricing_urls ?? []);
            $this->mode = 'existing';
            $this->workspaceReady = true;

            return;
        }

        // Recover runs interrupted in the last 24 hours (paused or partially failed)
        $interrupted = ContentRun::query()
            ->where('user_id', auth()->id())
            ->whereIn('status', ['paused', 'completed_with_errors'])
            ->where('updated_at', '>=', now()->subDay())
            ->whereHas('items', fn ($q) => $q->where('status', 'pending'))
            ->latest('id')
            ->first();
        if ($interrupted) {
            $this->activeRunId = $interrupted->id;
            $this->activePlanId = $interrupted->editorial_plan_id;
            $this->projectId = $interrupted->seo_project_id;
            $this->existingProjectId = $interrupted->seo_project_id;
            $this->competitorsText = implode("\n", $interrupted->project?->competitors ?? []);
            $this->competitorPricingUrlsText = app(CompetitorPricingUrlParser::class)->format($interrupted->project?->competitor_pricing_urls ?? []);
            $this->mode = 'existing';
            $this->workspaceReady = true;

            return;
        }

        $plan = EditorialPlan::query()->where('user_id', auth()->id())->whereIn('status', ['planning', 'locked', 'failed'])->whereDoesntHave('runs')->latest('id')->first();
        if ($plan) {
            $this->activePlanId = $plan->id;
            $this->projectId = $plan->seo_project_id;
            $this->existingProjectId = $plan->seo_project_id;
            $this->competitorsText = implode("\n", $plan->project?->competitors ?? []);
            $this->competitorPricingUrlsText = app(CompetitorPricingUrlParser::class)->format($plan->project?->competitor_pricing_urls ?? []);
            $this->contentCount = $plan->requested_count;
            $this->mode = 'existing';
            $this->workspaceReady = true;

            return;
        }

        $project = SeoProject::query()
            ->whereHas('keywords')
            ->whereHas('sourcePages', fn ($query) => $query->where('status', 'verified'))
            ->latest('id')
            ->first();
        if ($project) {
            $this->mode = 'existing';
            $this->existingProjectId = $project->id;
            $this->projectId = $project->id;
            $this->competitorsText = implode("\n", $project->competitors ?? []);
            $this->competitorPricingUrlsText = app(CompetitorPricingUrlParser::class)->format($project->competitor_pricing_urls ?? []);
            $this->workspaceReady = true;
        }
    }

    public function updatedMode(): void
    {
        $this->workspaceReady = false;
        $this->projectId = null;
        $this->activePlanId = null;
    }

    public function updatedExistingProjectId($value): void
    {
        $project = $value ? SeoProject::query()->find($value) : null;
        $this->projectId = $project?->id;
        $this->workspaceReady = $project ? $this->projectReady($project) : false;
        $this->competitorsText = $project ? implode("\n", $project->competitors ?? []) : '';
        $this->competitorPricingUrlsText = $project ? app(CompetitorPricingUrlParser::class)->format($project->competitor_pricing_urls ?? []) : '';
        $this->activePlanId = null;
        $this->message = $this->workspaceReady ? "Le dossier {$project->name} est prêt à générer." : '';
    }

    public function prepare(SourceCrawlWorkerLauncher $worker, SemrushCsvImporter $importer, CompetitorPricingUrlParser $competitorPricingUrls): void
    {
        $this->allowLongRequest(180);

        $rules = [
            'mode' => ['required', 'in:new,existing'],
            'apiKey' => ['nullable', 'string', 'min:20', 'max:500'],
            'csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:10240'],
            'pastedKeywords' => ['nullable', 'string', 'max:1500000'],
            'extraSourceUrls' => ['nullable', 'string', 'max:10000'],
            'competitorsText' => ['nullable', 'string', 'max:5000'],
            'competitorPricingUrlsText' => ['nullable', 'string', 'max:10000'],
        ];
        if ($this->mode === 'new') {
            $rules += [
                'name' => ['required', 'string', 'max:120'],
                'websiteUrl' => ['required', 'url', 'max:2000'],
                'pricingUrl' => ['nullable', 'url', 'max:2000'],
                'affiliateUrl' => ['nullable', 'url', 'max:2000'],
                'country' => ['required', 'string', 'size:2'],
                'currency' => ['required', 'string', 'size:3'],
            ];
        } else {
            $rules['existingProjectId'] = ['required', 'exists:seo_projects,id'];
        }
        $rules['publicationDays'] = ['nullable', 'integer', 'min:1', 'max:365'];
        $this->validate($rules, [], [
            'mode' => 'mode',
            'apiKey' => 'clé Gemini',
            'csv' => 'fichier de mots-clés',
            'pastedKeywords' => 'mots-clés collés',
            'extraSourceUrls' => 'autres pages officielles',
            'competitorsText' => 'concurrents reels',
            'competitorPricingUrlsText' => 'pages tarifs concurrents',
            'name' => 'nom de l’outil',
            'websiteUrl' => 'site officiel',
            'pricingUrl' => 'page tarifs',
            'affiliateUrl' => 'lien affilié',
            'country' => 'pays',
            'currency' => 'devise',
            'existingProjectId' => 'projet existant',
            'publicationDays' => 'publication étalée sur (jours)',
        ]);

        $this->message = '';
        $this->error = '';
        $this->crawlErrors = [];
        $this->workspaceReady = false;

        if ($this->apiKey !== '') {
            Setting::put('gemini_api_key', trim($this->apiKey), true);
            $this->apiKey = '';
        }

        $competitorConfig = $competitorPricingUrls->parse($this->competitorsText, $this->competitorPricingUrlsText);

        $project = $this->mode === 'existing'
            ? SeoProject::query()->findOrFail($this->existingProjectId)
            : SeoProject::query()->create([
                'name' => $this->name,
                'slug' => $this->uniqueProjectSlug($this->name),
                'website_url' => $this->websiteUrl,
                'pricing_url' => $this->pricingUrl ?: null,
                'affiliate_url' => $this->affiliateUrl ?: null,
                'country' => strtoupper($this->country),
                'currency' => strtoupper($this->currency),
                'competitors' => $competitorConfig['competitors'],
                'competitor_pricing_urls' => $competitorConfig['pricing_urls'],
                'crawl_status' => 'processing',
            ]);
        $this->projectId = $project->id;

        if ($this->mode === 'existing') {
            $project->update([
                'competitors' => $competitorConfig['competitors'] ?: ($project->competitors ?? []),
                'competitor_pricing_urls' => $competitorConfig['pricing_urls'],
            ]);
        }
        app(\App\Services\AffiliateSeoDefaults::class)->ensureForProject($project);

        if ($this->csv) {
            try {
                $importer->import($project, $this->csv->getRealPath());
            } catch (Throwable $exception) {
                $this->error = 'Import des mots-clés : '.$exception->getMessage();

                return;
            }
        }
        if (trim($this->pastedKeywords) !== '') {
            try {
                $importer->importText($project, $this->pastedKeywords);
            } catch (Throwable $exception) {
                $this->error = 'Import des mots-clés collés : '.$exception->getMessage();

                return;
            }
        }

        if ($project->keywords()->count() === 0) {
            $this->error = 'Ajoutez au moins un mot-clé avec un fichier Semrush/Google Keyword Planner ou en collant le tableau Semrush.';

            return;
        }

        $urls = collect([
            ['url' => $project->website_url, 'type' => 'homepage', 'competitor_name' => null],
            ['url' => $project->pricing_url, 'type' => 'pricing', 'competitor_name' => null],
        ])->filter(fn ($source) => $source['url']);
        foreach (($project->competitor_pricing_urls ?? []) as $competitorName => $url) {
            if (trim((string) $url) !== '') {
                $urls->push(['url' => trim((string) $url), 'type' => 'pricing', 'competitor_name' => trim((string) $competitorName)]);
            }
        }
        foreach (array_slice(preg_split('/\R/', $this->extraSourceUrls) ?: [], 0, 4) as $url) {
            if (trim($url) !== '') {
                $urls->push(['url' => trim($url), 'type' => 'other', 'competitor_name' => null]);
            }
        }

        $queued = 0;
        foreach ($urls->unique('url')->take(12) as $sourceData) {
            try {
                $source = SourcePage::query()->updateOrCreate(
                    ['seo_project_id' => $project->id, 'url' => $sourceData['url']],
                    ['type' => $sourceData['type'], 'competitor_name' => $sourceData['competitor_name'] ?? null, 'status' => 'processing', 'error_message' => null],
                );
                if (! app()->runningUnitTests()) {
                    $worker->launch($source->id);
                }
                $queued++;
            } catch (Throwable $exception) {
                $this->crawlErrors[] = $sourceData['url'].' — '.$exception->getMessage();
            }
        }

        if ($queued === 0) {
            $this->error = 'Aucune source n’a pu être mise en file.';

            return;
        }

        $project->update(['crawl_status' => 'processing']);
        $this->reset('csv', 'pastedKeywords');
        $this->sourcesCollecting = true;
        $this->message = "{$queued} source(s) en cours de collecte en arrière-plan. La planification se déverrouillera automatiquement.";
    }

    public function refreshPreparation(): void
    {
        if (! $this->sourcesCollecting || ! $this->projectId) {
            return;
        }

        $project = SeoProject::query()->find($this->projectId);
        if (! $project) {
            $this->sourcesCollecting = false;

            return;
        }

        $project->sourcePages()
            ->where('status', 'processing')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->update(['status' => 'failed', 'error_message' => 'Le worker de collecte n’a pas terminé dans le délai de sécurité.']);
        $remaining = $project->sourcePages()->where('status', 'processing')->count();
        if ($remaining > 0) {
            $verified = $project->sourcePages()->where('status', 'verified')->count();
            $this->message = "Collecte en cours : {$verified} source(s) terminée(s), {$remaining} restante(s).";

            return;
        }

        $this->sourcesCollecting = false;
        $this->crawlErrors = $project->sourcePages()
            ->where('status', 'failed')
            ->get()
            ->map(fn (SourcePage $source) => $source->url.' — '.($source->error_message ?: 'échec sans détail'))
            ->all();
        $verified = $project->sourcePages()->where('status', 'verified')->count();
        if ($verified === 0) {
            $project->update(['crawl_status' => 'failed']);
            $this->error = 'Aucune source vérifiée n’a pu être collectée. Consultez les erreurs ci-dessous.';
            $this->message = '';

            return;
        }

        $project->update(['crawl_status' => 'completed', 'last_crawled_at' => now()]);
        $this->workspaceReady = true;
        $this->error = '';
        $this->message = "Dossier prêt : {$verified} sources et {$project->keywords()->count()} mots-clés disponibles.";
    }

    public function startRun(EditorialPlanBuilder $planner, EditorialPlanWorkerLauncher $planningWorker): void
    {
        $this->validate([
            'projectId' => ['required', 'exists:seo_projects,id'],
            'contentCount' => ['required', 'integer', 'min:1', 'max:30'],
            'instructions' => ['nullable', 'string', 'max:3000'],
        ]);
        $active = ContentRun::query()->where('user_id', auth()->id())->whereIn('status', ['pending', 'processing'])->latest('id')->first();
        if ($active) {
            $this->activeRunId = $active->id;
            $this->activePlanId = $active->editorial_plan_id;
            $this->projectId = $active->seo_project_id;
            $this->workspaceReady = true;
            $this->error = 'Une campagne est déjà en cours. Reprenez-la avant d’en créer une nouvelle.';

            return;
        }
        if (! Setting::value('gemini_api_key', config('services.gemini.key'))) {
            $this->error = 'Ajoutez une clé API Gemini avant de lancer la génération.';

            return;
        }

        $project = SeoProject::query()->findOrFail($this->projectId);
        if ($project->sourcePages()->where('status', 'verified')->doesntExist()) {
            $this->error = 'Le projet ne possède aucune source vérifiée.';

            return;
        }

        $plan = $planner->createPlan(
            $project, 
            auth()->id(), 
            $this->contentCount, 
            $this->instructions
        );

        $this->activePlanId = $plan->id;
        $this->activeRunId = null;
        $this->error = '';
        $this->message = 'Planification démarrée. Les premières propositions vont apparaître progressivement.';
        if (! app()->runningUnitTests()) {
            try {
                $planningWorker->launch($plan->id);
            } catch (Throwable $exception) {
                report($exception);
                $plan->update(['status' => 'failed']);
                $this->error = $exception->getMessage();
                $this->message = '';
            }
        }
        $this->dispatch('planning-started');
    }

    public function lockFailedPlan(EditorialPlanBuilder $planner): void
    {
        $plan = EditorialPlan::query()->where('user_id', auth()->id())->find($this->activePlanId);
        if (! $plan || $plan->status !== 'failed') {
            return;
        }

        $valid = $plan->ideas()->where('status', 'candidate')->get();
        if ($valid->count() > 0) {
            $plan->update(['requested_count' => $valid->count()]);
            // Use reflection or update the plan directly since lockPlan is private
            $priorityMap = [
                'Level 1 - Pillar' => 1,
                'Level 2 - Commercial' => 2,
                'Level 5 - Comparatifs' => 2,
                'Level 6 - Alternatives' => 2,
                'Level 3 - Long Tail' => 3,
                'Level 4 - FAQ' => 4,
                'Level 7 - Tutoriels' => 5,
            ];

            $ranked = $valid->unique('id')->sortBy(function ($idea) use ($priorityMap) {
                $level = $idea->roadmap_level ?? '';
                $priority = $priorityMap[$level] ?? 99;
                return [$priority, -$idea->seo_score];
            })->values();

            $plan->ideas()->whereIn('status', ['candidate', 'accepted', 'reserve'])->update([
                'status' => 'reserve',
                'position' => null,
            ]);
            foreach ($ranked as $index => $idea) {
                $idea->update([
                    'status' => $index < $plan->requested_count ? 'accepted' : 'reserve',
                    'position' => $index + 1,
                ]);
            }
            $plan->update([
                'accepted_count' => $plan->ideas()->where('status', 'accepted')->count(),
                'status' => 'locked',
                'locked_at' => now(),
            ]);
            $this->error = '';
            $this->message = 'Le plan a été validé avec les idées trouvées.';
        }
    }

    public function processPlanningStep(EditorialPlanBuilder $planner, EditorialPlanWorkerLauncher $planningWorker): void
    {
        $plan = $this->activePlanId
            ? EditorialPlan::query()->where('user_id', auth()->id())->find($this->activePlanId)
            : null;

        if (! $plan || $plan->status !== 'planning') {
            $this->dispatch('planning-finished');

            return;
        }

        // En HTTP, aucune requête Gemini ne doit être exécutée par PHP-FPM :
        // Nginx coupe les réponses après 60 secondes. Le polling ne fait que
        // lire l'état ; il redémarre un worker uniquement si celui-ci paraît
        // réellement abandonné.
        if (! app()->runningUnitTests()) {
            if ($plan->updated_at->lt(now()->subMinute())) {
                try {
                    $planningWorker->launch($plan->id);
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error = $exception->getMessage();
                }
            }
            $maxAttempts = $plan->requested_count >= 20 ? 15 : ($plan->requested_count >= 10 ? 10 : 6);
            $this->message = "Planification en arrière-plan : {$plan->candidate_count} idées analysées, étape {$plan->attempts}/{$maxAttempts}.";
            $this->dispatch('planning-step-finished');

            return;
        }

        $this->allowLongRequest(180);

        try {
            $plan = $planner->advance($plan);
        } catch (Throwable $exception) {
            $plan->refresh();
            if ($plan->status === 'planning' && $this->isRecoverablePlanningError($exception)) {
                $this->error = '';
                $reason = $this->isCapacityError($exception)
                    ? 'Gemini Flash-Lite est momentanément saturé'
                    : 'Gemini n’a pas répondu dans le délai prévu';
                $this->message = "{$reason}. Nouvelle tentative automatique dans 5 secondes ; la progression est conservée.";
                $this->dispatch('planning-retry-later', delay: 5000);

                return;
            }

            report($exception);
            if ($plan->status === 'planning') {
                $plan->update(['status' => 'failed']);
            }
            $this->error = 'Planification impossible : '.$exception->getMessage();
            $this->message = '';
            $this->dispatch('planning-finished');

            return;
        }

        $this->error = '';
        if ($plan->isReady()) {
            $this->message = "Plan verrouillé : {$plan->accepted_count} angles uniques retenus sur {$plan->candidate_count} idées analysées. Vérifiez le plan puis lancez la rédaction.";
            $this->dispatch('planning-finished');

            return;
        }

        if ($plan->status === 'failed') {
            $this->error = "La planification n’a trouvé que {$plan->ideas()->where('status', 'candidate')->count()} angles uniques sur {$plan->requested_count}.";
            $this->message = '';
            $this->dispatch('planning-finished');

            return;
        }

        $candidateCount = $plan->ideas()->where('status', 'candidate')->count();
        $maxAttempts = $plan->requested_count >= 20 ? 15 : ($plan->requested_count >= 10 ? 10 : 6);
        $this->message = "Planification en cours : {$candidateCount}/{$plan->requested_count} angles retenus après l’étape {$plan->attempts}/{$maxAttempts}.";
        $this->dispatch('planning-step-finished');
    }

    public function cancelPlan(): void
    {
        if (! $this->activePlanId) {
            return;
        }

        $plan = EditorialPlan::query()->where('user_id', auth()->id())->find($this->activePlanId);
        if (! $plan || $plan->status === 'generating' || $plan->runs()->exists()) {
            $this->error = 'Impossible d’annuler ce plan car la production a déjà commencé.';
            return;
        }

        DB::transaction(function () use ($plan) {
            $plan->ideas()->delete();
            $plan->delete();
        });

        $this->activePlanId = null;
        $this->error = '';
        $this->message = 'Le plan éditorial a été annulé et les angles proposés ont été supprimés. Vous pouvez relancer une nouvelle planification.';
        $this->dispatch('planning-finished');
    }

    public function launchRun(ContentRunWorkerLauncher $worker): void
    {
        $plan = $this->activePlanId ? EditorialPlan::query()->with('project')->find($this->activePlanId) : null;
        if (! $plan || ! $plan->isReady()) {
            $this->error = 'Le bouton de rédaction reste verrouillé tant que le plan ne contient pas exactement le nombre d’idées demandé.';

            return;
        }
        if ($plan->runs()->exists()) {
            $this->error = 'Ce plan éditorial a déjà été lancé.';

            return;
        }

        $ideas = $plan->ideas()
            ->where('status', 'accepted')
            ->orderBy('position')
            ->orderByDesc('seo_score')
            ->limit($plan->requested_count)
            ->get();
        if ($ideas->count() !== $plan->requested_count) {
            $this->error = "Le plan contient {$ideas->count()} briefs exploitables sur {$plan->requested_count} attendus.";

            return;
        }

        $extraAcceptedIds = $plan->ideas()->where('status', 'accepted')->whereNotIn('id', $ideas->pluck('id'))->pluck('id');
        if ($extraAcceptedIds->isNotEmpty()) {
            $plan->ideas()->whereIn('id', $extraAcceptedIds)->update(['status' => 'reserve', 'position' => null]);
        }

        $run = DB::transaction(function () use ($plan, $ideas): ContentRun {
            $run = ContentRun::query()->create([
                'seo_project_id' => $plan->seo_project_id,
                'user_id' => auth()->id(),
                'editorial_plan_id' => $plan->id,
                'name' => 'Campagne '.$plan->project->name.' — '.now()->format('d/m/Y H:i'),
                'requested_count' => $plan->requested_count,
                'status' => 'pending',
                'instructions' => $plan->instructions,
                'publication_days' => $this->publicationDays,
            ]);
            foreach ($ideas as $idea) {
                $run->items()->create([
                    'editorial_idea_id' => $idea->id,
                    'keyword_id' => $idea->keyword_id,
                    'content_type' => $idea->content_type,
                    'status' => 'pending',
                ]);
            }
            $plan->update(['status' => 'generating']);

            return $run;
        });

        $this->activeRunId = $run->id;
        $this->activePlanId = $plan->id;
        $this->error = '';
        Cache::forget("content-run-worker:{$run->id}");
        $this->launchWorker($run, $worker);
        $this->message = "Génération automatique lancée pour {$run->requested_count} contenus.";
        $this->dispatch('batch-started');
    }

    public function resumeRun(ContentRunWorkerLauncher $worker): void
    {
        if (! $this->activeRunId) {
            return;
        }

        $run = ContentRun::query()->find($this->activeRunId);
        if (! $run || ! in_array($run->status, ['pending', 'processing', 'paused'], true)) {
            return;
        }

        $freshProcessing = $run->items()
            ->where('status', 'processing')
            ->where('updated_at', '>=', now()->subMinutes(3))
            ->exists();
        if ($freshProcessing) {
            $this->message = 'Une génération est encore en cours. Patientez quelques instants avant de reprendre.';
            $this->dispatch('batch-recheck');

            return;
        }

        $this->recoverInterruptedItems($run);
        $run->update(['status' => 'pending']);
        Cache::forget("content-run-worker:{$run->id}");
        $this->launchWorker($run, $worker);
        $this->message = 'Génération automatique reprise au premier contenu interrompu.';
        $this->dispatch('batch-started');
    }

    public function setPreset(string $type): void
    {
        $diversity = "\n\nREGLE ABSOLUE : Tu dois OBLIGATOIREMENT diversifier les sujets. Interdiction totale de proposer plus d'UNE seule idée sur le même sous-sujet (ex: si tu proposes un article sur la signature électronique, les autres doivent porter sur la comptabilité, la facturation, etc.).";
        
        if ($type === 'pillar') {
            $this->instructions = "Objectif : Construire les Pages Mères (Guides complets) pour asseoir l'autorité topique. \nCONTRAINTE STRICTE : Tu DOIS utiliser la valeur 'informational' pour le champ content_type. Ne crée JAMAIS de comparatif ou de liste d'outils ici. L'objectif est d'éduquer en profondeur sur des sujets métiers très distincts (la TVA, le statut juridique, les notes de frais, etc.) pour éviter les sujets génériques similaires." . $diversity;
        } elseif ($type === 'money') {
            $this->instructions = "Objectif : Money Pages / Conversion. Requêtes ultra-spécifiques et transactionnelles. Inclure systématiquement des tableaux de prix, la mention d'un simulateur, et des appels à l'action (CTA) très agressifs pour générer des commissions." . $diversity;
        } elseif ($type === 'interception') {
            $this->instructions = "Objectif : Trafic de Masse & Interception. Ce sont des requêtes navigationnelles. L'angle de l'article doit être l'interception de trafic (ex: faire un avis détaillé, proposer des alternatives meilleures ou moins chères, décrypter l'offre). Le ton doit être analytique et comparatif." . $diversity;
        }
    }

    public function stopRun(): void
    {
        $run = $this->activeRunId
            ? ContentRun::query()->where('user_id', auth()->id())->with(['items.editorialIdea', 'editorialPlan'])->find($this->activeRunId)
            : null;

        if (! $run || ! in_array($run->status, ['pending', 'processing'], true)) {
            $this->dispatch('batch-stopped');

            return;
        }

        DB::transaction(function () use ($run): void {
            $items = $run->items()->whereIn('status', ['pending', 'processing'])->with('editorialIdea')->get();
            foreach ($items as $item) {
                $item->update([
                    'status' => 'failed',
                    'error_message' => 'Campagne arrêtée manuellement. Les parties déjà produites sont conservées.',
                    'started_at' => null,
                    'completed_at' => now(),
                ]);
                if ($item->editorialIdea?->status === 'generating') {
                    $item->editorialIdea->update(['status' => 'accepted']);
                }
            }

            $run->update([
                'status' => 'completed_with_errors',
                'failed_count' => $run->items()->where('status', 'failed')->count(),
                'completed_at' => now(),
            ]);
            if ($run->editorialPlan && $run->editorialPlan->status === 'generating') {
                $run->editorialPlan->update(['status' => 'locked']);
            }
        });

        $this->error = '';
        $this->message = 'Campagne arrêtée. Les briefs et les parties déjà générées sont conservés ; vous pourrez reprendre avec « Réessayer ».';
        $this->dispatch('batch-stopped');
    }

    public function resumePausedRun(int $runId, ContentRunWorkerLauncher $worker): void
    {
        $run = ContentRun::query()
            ->where('user_id', auth()->id())
            ->where('status', 'paused')
            ->findOrFail($runId);

        $run->items()->where('status', 'pending')->update([
            'error_message' => null,
            'started_at' => null,
        ]);
        $run->update(['status' => 'pending']);

        $this->activeRunId = $run->id;
        $this->activePlanId = $run->editorial_plan_id;
        $this->projectId = $run->seo_project_id;
        $this->existingProjectId = $run->seo_project_id;
        $this->mode = 'existing';
        $this->workspaceReady = true;
        $this->error = '';
        $this->launchWorker($run, $worker);
        $this->message = 'Génération automatique reprise. Les parties déjà enregistrées sont conservées.';
        $this->dispatch('batch-started');
    }

    public function retryFailedRun(int $runId, ContentRunWorkerLauncher $worker): void
    {
        $run = ContentRun::query()
            ->where('user_id', auth()->id())
            ->findOrFail($runId);

        $failedCount = $run->items()->where('status', 'failed')->count();
        if ($failedCount === 0) {
            $this->message = 'Cette campagne ne contient aucun contenu en échec.';

            return;
        }

        $otherActiveRun = ContentRun::query()
            ->where('user_id', auth()->id())
            ->whereKeyNot($run->id)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();
        if ($otherActiveRun) {
            $this->error = 'Une autre campagne est déjà en cours. Terminez-la avant de relancer ces contenus.';

            return;
        }

        DB::transaction(function () use ($run): void {
            $run->items()->where('status', 'failed')->update([
                'status' => 'pending',
                'error_message' => null,
                'api_attempts' => 0,
                'started_at' => null,
                'completed_at' => null,
            ]);
            $run->update([
                'status' => 'pending',
                'completed_count' => $run->items()->where('status', 'completed')->count(),
                'failed_count' => 0,
                'completed_at' => null,
            ]);
        });

        $this->activeRunId = $run->id;
        $this->activePlanId = $run->editorial_plan_id;
        $this->projectId = $run->seo_project_id;
        $this->existingProjectId = $run->seo_project_id;
        $this->mode = 'existing';
        $this->workspaceReady = true;
        $this->error = '';
        $this->launchWorker($run, $worker);
        $this->message = "Génération automatique relancée pour {$failedCount} contenu(s).";
        $this->dispatch('batch-started');
    }

    public function processNext(GeminiContentGenerator $generator, EditorialPlanBuilder $planner, ContentRunProcessor $processor, ContentRunWorkerLauncher $worker): void
    {
        $run = $this->activeRunId ? ContentRun::query()->find($this->activeRunId) : null;
        if (! $run || ! in_array($run->status, ['pending', 'processing'], true)) {
            $this->dispatch('batch-item-finished', remaining: 0);

            return;
        }

        // Les tests exécutent volontairement l'étape dans le même processus.
        // En HTTP, un worker CLI autonome prend toute la campagne en charge.
        // Nginx reçoit immédiatement la réponse Livewire et le traitement ne
        // dépend plus du navigateur, du polling ou d'un processus PHP-FPM.
        if (app()->runningUnitTests()) {
            $this->applyProcessingResult($processor->process($run->id));

            return;
        }

        $freshProcessing = $run->items()
            ->where('status', 'processing')
            ->where('updated_at', '>=', now()->subMinutes(3))
            ->exists();
        if ($freshProcessing) {
            $this->message = 'Génération en cours.';
            $this->dispatch('batch-recheck');

            return;
        }

        try {
            $worker->launch($run->id);
            $this->error = '';
            $this->message = 'Génération démarrée.';
            $this->dispatch('batch-recheck');
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception->getMessage();
            $this->message = '';
            $this->dispatch('batch-stopped');
        }

        return;

        $this->allowLongRequest(420);

        $run = $this->activeRunId ? ContentRun::query()->with('project')->find($this->activeRunId) : null;
        if (! $run || ! in_array($run->status, ['pending', 'processing'], true)) {
            $this->dispatch('batch-item-finished', remaining: 0);

            return;
        }

        // A browser refresh or a lost Livewire response can leave an item marked
        // as processing even though no request is running anymore. The UI polls
        // this method every five seconds; recover such an item after the same
        // safety window used by resumeRun(), while preserving its saved parts.
        $this->recoverInterruptedItems($run, 3);

        if ($run->items()->where('status', 'processing')->exists()) {
            $this->message = 'Une génération est encore en cours.';
            $this->dispatch('batch-recheck');

            return;
        }

        $item = $run->items()->where('status', 'pending')->orderBy('id')->first();
        if (! $item) {
            $this->completeRun($run);

            return;
        }

        $run->update(['status' => 'processing', 'started_at' => $run->started_at ?? now()]);
        $item->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $idea = $item->editorialIdea;
            if ($idea) {
                if ($item->generation_step === 0) {
                    $idea->increment('generation_attempts');
                }
                $idea->update(['status' => 'generating']);
                $parts = $item->generation_parts ?? [];
                $step = (int) $item->generation_step;
                $partCount = $generator->partCount(
                    $item->content_type,
                    $idea->title,
                    $idea->intent,
                    $idea->funnel_stage,
                    $idea->primary_keyword,
                    array_values($parts),
                );

                if ($step < $partCount) {
                    $parts[$step] = $generator->generatePartFromIdea(
                        $run->project,
                        $idea,
                        (string) $run->instructions,
                        $step,
                        (int) $item->api_attempts,
                        array_values($parts),
                    );
                    $nextStep = $step + 1;
                    $item->update([
                        'generation_parts' => array_values($parts),
                        'generation_step' => $nextStep,
                        'api_attempts' => 0,
                        'error_message' => null,
                    ]);

                    if ($nextStep < $partCount) {
                        $item->update(['status' => 'pending']);
                        $this->message = "« {$idea->title} » : partie {$nextStep}/{$partCount} enregistrée.";
                        $this->dispatch('batch-item-finished', remaining: $run->items()->where('status', 'pending')->count());

                        return;
                    }
                }

                $article = $generator->finalizeFromIdeaParts($run->project, $idea, (string) $run->instructions, $parts);
            } else {
                // Compatibilité des campagnes créées avant l'introduction des plans éditoriaux.
                $article = $generator->generate($run->project, $item->content_type, $item->keyword, (string) $run->instructions);
            }
            $item->update(['article_id' => $article->id, 'status' => 'completed', 'completed_at' => now()]);
            $idea?->update(['status' => 'generated']);
            $run->increment('completed_count');
        } catch (DuplicateContentException|PlannedContentRejectedException $exception) {
            $item->update([
                'status' => 'rejected',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);
            try {
                if (! $run->editorialPlan || ! $item->editorialIdea) {
                    $item->update(['status' => 'skipped']);
                    throw new \LogicException('Ancienne campagne : doublon écarté sans remplacement planifié.');
                }
                $replacement = $planner->replacementFor($run->editorialPlan, $item->editorialIdea);
                $run->items()->create([
                    'editorial_idea_id' => $replacement->id,
                    'keyword_id' => $replacement->keyword_id,
                    'content_type' => $replacement->content_type,
                    'status' => 'pending',
                ]);
                $this->message = "Un angle réellement incompatible ou déjà couvert a été écarté ; « {$replacement->title} » le remplace automatiquement. Un simple défaut de mise en forme ne déclenche plus de remplacement.";
            } catch (\LogicException) {
                // Les anciennes campagnes n’avaient pas de réserve éditoriale.
            } catch (Throwable $replacementError) {
                $item->update(['status' => 'failed', 'error_message' => mb_substr($exception->getMessage().' '.$replacementError->getMessage(), 0, 2000)]);
                $run->increment('failed_count');
            }
        } catch (Throwable $exception) {
            if ($this->isRecoverableGenerationError($exception)) {
                $apiAttempts = min(255, (int) $item->api_attempts + 1);
                $item->update([
                    'status' => 'pending',
                    'api_attempts' => $apiAttempts,
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'started_at' => null,
                ]);
                $run->update(['status' => 'pending']);
                $reason = match (true) {
                    $this->isCapacityError($exception) && $apiAttempts > 3 => 'Gemini Flash de secours est également saturé',
                    $this->isCapacityError($exception) && $apiAttempts === 3 => 'Gemini Flash-Lite est saturé ; le secours Gemini Flash sera utilisé au prochain essai',
                    $this->isCapacityError($exception) => 'Gemini Flash-Lite est saturé',
                    $this->isTimeoutError($exception) => 'Gemini n’a pas répondu à temps',
                    default => 'La partie reçue est incomplète',
                };
                $this->message = "{$reason} (tentative {$apiAttempts}). Nouvelle rédaction automatique dans 5 secondes, sans limite et sans perte de contenu.";
                $this->dispatch('batch-retry-later', delay: 5000);

                return;
            }

            report($exception);
            $this->stopRunAfterTechnicalError($run, $item, $exception);

            return;
        }

        $remaining = $run->items()->where('status', 'pending')->count();
        if ($remaining === 0) {
            $this->completeRun($run->fresh());
        } else {
            $this->dispatch('batch-item-finished', remaining: $remaining);
        }
    }

    /** @param array{state:string,message:string,error:string,remaining:int,attempt?:int} $result */
    private function applyProcessingResult(array $result): void
    {
        $this->message = $result['message'];
        $this->error = $result['error'];

        match ($result['state']) {
            'retry' => $this->dispatch('batch-retry-later', delay: 5000),
            'stopped' => $this->dispatch('batch-stopped'),
            'busy' => $this->dispatch('batch-recheck'),
            default => $this->dispatch('batch-item-finished', remaining: $result['remaining']),
        };
    }

    private function launchWorker(ContentRun $run, ContentRunWorkerLauncher $worker): void
    {
        if (! app()->runningUnitTests()) {
            $worker->launch($run->id);
        }
    }

    public function deleteSelected(): void
    {
        $ids = array_intersect($this->normalizedSelectedIds(), $this->bulkSelectionIds());
        $deletingActiveRun = $this->activeRunId && in_array($this->activeRunId, $ids, true);
        $count = ContentRun::query()->whereIn('id', $ids)->delete();
        if ($deletingActiveRun) {
            $this->activeRunId = null;
        }
        $this->resetBulkSelection();
        $this->message = "{$count} campagne(s) supprimée(s). Les articles déjà produits sont conservés.";
    }

    protected function bulkSelectionIds(): array
    {
        return ContentRun::query()->latest()->limit(6)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function completeRun(ContentRun $run): void
    {
        $skippedCount = $run->items()->whereIn('status', ['skipped', 'rejected'])->count();
        $status = match (true) {
            $run->failed_count > 0 => 'completed_with_errors',
            $run->completed_count >= $run->requested_count => 'completed',
            $skippedCount > 0 => 'completed_with_warnings',
            default => 'completed',
        };
        $run->update([
            'status' => $status,
            'completed_at' => now(),
        ]);
        if ($run->editorialPlan && ! $run->editorialPlan->content_schedule_id && $run->completed_count >= $run->requested_count) {
            $run->editorialPlan->update(['status' => 'completed']);
        }
        $this->message = "Campagne terminée : {$run->completed_count} contenus validés, {$skippedCount} brouillons remplacés, {$run->failed_count} échecs techniques.";
        $this->dispatch('batch-item-finished', remaining: 0);
    }

    private function recoverInterruptedItems(ContentRun $run, int $minimumAgeMinutes = 0): void
    {
        $query = $run->items()->where('status', 'processing');
        if ($minimumAgeMinutes > 0) {
            $query->where('updated_at', '<', now()->subMinutes($minimumAgeMinutes));
        }

        $items = $query->with('editorialIdea')->get();
        foreach ($items as $item) {
            $item->update(['status' => 'pending', 'started_at' => null]);
            if ($item->editorialIdea?->status === 'generating') {
                $item->editorialIdea->update(['status' => 'accepted']);
            }
        }
    }

    private function isCapacityError(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'high demand')
            || str_contains($message, 'high traffic')
            || str_contains($message, 'too many requests')
            || str_contains($message, 'resource exhausted')
            || str_contains($message, 'rate limit')
            || preg_match('/(?:gemini\s+)?http\s+(?:429|503)\b/u', $message) === 1
            || preg_match('/statut http (?:429|503)\b/u', $message) === 1;
    }

    private function isRecoverableGenerationError(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return $this->isCapacityError($exception)
            || $this->isTimeoutError($exception)
            || str_contains($message, 'réponse gemini incomplète')
            || str_contains($message, 'contenu structuré exploitable');
    }

    private function isRecoverablePlanningError(Throwable $exception): bool
    {
        return $this->isCapacityError($exception) || $this->isTimeoutError($exception);
    }

    private function isTimeoutError(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return $exception instanceof ConnectionException
            || str_contains($message, 'curl error 28')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'délai d’attente')
            || str_contains($message, 'limite de sortie')
            || str_contains($message, 'structuré')
            || str_contains($message, 'gemini http')
            || str_contains($message, 'connection reset');
    }

    private function stopRunAfterTechnicalError(ContentRun $run, $failedItem, Throwable $exception): void
    {
        DB::transaction(function () use ($run, $failedItem, $exception): void {
            $items = $run->items()->whereIn('status', ['pending', 'processing'])->with('editorialIdea')->get();
            foreach ($items as $item) {
                $message = $item->is($failedItem)
                    ? $exception->getMessage()
                    : 'Campagne stoppée automatiquement après une erreur technique sur un autre contenu.';
                $item->update([
                    'status' => 'failed',
                    'error_message' => mb_substr($message, 0, 2000),
                    'started_at' => null,
                    'completed_at' => now(),
                ]);
                if ($item->editorialIdea?->status === 'generating') {
                    $item->editorialIdea->update(['status' => 'accepted']);
                }
            }

            $run->update([
                'status' => 'completed_with_errors',
                'failed_count' => $run->items()->where('status', 'failed')->count(),
                'completed_at' => now(),
            ]);
            if ($run->editorialPlan && $run->editorialPlan->status === 'generating') {
                $run->editorialPlan->update(['status' => 'locked']);
            }
        });

        $this->error = 'Campagne stoppée automatiquement : '.$exception->getMessage();
        $this->message = '';
        $this->dispatch('batch-stopped');
    }

    private function contentTypeFor(Keyword $keyword): string
    {
        $value = mb_strtolower($keyword->cluster.' '.$keyword->keyword);

        return match (true) {
            str_contains($value, 'compar') || preg_match('/\bvs\b/u', $value) => 'comparison',
            str_contains($value, 'alternative') || str_contains($value, 'concurrent') => 'alternatives',
            str_contains($value, 'tarif') || str_contains($value, 'prix') => 'pricing',
            str_contains($value, 'meilleur') => 'best_tools',
            str_contains($value, 'avis') || str_contains($value, 'test') => 'tool_review',
            default => 'informational',
        };
    }

    private function uniqueProjectSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'outil';
        $slug = $base;
        $counter = 2;
        while (SeoProject::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function lines(string $value): array
    {
        return collect(preg_split('/\R/', $value))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }

    private function projectReady(SeoProject $project): bool
    {
        return $project->keywords()->exists()
            && $project->sourcePages()->where('status', 'verified')->exists();
    }

    private function allowLongRequest(int $seconds): void
    {
        if (function_exists('set_time_limit')) {
            @ini_set('max_execution_time', (string) $seconds);
            @set_time_limit($seconds);
        }
    }

    public function render()
    {
        $project = $this->projectId ? SeoProject::query()->withCount(['sourcePages', 'keywords'])->find($this->projectId) : null;
        $run = $this->activeRunId ? ContentRun::query()->with(['project', 'editorialPlan', 'items.keyword', 'items.article', 'items.editorialIdea.keyword'])->find($this->activeRunId) : null;
        $plan = $this->activePlanId ? EditorialPlan::query()->with(['project', 'ideas' => fn ($query) => $query->with('keyword')->orderBy('position')->orderByDesc('seo_score')])->find($this->activePlanId) : $run?->editorialPlan?->load(['ideas' => fn ($query) => $query->with('keyword')->orderBy('position')->orderByDesc('seo_score')]);

        return view('livewire.automation', [
            'projects' => SeoProject::query()->orderBy('name')->get(),
            'project' => $project,
            'run' => $run,
            'plan' => $plan,
            'recentRuns' => ContentRun::query()->with('project')->latest()->limit(6)->get(),
            'hasApiKey' => (bool) Setting::value('gemini_api_key', config('services.gemini.key')),
        ])->title('Flux automatisé');
    }
}
