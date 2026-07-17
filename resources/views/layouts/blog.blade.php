<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'FreelanceOS') - {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', 'Guides, outils et comparatifs pour gérer son activité freelance en France.')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="blog-body">
    <header class="blog-header">
        <a class="brand public-brand" href="{{ route('home') }}">
            <span class="brand-mark">F</span>
            <span>FreelanceOS<small>Guides terrain pour indépendants</small></span>
        </a>
        <nav aria-label="Navigation publique">
            <a href="{{ route('home') }}">Parcours</a>
            <a href="{{ route('blog.index') }}">Guides</a>
            <a href="{{ route('free-tools.index') }}">Outils gratuits</a>
            <a href="{{ route('tools.index') }}">Logiciels</a>
            <a class="nav-admin" href="{{ route('login') }}">Studio</a>
        </nav>
    </header>
    <main>@yield('content')</main>
    <footer class="blog-footer">
        <div>
            <a class="brand brand-light public-brand" href="{{ route('home') }}"><span class="brand-mark">F</span><span>FreelanceOS<small>Freelance admin, sans folklore</small></span></a>
            <p>Guides vérifiés, outils gratuits et comparatifs transparents pour indépendants.</p>
        </div>
        <div class="footer-links">
            <a href="{{ route('blog.index') }}">Guides</a>
            <a href="{{ route('free-tools.index') }}">Outils gratuits</a>
            <a href="{{ route('tools.index') }}">Logiciels suivis</a>
        </div>
        <p>© {{ now()->year }} FreelanceOS · Certains liens peuvent être affiliés.</p>
    </footer>
</body>
</html>
