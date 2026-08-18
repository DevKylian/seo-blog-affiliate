<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\EditorialPipeline;
use App\Services\EditorialPipeline\Agents\StrategyAgent;

class EditorialPipelineDashboard extends Component
{
    public $newTheme = '';

    public function createPipeline()
    {
        $this->validate(['newTheme' => 'required|string|max:255']);

        $pipeline = EditorialPipeline::create([
            'theme' => $this->newTheme,
            'run_id' => \Illuminate\Support\Str::uuid()->toString(),
        ]);

        $this->newTheme = '';
        
        $agent = new StrategyAgent($pipeline);
        $agent->run();
    }

    public function render()
    {
        return view('livewire.admin.editorial-pipeline-dashboard', [
            'pipelines' => EditorialPipeline::latest()->get()
        ])->layout('layouts.admin');
    }
}
