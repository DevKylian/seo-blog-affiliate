<?php

namespace App\Livewire\Admin\PipelineSteps;

use Livewire\Component;
use App\Models\EditorialPipeline;
use App\Models\PipelineArtifact;

class HumanValidationStep extends Component
{
    public $pipelineId;
    public $artifactId;

    public function validateStep()
    {
        $artifact = PipelineArtifact::findOrFail($this->artifactId);
        $artifact->update(['status' => 'validated']);
        
        $pipeline = EditorialPipeline::findOrFail($this->pipelineId);
        
        // Next agent logic should go here in a real implementation
        // e.g. if artifact is from BriefAgent, trigger WritingAgent
        
        $this->dispatch('pipelineUpdated');
    }

    public function render()
    {
        return view('livewire.admin.pipeline-steps.human-validation-step', [
            'artifact' => PipelineArtifact::find($this->artifactId)
        ]);
    }
}
