<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Ressources scolaires et universitaires autorisées, de la CI à la Licence 3.">
    <title>{{ $title ?? 'SENI-CNF EDU' }}</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}">
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
    <link rel="stylesheet" href="{{ asset('about.css') }}">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}"><span>SENI-CNF</span><b>EDU</b></a>
        <form class="top-search" action="{{ route('application') }}">
            <input name="q" value="{{ request('q') }}" placeholder="Rechercher une ressource">
        </form>
        <nav>
            <a href="{{ route('application') }}">Catalogue</a>
            <a href="{{ route('about') }}">En savoir plus</a>
            @auth
                <a href="{{ route('library.index') }}">Mes ressources</a>
                <a href="{{ route('cart.show') }}">Panier</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}">Administration</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button>Déconnexion</button>
                </form>
            @else
                <a href="{{ route('login') }}">Connexion</a>
                <a class="nav-cta" href="{{ route('register') }}">Créer un compte</a>
            @endauth
        </nav>
    </header>

    @if (session('success'))
        <div class="flash success">{{ session('success') }}</div>
    @endif
    @if (session('warning'))
        <div class="flash warning">{{ session('warning') }}</div>
    @endif
    @if ($errors->any())
        <div class="flash error">{{ $errors->first() }}</div>
    @endif

    <main>{{ $slot }}</main>
    <footer>
        <strong>SENI-CNF EDU</strong>
        <span>SAVOIR • SAVOIR-FAIRE • AVENIR</span>
        <a href="#">Conditions de vente</a>
        <a href="#">Confidentialité</a>
        <a href="{{ route('about') }}">En savoir plus</a>
    </footer>
</body>
</html>
