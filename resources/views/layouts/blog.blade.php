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
    

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    <style>
        .mobile-menu-btn { display: none; }
        .main-nav { display: flex; align-items: center; gap: 32px; }
        .main-nav a.nav-link { color: #475569; font-weight: 700; font-size: 14px; text-decoration: none; transition: color 0.2s; }
        .main-nav a.nav-cta { background: #F75A77; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 800; font-size: 14px; text-decoration: none; box-shadow: 0 4px 6px rgba(247,90,119,0.2); transition: all 0.2s; }
        
        @media (max-width: 900px) {
            .mobile-menu-btn { display: block !important; }
            .main-nav { display: none !important; }
            .main-nav.mobile-open { 
                display: flex !important; 
                flex-direction: column !important; 
                width: 100% !important; 
                padding-top: 24px !important; 
                margin-top: 16px !important; 
                border-top: 1px solid rgba(226, 232, 240, 0.8) !important;
                gap: 16px !important;
                align-items: flex-start !important;
            }
            .main-nav.mobile-open a.nav-link { font-size: 18px !important; color: #0f172a !important; font-weight: 800 !important; width: 100%; }
            .main-nav.mobile-open a.nav-cta { font-size: 16px !important; text-align: center !important; width: 100%; margin-top: 8px !important; }
            
            /* Footer mobile */
            .blog-footer { padding: 40px 24px 32px !important; }
            .footer-wrapper { flex-direction: column !important; gap: 40px !important; }
            .footer-links { flex-direction: column !important; gap: 32px !important; align-items: flex-start !important; }
            .footer-bottom { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; }
        }
    </style>
    <!-- Umami Analytics -->
    <script defer src="https://cloud.umami.is/script.js" data-website-id="f009de17-3390-42ab-8741-cc2362bd5cc1"></script>
</head>
<body class="blog-body">
    <!-- Top Alert Banner for Mobile -->
    <style>
        .mobile-top-cta {
            display: none;
            background: #F75A77;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            box-sizing: border-box;
        }
        @media (max-width: 900px) {
            .mobile-top-cta {
                display: flex !important;
            }
        }
    </style>
    <div class="mobile-top-cta no-print" x-data="{ showAlert: true }" x-show="showAlert">
        <a href="{{ route('affiliate.redirect', 'indy') }}" target="_blank" rel="sponsored nofollow" style="display: flex; justify-content: center; align-items: center; gap: 6px; flex: 1; color: white; text-decoration: none;">
            <span>🎯 Indy : Compta 100% gratuite</span>
            <span style="text-decoration: underline; margin-left: 6px;">Voir l'offre</span>
        </a>
        <button type="button" @click="showAlert = false" aria-label="Fermer" style="background: transparent; border: none; color: white; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; opacity: 0.8;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
    <div class="no-print" style="position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,0.9); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(226, 232, 240, 0.8); width: 100%;">
        <header x-data="{ mobileMenuOpen: false }" style="display: flex; align-items: center; justify-content: space-between; max-width: 1050px; margin: 0 auto; flex-wrap: wrap; height: auto; min-height: 78px; padding: 16px 24px;">
            
            <!-- LOGO -->
            <a class="brand public-brand" href="{{ route('home') }}" style="text-decoration: none; display: flex; align-items: center; gap: 11px;">
                <span class="brand-mark" style="background: linear-gradient(135deg, #2563eb, #4f46e5); box-shadow: 0 4px 12px rgba(37,99,235,0.3);">B</span>
                <span style="font-weight: 800; font-size: 20px; color: #0f172a;">BusinessKit</span>
            </a>
            
            <!-- BURGER BTN (MOBILE) -->
            <button type="button" @click="mobileMenuOpen = !mobileMenuOpen" class="mobile-menu-btn" aria-label="Menu" style="background: none; border: none; cursor: pointer; color: #0f172a; padding: 4px; margin: 0;">
                <svg x-show="!mobileMenuOpen" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                <svg x-show="mobileMenuOpen" x-cloak width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>

            <!-- UNIFIED NAV -->
            <nav class="main-nav" :class="{ 'mobile-open': mobileMenuOpen }" aria-label="Navigation publique">
                <a href="{{ route('home') }}" class="nav-link">Accueil</a>
                <a href="{{ route('blog.index') }}" class="nav-link">Guides</a>
                <a href="{{ route('metiers.index') }}" class="nav-link">Métiers</a>
                <a href="{{ route('free-tools.index') }}" class="nav-link">Outils gratuits</a>
                <a href="{{ route('tools.index') }}" class="nav-link">Comparateur</a>
                <a href="{{ route('affiliate.redirect', 'indy') }}" class="nav-cta" target="_blank" rel="sponsored nofollow">Profiter de l'offre gratuite Indy</a>
            </nav>
        </header>
    </div>
    <main>@yield('content')</main>
    <footer class="blog-footer">
        <div class="footer-wrapper" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 48px; width: 100%; margin-bottom: 48px;">
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
                    <a href="{{ route('metiers.index') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Métiers</a>
                    <a href="{{ route('free-tools.index') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Outils gratuits</a>
                    <a href="{{ route('tools.index') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Comparatif logiciels</a>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <h4 style="color: white; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Transparence</h4>
                    <a href="{{ route('criteres') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Nos critères de sélection</a>
                    <a href="{{ route('sources') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Nos sources</a>
                    <a href="{{ route('tests') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Nos tests logiciels</a>
                    <a href="{{ route('author.show') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Notre auteur</a>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <h4 style="color: white; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Légal</h4>
                    <a href="{{ route('mentions-legales') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Mentions légales</a>
                    <a href="{{ route('politique-confidentialite') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Politique de confidentialité</a>
                    <a href="{{ route('sitemap') }}" style="color: #cbd5e1; text-decoration: none; font-size: 14px; transition: color 0.2s;">Plan du site</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom" style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 24px; width: 100%; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <p style="margin: 0; color: #64748b; font-size: 13px;">© {{ now()->year }} BusinessKit · Tous droits réservés. Certains liens peuvent être affiliés.</p>
            <span style="color: #64748b; font-size: 13px; font-weight: 500;">Conçu pour les freelances en France 🇫🇷</span>
        </div>
    </footer>
    <!-- Sticky Footer CTA for Desktop -->
    <style>
        .desktop-sticky-cta {
            position: fixed;
            bottom: 32px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            padding: 12px 12px 12px 24px;
            border-radius: 100px;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            width: max-content;
            max-width: 90vw;
        }
        .desktop-sticky-cta-btn {
            background: #F75A77;
            color: white;
            padding: 10px 24px;
            border-radius: 100px;
            font-weight: 800;
            font-size: 15px;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 10px rgba(247, 90, 119, 0.3);
            white-space: nowrap;
        }
        .desktop-sticky-cta-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(247, 90, 119, 0.4);
        }
        @media (min-width: 901px) {
            body { padding-bottom: 90px; } /* Prevent footer overlap */
        }
        @media (max-width: 900px) {
            .desktop-sticky-cta {
                display: none !important;
            }
        }
    </style>
    <div class="desktop-sticky-cta no-print">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="background: #fdf2f8; color: #F75A77; padding: 4px 10px; border-radius: 100px; font-weight: 800; font-size: 12px; text-transform: uppercase;">🎯 Notre recommandation n°1</div>
            <strong style="color: #0f172a; font-size: 14px;">Indy automatise votre compta et vos déclarations — gratuit sans limite de temps, sans engagement</strong>
        </div>
        <a href="{{ route('affiliate.redirect', 'indy') }}" class="desktop-sticky-cta-btn" target="_blank" rel="sponsored nofollow">
            Créer mon compte gratuit &rarr;
        </a>
    </div>

    @livewireScripts
</body>
</html>
