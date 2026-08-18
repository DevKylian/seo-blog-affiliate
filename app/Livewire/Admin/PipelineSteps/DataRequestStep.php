<?php

namespace App\Livewire\Admin\PipelineSteps;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\EditorialPipeline;
use App\Services\EditorialPipeline\Agents\ImportAgent;
use Illuminate\Support\Facades\Storage;

class DataRequestStep extends Component
{
    use WithFileUploads;

    public $pipelineId;
    public $csvFile;

    public function processUpload()
    {
        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        $pipeline = EditorialPipeline::findOrFail($this->pipelineId);

        // Save the file
        $path = $this->csvFile->store('semrush_imports');

        // Note: We don't read CSV here since this is scaffolding, we just trigger the ImportAgent.
        $agent = new ImportAgent($pipeline);
        $agent->run([
            'file_path' => $path,
            'keywords' => ['example_keyword' => 1] // Mocked data
        ]);
        
        $this->reset('csvFile');
        $this->dispatch('pipelineUpdated');
    }

    public function simulateImport()
    {
        $pipeline = EditorialPipeline::findOrFail($this->pipelineId);
        $artifact = $pipeline->pipelineArtifacts()->where('agent_name', 'strategy')->latest()->first();
        $themes = $artifact->data['themes'] ?? [];
        
        $mockedKeywords = [];
        foreach ($themes as $theme) {
            foreach ($theme['seed_keywords'] ?? [] as $sk) {
                $mockedKeywords[] = [
                    'keyword' => $sk,
                    'volume' => rand(100, 5000),
                    'keyword_difficulty' => rand(10, 80),
                    'cpc' => rand(1, 100) / 10,
                    'intent' => ['Informational', 'Commercial', 'Navigational', 'Transactional'][rand(0, 3)]
                ];
                $mockedKeywords[] = [
                    'keyword' => $sk . ' avis',
                    'volume' => rand(50, 1000),
                    'keyword_difficulty' => rand(10, 50),
                    'cpc' => rand(1, 50) / 10,
                    'intent' => 'Commercial'
                ];
            }
        }
        
        $agent = new ImportAgent($pipeline);
        $agent->run([
            'file_path' => 'simulated_import.csv',
            'keywords' => $mockedKeywords
        ]);
        
        $this->dispatch('pipelineUpdated');
    }

    public function render()
    {
        return view('livewire.admin.pipeline-steps.data-request-step');
    }
}
