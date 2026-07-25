<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <title>@yield('title', 'BusinessKit')</title>
    <meta name="description" content="@yield('description', 'Guides, outils et comparatifs pour gérer son activité freelance en France.')">
    <meta name="robots" content="@yield('robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('title', 'BusinessKit')">
    <meta property="og:description" content="@yield('description', 'Guides, outils et comparatifs pour gérer son activité freelance en France.')">
    <meta property="og:site_name" content="BusinessKit">
    <meta property="og:image" content="@yield('og_image', url('/images/og-default.png'))">
    <meta property="og:locale" content="fr_FR">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'BusinessKit')">
    <meta name="twitter:description" content="@yield('description', 'Guides, outils et comparatifs pour gérer son activité freelance en France.')">
    <meta name="twitter:image" content="@yield('og_image', url('/images/og-default.png'))">
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="blog-body">
    <header class="blog-header" style="position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,0.9); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(226, 232, 240, 0.8);">
        <a class="brand public-brand" href="{{ route('home') }}" style="text-decoration: none;">
            <span class="brand-mark" style="background: linear-gradient(135deg, #2563eb, #4f46e5); box-shadow: 0 4px 12px rgba(37,99,235,0.3);">B</span>
            <span style="font-weight: 800; font-size: 20px; color: #0f172a;">BusinessKit</span>
        </a>
        <nav aria-label="Navigation publique" style="display: flex; align-items: center; gap: 32px;">
            <a href="{{ route('home') }}" style="color: #475569; font-weight: 700; font-size: 14px; text-decoration: none; transition: color 0.2s;">Accueil</a>
            <a href="{{ route('blog.index') }}" style="color: #475569; font-weight: 700; font-size: 14px; text-decoration: none; transition: color 0.2s;">Guides</a>
            <a href="{{ route('free-tools.index') }}" style="color: #475569; font-weight: 700; font-size: 14px; text-decoration: none; transition: color 0.2s;">Outils gratuits</a>
            <a href="{{ route('tools.index') }}" style="color: #475569; font-weight: 700; font-size: 14px; text-decoration: none; transition: color 0.2s;">Logiciels</a>
            <a href="{{ route('affiliate.redirect', 'indy') }}" style="background: #F75A77; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 800; font-size: 14px; text-decoration: none; box-shadow: 0 4px 6px rgba(247,90,119,0.2); transition: all 0.2s;" target="_blank" rel="sponsored nofollow">Essayer Indy</a>
        </nav>
    </header>
    <main>@yield('content')</main>
    <footer class="blog-footer">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 48px; width: 100%;">
            <div style="max-width: 380px;">
                <a class="brand brand-light public-brand" href="{{ route('home') }}" style="text-decoration: none; display: inline-flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                    <span class="brand-mark" style="background: linear-gradient(135deg, #2563eb, #4f46e5); box-shadow: 0 4px 12px rgba(37,99,235,0.4); border-radius: 10px; width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; color: white;">B</span>
                    <span style="font-weight: 800; font-size: 20px; color: #ffffff; line-height: 1.2;">
                        BusinessKit
                    </span>
                </a>
                <p style="color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0;">
                    Comparez les logiciels qui font gagner du temps aux entrepreneurs.
                </p>
            </div>
            <div class="footer-links" style="display: flex; flex-wrap: wrap; gap: 64px; justify-content: flex-end; flex: 1; min-width: 280px;">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <h4 style="color: white; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Ressources</h4>
                    <a href="{{ route('blog.index') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Guides SEO</a>
                    <a href="{{ route('free-tools.index') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Outils gratuits</a>
                    <a href="{{ route('tools.index') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Comparatif logiciels</a>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <h4 style="color: white; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Transparence</h4>
                    <a href="#" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Nos critères de sélection</a>
                    <a href="#" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Nos sources</a>
                    <a href="#" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Nos tests logiciels</a>
                    <a href="{{ route('author.show') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Notre auteur</a>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <h4 style="color: white; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Légal</h4>
                    <a href="#" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Mentions légales</a>
                    <a href="#" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Politique de confidentialité</a>
                    <a href="#" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Plan du site</a>
                </div>
            </div>
        </div>
        <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 24px; width: 100%; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <p style="margin: 0; color: #64748b; font-size: 13px;">© {{ now()->year }} BusinessKit · Tous droits réservés. Certains liens peuvent être affiliés.</p>
            <span style="color: #64748b; font-size: 13px; font-weight: 500;">Conçu pour les freelances en France 🇫🇷</span>
        </div>
    </footer>
</body>
</html>
