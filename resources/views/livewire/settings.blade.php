<div class="dashboard-page os-page narrow-page">
    <section class="page-heading"><div><span class="eyebrow dark">Configuration</span><h1>Réglages Gemini</h1><p>La génération est exécutée exclusivement côté serveur.</p></div></section>
    @if($message)<div class="alert success">✓ {{ $message }}</div>@endif @if($error)<div class="alert danger">{{ $error }}</div>@endif
    <article class="panel settings-card"><div class="settings-hero"><span class="gemini-logo">✦</span><div><h2>Google Gemini API</h2><p>Modèle stable pour la génération de contenus structurés et cités.</p></div><span class="connection-state {{ $hasSavedKey ? 'connected':'' }}"><i></i>{{ $hasSavedKey ? 'Clé configurée':'Non configuré' }}</span></div>
        <form wire:submit="save" class="os-form one-column settings-form">
            <div class="field"><label>Clé API Gemini</label><div class="secret-input"><input wire:model="apiKey" type="password" autocomplete="new-password" placeholder="{{ $hasSavedKey ? '•••••••••••••••• (laisser vide pour conserver)' : 'Saisissez votre clé API' }}"><span>🔒</span></div>@error('apiKey')<small class="field-error">{{ $message }}</small>@enderror<p class="field-help">Chiffrée en base avec APP_KEY. Elle n’est jamais envoyée au navigateur après enregistrement.</p></div>
            <div class="field"><label>Modèle</label><select wire:model="model"><option value="gemini-2.5-flash-lite">Gemini 2.5 Flash-Lite — économique (par défaut)</option><option value="gemini-2.5-flash">Gemini 2.5 Flash — alternative manuelle</option></select></div>
            <div class="settings-actions"><button class="secondary-button" type="button" wire:click="test"><span wire:loading.remove wire:target="test">Tester la connexion</span><span wire:loading wire:target="test">Test…</span></button><button class="primary-button" type="submit">Enregistrer</button></div>
        </form>
        <div class="security-box"><strong>Bonnes pratiques</strong><ul><li>Utilisez une clé d’autorisation Gemini récente et restreinte à l’API Gemini.</li><li>Définissez des alertes de facturation et faites tourner la clé si elle a été exposée.</li><li>En production, privilégiez un gestionnaire de secrets externe.</li></ul><a href="https://ai.google.dev/gemini-api/docs/api-key" target="_blank" rel="noopener">Documentation officielle ↗</a></div>
    </article>
</div>
