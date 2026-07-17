<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Administration' }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body" x-data="{ sidebarOpen: false }">
    <div class="admin-shell">
        <div class="sidebar-overlay" x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"></div>
        <aside class="sidebar" :class="{ 'is-open': sidebarOpen }">
            <div class="sidebar-head">
                <a class="brand" href="{{ route('admin.dashboard') }}" wire:navigate>
                    <span class="brand-mark">B</span><span>BlogSEO</span>
                </a>
                <button class="icon-button sidebar-close" @click="sidebarOpen = false" aria-label="Fermer le menu">×</button>
            </div>
            <nav class="nav-menu">
                <p class="nav-label">Navigation</p>
                <a class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}" wire:navigate>
                    <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z"/></svg>
                    Tableau de bord
                </a>
                <a class="nav-item nav-automation {{ request()->routeIs('admin.automation') ? 'active' : '' }}" href="{{ route('admin.automation') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="m12 2 1.8 5.2L19 9l-5.2 1.8L12 16l-1.8-5.2L5 9l5.2-1.8L12 2Zm7 12 1 2.8 2.8 1-2.8 1L19 22l-1-3.2-2.8-1 2.8-1L19 14Z"/></svg>Flux automatique <small>AI</small></a>
                <a class="nav-item {{ request()->routeIs('admin.scheduler') ? 'active' : '' }}" href="{{ route('admin.scheduler') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="M7 2h2v2h6V2h2v2h3v18H4V4h3V2Zm11 8H6v10h12V10ZM6 6v2h12V6H6Zm2 6h3v3H8v-3Zm5 0h3v3h-3v-3Z"/></svg>Content Factory <small>Auto</small></a>
                <a class="nav-item {{ request()->routeIs('admin.projects') ? 'active' : '' }}" href="{{ route('admin.projects') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"/></svg>Projets & outils</a>
                <a class="nav-item {{ request()->routeIs('admin.research') ? 'active' : '' }}" href="{{ route('admin.research') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="m9.5 3 1.6 4.4L15.5 9l-4.4 1.6L9.5 15l-1.6-4.4L3.5 9l4.4-1.6L9.5 3Zm7 8 1.1 2.9 2.9 1.1-2.9 1.1L16.5 19l-1.1-2.9-2.9-1.1 2.9-1.1L16.5 11Z"/></svg>Collecte & preuves</a>
                <a class="nav-item {{ request()->routeIs('admin.keywords') ? 'active' : '' }}" href="{{ route('admin.keywords') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="M9.5 3A6.5 6.5 0 1 0 13.6 14.55L19.05 20 20.5 18.55l-5.45-5.45A6.5 6.5 0 0 0 9.5 3Zm0 2a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Z"/></svg>Mots-clés Semrush</a>
                <a class="nav-item {{ request()->routeIs('admin.content') ? 'active' : '' }}" href="{{ route('admin.content') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2Zm-2 12H7v-2h10v2Zm0-4H7V9h10v2Zm-3-4H7V5h7v2Z"/></svg>Studio de contenu</a>
                <a class="nav-item {{ request()->routeIs('admin.audits') ? 'active' : '' }}" href="{{ route('admin.audits') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5l-8-3Zm0 2.2L18 6v5c0 3.8-2.4 7.4-6 8.8-3.6-1.4-6-5-6-8.8V6l6-1.8Zm3.7 5.1 1.4 1.4-5.8 5.8-3.2-3.2 1.4-1.4 1.8 1.8 4.4-4.4Z"/></svg>Audits qualité</a>
                <a class="nav-item {{ request()->routeIs('admin.seo-intelligence') ? 'active' : '' }}" href="{{ route('admin.seo-intelligence') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="M4 19h16v2H4v-2Zm1-3 4-4 3 3 6-7 1.5 1.3-7.4 8.7-3.1-3.1L6.4 18 5 16Zm1-7a3 3 0 1 1 5.8 1H9.4A1 1 0 1 0 8 9H6Zm9-6h4v4h-4V3Zm1.5 1.5v1h1v-1h-1Z"/></svg>SEO Intelligence</a>
                <a class="nav-item {{ request()->routeIs('admin.articles*') ? 'active' : '' }}" href="{{ route('admin.articles') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="M5 3h11l3 3v15H5V3Zm2 2v14h10V7h-3V5H7Zm2 5h6v2H9v-2Zm0 4h6v2H9v-2Z"/></svg>Articles & CMS</a>
                <p class="nav-label nav-label-second">Système</p>
                <a class="nav-item {{ request()->routeIs('admin.logs') ? 'active' : '' }}" href="{{ route('admin.logs') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="M4 5h16v2H4V5Zm0 6h16v2H4v-2Zm0 6h10v2H4v-2Z"/></svg>Logs d’accès</a>
                <a class="nav-item {{ request()->routeIs('admin.settings') ? 'active' : '' }}" href="{{ route('admin.settings') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="m19.4 13 .1-1-.1-1 2-1.6-2-3.4-2.5 1a8 8 0 0 0-1.7-1L15 3h-4l-.4 3a8 8 0 0 0-1.7 1L6.5 6l-2 3.4L6.6 11l-.1 1 .1 1-2 1.6 2 3.4 2.5-1a8 8 0 0 0 1.7 1l.2 3h4l.4-3a8 8 0 0 0 1.7-1l2.4 1 2-3.4-2.1-1.6ZM13 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Z"/></svg>Réglages API</a>
            </nav>
            <div class="sidebar-user">
                <div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                <div><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button title="Se déconnecter" aria-label="Se déconnecter">↗</button></form>
            </div>
        </aside>
        <main class="main-content">
            <header class="topbar">
                <button class="icon-button menu-button" @click="sidebarOpen = true" aria-label="Ouvrir le menu">☰</button>
                <div class="topbar-spacer"></div>
                @if(app()->isLocal())
                    <form method="POST" action="{{ route('admin.dev.stop-generations') }}" class="dev-stop-form">
                        @csrf
                        <button type="submit" class="dev-stop-button" title="Stopper toutes les générations et planifications IA actives en local" onclick="return confirm('Couper toutes les générations IA en cours et mettre la Content Factory en pause ?')">
                            <span></span>
                            Couper les générations
                        </button>
                    </form>
                @endif
                <span class="status-pill"><i></i> Système opérationnel</span>
                <div class="top-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            </header>
            @if(session('dev_generation_stop'))
                <div class="dev-stop-toast">{{ session('dev_generation_stop') }}</div>
            @endif
            {{ $slot }}
        </main>
    </div>
</body>
</html>
