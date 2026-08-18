<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\EditorialPipeline;
use Livewire\Attributes\On;

class EditorialPipelineShow extends Component
{
    public $pipelineId;

    public function mount($id)
    {
        $this->pipelineId = $id;
    }

    public function getPipelineProperty()
    {
        return EditorialPipeline::findOrFail($this->pipelineId);
    }

    #[On('pipelineUpdated')]
    public function refreshPipeline()
    {
        // Auto re-renders
    }

    public function continuePipeline()
    {
        $pipeline = $this->pipeline;
        
        $chain = [
            'strategy' => \App\Services\EditorialPipeline\Agents\ImportAgent::class,
            'import' => \App\Services\EditorialPipeline\Agents\ClusteringAgent::class,
            'clustering' => \App\Services\EditorialPipeline\Agents\PrioritizationAgent::class,
            'prioritization' => \App\Services\EditorialPipeline\Agents\SerpAgent::class,
            'serp' => \App\Services\EditorialPipeline\Agents\ArchitectureAgent::class,
            'architecture' => \App\Services\EditorialPipeline\Agents\ConversionAgent::class,
            'conversion' => \App\Services\EditorialPipeline\Agents\BriefAgent::class,
            'brief' => \App\Services\EditorialPipeline\Agents\WritingAgent::class,
            'writing' => \App\Services\EditorialPipeline\Agents\CritiqueAgent::class,
            'critique' => null,
        ];
        
        $nextClass = $chain[$pipeline->current_agent] ?? null;
        
        if ($nextClass) {
            $agent = new $nextClass($pipeline);
            $lastArtifact = $pipeline->pipelineArtifacts()->where('agent_name', $pipeline->current_agent)->latest()->first();
            $agent->run($lastArtifact ? $lastArtifact->data : []);
        } else {
            $pipeline->update(['status' => 'completed']);
        }
    }

    public function render()
    {
        return view('livewire.admin.editorial-pipeline-show', [
            'pipeline' => $this->pipeline
        ])->layout('layouts.admin');
    }
}
