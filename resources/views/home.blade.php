<x-layouts.app title="SENI-CNF EDU | Ressources scolaires et universitaires">
    <section class="hero">
        <div>
            <p class="eyebrow">Ressources scolaires & universitaires</p>
            <h1>Bienvenue sur SENI-CNF EDU.</h1>
            <p>Choisissez ci-dessous le type de contenu qui répond à votre besoin : acheter une ressource, emprunter un livre ou lire un document dans votre navigateur.</p>
            <a class="button primary" href="#contenus">Choisir un contenu</a>
        </div>
        <div class="hero-levels">
            <a href="{{ route('application', ['cycle' => 'primaire']) }}#ressources-payantes">CI - CM2</a>
            <a href="{{ route('application', ['cycle' => 'college']) }}#ressources-payantes">6e - 3e</a>
            <a href="{{ route('application', ['cycle' => 'lycee']) }}#ressources-payantes">Seconde - Terminale</a>
            <a href="{{ route('application', ['cycle' => 'universite']) }}#ressources-payantes">Licence 1 - 3</a>
        </div>
    </section>

    <section id="contenus" class="section home-catalog-links">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Choisir un contenu</p>
                <h2>Où souhaitez-vous aller ?</h2>
            </div>
        </div>
        <div class="home-content-grid">
            <a class="home-content-link paid" href="{{ route('application') }}#ressources-payantes">
                <strong>Ressources numériques</strong>
                <span>Cours, exercices, corrigés et annales disponibles à l'achat légal.</span>
            </a>
            <a class="home-content-link books" href="{{ route('application') }}#livres-physiques">
                <strong>Bibliothèque physique</strong>
                <span>Livres disponibles à l'emprunt ou à la réservation, avec leurs documents de lecture.</span>
            </a>
        </div>
    </section>

</x-layouts.app>
