<div>
    @if($success)
        <div style="padding: 16px; background-color: #d1fae5; color: #065f46; border-radius: 8px; font-weight: 700; max-width: 400px; margin: 0 auto;">
            Merci ! Vous êtes bien inscrit à la newsletter.
        </div>
    @else
        <form wire:submit="subscribe" class="hp-newsletter-form">
            <input type="email" wire:model="email" class="hp-newsletter-input" placeholder="votre@email.com" required>
            <button type="submit" class="hp-newsletter-btn">M'inscrire gratuitement</button>
        </form>
        @error('email')
            <div style="color: #ef4444; font-size: 14px; margin-top: 8px; font-weight: 600;">{{ $message }}</div>
        @enderror
    @endif
</div>
