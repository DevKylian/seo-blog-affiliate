<div class="login-card">
    <div class="login-heading">
        <span class="mobile-brand"><span class="brand-mark">B</span> BlogSEO</span>
        <span class="eyebrow dark">Administration</span>
        <h2>Heureux de vous revoir.</h2>
        <p>Connectez-vous pour accéder à votre espace.</p>
    </div>

    <form wire:submit="authenticate" class="login-form">
        <label for="email">Adresse e-mail</label>
        <div class="input-wrap">
            <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2Zm0 4-8 5-8-5V6l8 5 8-5v2Z"/></svg>
            <input wire:model="email" id="email" type="email" autocomplete="email" placeholder="admin@example.com" autofocus>
        </div>
        @error('email') <span class="field-error">{{ $message }}</span> @enderror

        <label for="password">Mot de passe</label>
        <div class="input-wrap">
            <svg viewBox="0 0 24 24"><path d="M12 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm6-9h-1V6A5 5 0 0 0 7 6v2H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2ZM9 6a3 3 0 0 1 6 0v2H9V6Zm9 13H6v-9h12v9Z"/></svg>
            <input wire:model="password" id="password" type="password" autocomplete="current-password" placeholder="••••••••">
        </div>
        @error('password') <span class="field-error">{{ $message }}</span> @enderror

        <label class="checkbox-row"><input wire:model="remember" type="checkbox"><span>Rester connecté</span></label>
        <button class="primary-button" type="submit">
            <span wire:loading.remove wire:target="authenticate">Se connecter <b>→</b></span>
            <span wire:loading wire:target="authenticate">Connexion…</span>
        </button>
    </form>
    <p class="demo-hint">Démo : admin@example.com / password</p>
</div>
