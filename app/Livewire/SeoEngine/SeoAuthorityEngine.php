<?php

namespace App\Livewire\SeoEngine;

use Livewire\Component;
use App\Models\SeoProject;
use App\Models\SeoArtifact;
use App\Services\AI\Generators\ProductStrategyAnalyzer;
use Illuminate\Support\Facades\Log;

class SeoAuthorityEngine extends Component
{
    public $currentStep = 1;
    public $projectId = null;
    public $isAnalyzingMarket = false;
    public $contentCount = 10;
    public $instructions = '';
    public $activePlanId = null;
    public $activeRunId = null;
    
    public $scheduleFrequency = 'daily';
    public $schedulePostsPerInterval = 2;
    public $scheduleStartDate = null;
    
    public $steps = [
        1 => 'Projet & Produit',
        2 => 'Business Understanding',
        3 => 'Analyse Concurrence',
        4 => 'Intentions de Recherche',
        5 => 'Topic Clusters',
        6 => 'Feuille de Route',
        7 => 'Briefs SEO',
        8 => 'Production Contenu',
        9 => 'Publication & Actifs'
    ];

    protected $listeners = ['projectSelected' => 'setProject', 'nextStep' => 'goToNextStep'];

    public function mount()
    {
        $latest = SeoProject::latest()->first();
        if ($latest) {
            $this->projectId = $latest->id;
            
            // Retrouver le dernier plan
            $latestPlan = \App\Models\EditorialPlan::where('seo_project_id', $this->projectId)->latest()->first();
            if ($latestPlan) {
                $this->activePlanId = $latestPlan->id;
                
                // Retrouver le dernier run actif POUR CE PLAN
                $activeRun = \App\Models\ContentRun::where('editorial_plan_id', $this->activePlanId)->latest()->first();
                if ($activeRun) {
                    $this->activeRunId = $activeRun->id;
                }
            }
        }
    }

    public function updatedProjectId()
    {
        if ($this->projectId) {
            $this->currentStep = 2; // Move to step 2 after selecting a project
            
            $latestPlan = \App\Models\EditorialPlan::where('seo_project_id', $this->projectId)->latest()->first();
            if ($latestPlan) {
                $this->activePlanId = $latestPlan->id;
                $activeRun = \App\Models\ContentRun::where('editorial_plan_id', $this->activePlanId)->latest()->first();
                $this->activeRunId = $activeRun ? $activeRun->id : null;
            } else {
                $this->activePlanId = null;
                $this->activeRunId = null;
            }
        }
    }

    public function getActiveProjectProperty()
    {
        return $this->projectId ? SeoProject::withCount(['keywords', 'articles'])->find($this->projectId) : null;
    }

    public function getMarketAnalysisProperty()
    {
        if (!$this->projectId) return null;
        return SeoArtifact::where('seo_project_id', $this->projectId)
            ->where('type', 'market_analysis')
            ->latest('version')
            ->first();
    }

    public function getPlanProperty()
    {
        if (!$this->activePlanId) return null;
        return \App\Models\EditorialPlan::with(['ideas' => function ($q) {
            $q->with('keyword');
        }])->find($this->activePlanId);
    }

    public function getRunProperty()
    {
        if (!$this->activeRunId) return null;
        return \App\Models\ContentRun::with(['items.editorialIdea', 'items.keyword', 'items.article'])->find($this->activeRunId);
    }

    public function processPlanningStep()
    {
        $plan = $this->plan;
        if ($plan && $plan->status === 'planning') {
            // Re-render handled by Livewire automatically
        }
    }
    
    public function processRunStep()
    {
        $run = $this->run;
        if ($run && in_array($run->status, ['pending', 'processing'])) {
            // Re-render handled by Livewire automatically
        }
    }

    public function analyzeMarket(ProductStrategyAnalyzer $analyzer)
    {
        $this->isAnalyzingMarket = true;
        
        try {
            $project = SeoProject::findOrFail($this->projectId);
            $result = $analyzer->generate($project);
            $this->isAnalyzingMarket = false;
            
            // Advance to next step
            $this->goToNextStep();
            
        } catch (\Exception $e) {
            $this->isAnalyzingMarket = false;
            Log::error('Market analysis failed: ' . $e->getMessage());
            $this->addError('market_analysis', 'Erreur lors de l\'analyse du marché : ' . $e->getMessage());
        }
    }

    public function setProject($id)
    {
        $this->projectId = $id;
        $this->currentStep = 2;
    }

    public function goToStep($step)
    {
        if ($step >= 1 && $step <= 9) {
            $this->currentStep = $step;
        }
    }

    public function goToNextStep()
    {
        if ($this->currentStep < 9) {
            $this->currentStep++;
        }
    }

    public function clusterKeywords(\App\Services\SemanticKeywordClusterer $clusterer)
    {
        try {
            $project = SeoProject::findOrFail($this->projectId);
            $clusterer->rebuildProject($project);
            
            // Advance to next step
            $this->goToNextStep();
        } catch (\Exception $e) {
            Log::error('Clustering failed: ' . $e->getMessage());
            $this->addError('clustering', 'Erreur lors du clustering : ' . $e->getMessage());
        }
    }

    public function startStrategyPlan(\App\Services\EditorialPlanBuilder $planner, \App\Services\EditorialPlanWorkerLauncher $planningWorker)
    {
        try {
            $project = SeoProject::findOrFail($this->projectId);
            
            // We use the same workflow as Automation.php
            $plan = $planner->createPlan($project, auth()->id(), $this->contentCount, $this->instructions);
            $this->activePlanId = $plan->id;
            $this->activeRunId = null; // Reset the active run since we have a new plan
            
            if (! app()->runningUnitTests()) {
                $planningWorker->launch($plan->id);
            }
            
            // On le redirige vers l'étape suivante ou on lui affiche un succès
            $this->goToNextStep();
        } catch (\Exception $e) {
            Log::error('Planification failed: ' . $e->getMessage());
            $this->addError('planification', 'Erreur lors de la planification : ' . $e->getMessage());
        }
    }

    public function launchRun(\App\Services\ContentRunWorkerLauncher $worker)
    {
        $plan = $this->plan;
        if (! $plan || ! $plan->isReady()) {
            $this->addError('run', 'Le plan n’est pas prêt.');
            return;
        }
        if ($plan->runs()->exists()) {
            $this->addError('run', 'Ce plan éditorial a déjà été lancé.');
            return;
        }

        $ideas = $plan->ideas()
            ->where('status', 'accepted')
            ->orderBy('position')
            ->orderByDesc('seo_score')
            ->limit($plan->requested_count)
            ->get();
            
        if ($ideas->count() !== $plan->requested_count) {
            $this->addError('run', "Le plan contient {$ideas->count()} briefs exploitables sur {$plan->requested_count} attendus.");
            return;
        }

        $extraAcceptedIds = $plan->ideas()->where('status', 'accepted')->whereNotIn('id', $ideas->pluck('id'))->pluck('id');
        if ($extraAcceptedIds->isNotEmpty()) {
            $plan->ideas()->whereIn('id', $extraAcceptedIds)->update(['status' => 'reserve', 'position' => null]);
        }

        $run = \Illuminate\Support\Facades\DB::transaction(function () use ($plan, $ideas) {
            $run = \App\Models\ContentRun::query()->create([
                'seo_project_id' => $plan->seo_project_id,
                'user_id' => auth()->id(),
                'editorial_plan_id' => $plan->id,
                'name' => 'Campagne '.$plan->project->name.' — '.now()->format('d/m/Y H:i'),
                'requested_count' => $plan->requested_count,
                'status' => 'pending',
                'instructions' => $plan->instructions,
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
        
        if (! app()->runningUnitTests()) {
            try {
                $worker->launch($run->id);
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::error($exception);
                $this->addError('run', $exception->getMessage());
            }
        }
    }

    public function publishRun()
    {
        $run = $this->run;
        if (! $run) {
            return;
        }

        $currentDate = $this->scheduleStartDate ? \Carbon\Carbon::parse($this->scheduleStartDate) : now();
        $postsInCurrentInterval = 0;

        foreach ($run->items as $item) {
            $publishDate = $currentDate->copy();
            $status = $publishDate->isPast() || $publishDate->isSameDay(now()) ? 'published' : 'scheduled';
            
            if ($item->article) {
                $item->article->update([
                    'status' => $status,
                    'published_at' => clone $publishDate,
                    'scheduled_at' => $status === 'scheduled' ? clone $publishDate : null,
                ]);
            } elseif (empty($item->article_id)) {
                $idea = $item->editorialIdea;
                $title = $idea ? $idea->title : 'Article généré - ' . $item->id;
                $slug = \Illuminate\Support\Str::slug($title) . '-' . uniqid();
                $body = is_array($item->generation_parts) ? implode("\n\n", $item->generation_parts) : '';

                $article = \App\Models\Article::query()->create([
                    'seo_project_id' => $run->seo_project_id,
                    'keyword_id' => $item->keyword_id ?? $idea?->keyword_id,
                    'type' => $item->content_type ?? 'blog',
                    'title' => $title,
                    'slug' => $slug,
                    'body' => $body,
                    'status' => $status,
                    'published_at' => clone $publishDate,
                    'scheduled_at' => $status === 'scheduled' ? clone $publishDate : null,
                    'author_id' => auth()->id() ?? 1,
                    'generated_by' => 'gemini-1.5-pro',
                ]);
                $item->update(['article_id' => $article->id]);
            }

            // Scheduling logic
            $postsInCurrentInterval++;
            if ($postsInCurrentInterval >= $this->schedulePostsPerInterval) {
                $postsInCurrentInterval = 0;
                if ($this->scheduleFrequency === 'daily') {
                    $currentDate->addDay();
                } elseif ($this->scheduleFrequency === 'weekly') {
                    $currentDate->addWeek();
                } elseif ($this->scheduleFrequency === 'monthly') {
                    $currentDate->addMonth();
                }
            }
        }
        
        session()->flash('message', 'Les articles ont été planifiés/publiés avec succès !');
        $this->goToNextStep();
    }

    public function retryFailedRun(\App\Services\ContentRunWorkerLauncher $worker)
    {
        $run = $this->run;
        if (!$run) return;

        $failedCount = $run->items()->where('status', 'failed')->count();
        if ($failedCount === 0) return;

        \Illuminate\Support\Facades\DB::transaction(function () use ($run): void {
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

        if (! app()->runningUnitTests()) {
            try {
                $worker->launch($run->id);
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::error($exception);
                $this->addError('run', $exception->getMessage());
            }
        }
    }

    public function getClustersProperty()
    {
        if (!$this->projectId) return null;
        return \App\Models\ContentCluster::where('seo_project_id', $this->projectId)
            ->where('status', '!=', 'archived')
            ->withCount('keywords')
            ->orderByDesc('opportunity_score')
            ->get();
    }

    public function render()
    {
        return view('livewire.seo-engine.seo-authority-engine', [
            'projects' => SeoProject::orderBy('name')->get(),
            'activeProject' => $this->activeProject,
            'marketAnalysis' => $this->marketAnalysis,
            'clusters' => $this->clusters,
            'plan' => $this->plan,
            'run' => $this->run,
        ])->layout('layouts.admin');
    }
}
