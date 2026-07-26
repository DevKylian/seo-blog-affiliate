<?php

namespace App\Livewire\Frontend;

use Livewire\Component;

use App\Models\NewsletterSubscriber;

class NewsletterForm extends Component
{
    public string $email = '';
    public bool $success = false;

    public function subscribe()
    {
        $this->validate([
            'email' => 'required|email|max:255',
        ]);

        NewsletterSubscriber::firstOrCreate(['email' => $this->email]);

        $this->success = true;
        $this->email = '';
    }

    public function render()
    {
        return view('livewire.frontend.newsletter-form');
    }
}
