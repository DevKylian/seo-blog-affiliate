<?php

namespace App\Livewire;

use App\Services\MarkdownImportService;
use Livewire\Component;
use Livewire\WithFileUploads;


class MarkdownImporter extends Component
{
    use WithFileUploads;

    public $markdownFiles = [];
    public $message = '';
    public function importFiles(MarkdownImportService $service)
    {
        $this->validate([
            'markdownFiles.*' => 'file|max:2048', // 2MB Max
        ]);

        $count = 0;
        foreach ($this->markdownFiles as $file) {
            $content = file_get_contents($file->getRealPath());
            $service->import($content);
            $count++;
        }

        $this->markdownFiles = [];
        $this->message = "{$count} article(s) importé(s) avec succès en brouillon.";
        
        // Refresh the parent articles list if using events, or just let Livewire handle state
        $this->dispatch('articlesImported');
    }

    public function render()
    {
        return view('livewire.markdown-importer');
    }
}
