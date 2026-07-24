<?php

namespace App\Livewire\SeoEngine;

use Livewire\Component;
use App\Models\SeoTask;

class MonitoringDashboard extends Component
{
    public function resolveTask($taskId)
    {
        $task = SeoTask::find($taskId);
        if ($task) {
            $task->status = 'completed';
            $task->save();
        }
    }

    public function render()
    {
        $tasks = SeoTask::where('status', 'pending')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return view('livewire.seo-engine.monitoring-dashboard', [
            'tasks' => $tasks
        ]);
    }
}
