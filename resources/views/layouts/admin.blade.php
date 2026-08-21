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
                    <span class="brand-mark">B</span><span>BusinessKit</span>
                </a>
                <button class="icon-button sidebar-close" @click="sidebarOpen = false" aria-label="Fermer le menu">×</button>
            </div>
            <nav class="nav-menu">
                <p class="nav-label">Navigation</p>
                <a class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}" wire:navigate>
                    <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z"/></svg>
                    Tableau de bord
                </a>
                <a class="nav-item {{ request()->routeIs('admin.articles*') ? 'active' : '' }}" href="{{ route('admin.articles') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="M5 3h11l3 3v15H5V3Zm2 2v14h10V7h-3V5H7Zm2 5h6v2H9v-2Zm0 4h6v2H9v-2Z"/></svg>Articles & CMS</a>
                <a class="nav-item {{ request()->routeIs('admin.newsletter') ? 'active' : '' }}" href="{{ route('admin.newsletter') }}" wire:navigate><svg viewBox="0 0 24 24"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0l-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z"/></svg>Newsletter</a>
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
                <a href="{{ url('/') }}" target="_blank" style="margin: 0 15px; font-size: 13px; font-weight: 500; color: var(--admin-text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 4px; background: rgba(0,0,0,0.05);" onmouseover="this.style.background='rgba(0,0,0,0.1)'" onmouseout="this.style.background='rgba(0,0,0,0.05)'">
                    Voir le site ↗
                </a>
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
