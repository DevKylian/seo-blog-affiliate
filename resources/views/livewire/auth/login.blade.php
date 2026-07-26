<div class="login-card">
    <form wire:submit="authenticate" class="login-form">
        <label for="email">Adresse e-mail</label>
        <div class="input-wrap">
            <input wire:model="email" id="email" type="email" autocomplete="email" autofocus>
        </div>
        @error('email') <span class="field-error">{{ $message }}</span> @enderror

        <label for="password">Mot de passe</label>
        <div class="input-wrap">
            <input wire:model="password" id="password" type="password" autocomplete="current-password">
        </div>
        @error('password') <span class="field-error">{{ $message }}</span> @enderror

        <button class="primary-button" type="submit" style="margin-top: 15px;">
            <span wire:loading.remove wire:target="authenticate">Se connecter</span>
            <span wire:loading wire:target="authenticate">Connexion…</span>
        </button>
    </form>
</div>
