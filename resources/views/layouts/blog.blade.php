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
    <header class="blog-header" style="position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,0.9); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(226, 232, 240, 0.8);">
        <a class="brand public-brand" href="{{ route('home') }}" style="text-decoration: none;">
            <span class="brand-mark" style="background: linear-gradient(135deg, #2563eb, #4f46e5); box-shadow: 0 4px 12px rgba(37,99,235,0.3);">F</span>
            <span style="font-weight: 800; font-size: 20px; color: #0f172a;">FreelanceOS</span>
        </a>
        <nav aria-label="Navigation publique" style="display: flex; align-items: center; gap: 32px;">
            <a href="{{ route('home') }}" style="color: #475569; font-weight: 700; font-size: 14px; text-decoration: none; transition: color 0.2s;">Accueil</a>
            <a href="{{ route('blog.index') }}" style="color: #475569; font-weight: 700; font-size: 14px; text-decoration: none; transition: color 0.2s;">Guides</a>
            <a href="{{ route('free-tools.index') }}" style="color: #475569; font-weight: 700; font-size: 14px; text-decoration: none; transition: color 0.2s;">Outils gratuits</a>
            <a href="{{ route('tools.index') }}" style="color: #475569; font-weight: 700; font-size: 14px; text-decoration: none; transition: color 0.2s;">Logiciels</a>
            <a href="{{ route('affiliate.redirect', 'indy') }}" style="background: #2563eb; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 800; font-size: 14px; text-decoration: none; box-shadow: 0 4px 6px rgba(37,99,235,0.2); transition: all 0.2s;" target="_blank" rel="sponsored nofollow">Essayer Indy</a>
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
