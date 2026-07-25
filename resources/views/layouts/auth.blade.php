<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Administration' }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-intro">
            <a class="brand brand-light" href="/">
                <span class="brand-mark">B</span>
                <span>BusinessKit</span>
            </a>
            <div class="auth-copy">
                <span class="eyebrow">Espace de pilotage</span>
                <h1>Vos contenus.<br>Vos résultats.<br><em>Un seul endroit.</em></h1>
                <p>Suivez la croissance de votre audience et gardez le contrôle sur votre plateforme.</p>
            </div>
            <p class="auth-footnote">Propulsé par Laravel & Livewire</p>
        </section>
        <section class="auth-panel">{{ $slot }}</section>
    </main>
</body>
</html>
