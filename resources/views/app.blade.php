<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Gestion des livres, emprunts et réservations de SENI-CNF EDU.">
    <title>SENI-CNF EDU | Bibliothèque</title>
    @vite(['resources/js/app.js'])
</head>
<body>
    <div id="library-root"></div>
</body>
</html>
