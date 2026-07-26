<?php

namespace App\Livewire\Admin;

use Livewire\Component;

use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\NewsletterSubscriber;

#[Layout('layouts.admin')]
class NewsletterSubscribers extends Component
{
    use WithPagination;

    public function delete(int $id)
    {
        NewsletterSubscriber::destroy($id);
    }

    public function render()
    {
        return view('livewire.admin.newsletter-subscribers', [
            'subscribers' => NewsletterSubscriber::latest()->paginate(50)
        ]);
    }
}
