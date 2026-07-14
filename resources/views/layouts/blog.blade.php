<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Blog') — {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', 'Guides, comparatifs et analyses de logiciels vérifiés par BlogSEO.')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="blog-body">
    <header class="blog-header"><a class="brand" href="{{ route('blog.index') }}"><span class="brand-mark">B</span><span>BlogSEO</span></a><nav><a href="{{ route('blog.index') }}">Guides & comparatifs</a><a href="{{ route('tools.index') }}">Outils</a><a href="{{ route('login') }}">Administration</a></nav></header>
    <main>@yield('content')</main>
    <footer class="blog-footer"><div><a class="brand brand-light" href="{{ route('blog.index') }}"><span class="brand-mark">B</span><span>BlogSEO</span></a><p>Des analyses logicielles sourcées, régulièrement vérifiées et transparentes.</p></div><p>© {{ now()->year }} BlogSEO · Certains liens peuvent être affiliés.</p></footer>
</body>
</html>
