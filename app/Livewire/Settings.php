<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Services\GeminiContentGenerator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin')]
class Settings extends Component
{
    public string $apiKey = '';

    public string $model = 'gemini-2.5-flash-lite';

    public string $message = '';

    public string $error = '';

    public bool $hasSavedKey = false;

    public function mount(): void
    {
        $this->model = (string) Setting::value('gemini_model', 'gemini-2.5-flash-lite');
        if (! in_array($this->model, ['gemini-2.5-flash-lite', 'gemini-2.5-flash'], true)) {
            $this->model = 'gemini-2.5-flash-lite';
        }
        $this->hasSavedKey = (bool) Setting::value('gemini_api_key', config('services.gemini.key'));
    }

    public function save(): void
    {
        $this->validate([
            'apiKey' => ['nullable', 'string', 'min:20', 'max:500'],
            'model' => ['required', 'in:gemini-2.5-flash-lite,gemini-2.5-flash'],
        ]);
        if ($this->apiKey !== '') {
            Setting::put('gemini_api_key', trim($this->apiKey), true);
            $this->apiKey = '';
            $this->hasSavedKey = true;
        }
        Setting::put('gemini_model', $this->model);
        $this->message = 'Réglages enregistrés. La clé est chiffrée avec APP_KEY.';
        $this->error = '';
    }

    public function test(GeminiContentGenerator $generator): void
    {
        $this->message = '';
        $this->error = '';
        try {
            $key = $this->apiKey !== '' ? $this->apiKey : null;
            $generator->testConnection($key, $this->model);
            $this->message = 'Connexion Gemini validée avec succès.';
        } catch (Throwable $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.settings')->title('Réglages IA');
    }
}
