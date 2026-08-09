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

        <div class="remember-wrap" style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
            <input wire:model="remember" id="remember" type="checkbox" style="width: auto; margin: 0;">
            <label for="remember" style="margin: 0; font-weight: normal; font-size: 0.9em; cursor: pointer;">Se souvenir de moi</label>
        </div>

        <button class="primary-button" type="submit" style="margin-top: 20px;">
            <span wire:loading.remove wire:target="authenticate">Se connecter</span>
            <span wire:loading wire:target="authenticate">Connexion…</span>
        </button>
    </form>
</div>
