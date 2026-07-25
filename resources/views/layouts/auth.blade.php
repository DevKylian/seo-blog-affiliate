<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Administration' }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body" style="display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f8fafc; margin: 0;">
    <main style="width: 100%; max-width: 400px; padding: 20px;">
        {{ $slot }}
    </main>
</body>
</html>
