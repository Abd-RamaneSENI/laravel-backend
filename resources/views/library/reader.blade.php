<x-layouts.app :title="$resource->title.' | Lecture | SENI-CNF EDU'">
    <section class="reader-shell">
        <div class="reader-heading">
            <div>
                <p class="eyebrow">Lecture en ligne</p>
                <h1>{{ $resource->title }}</h1>
                <p class="lead">Ce lecteur affiche le PDF sans exposer son chemin privé. Vous pouvez aussi le télécharger pour le consulter hors ligne ou l'imprimer avec le lecteur PDF de votre navigateur.</p>
            </div>
            <div class="reader-actions">
                <a class="button secondary" href="{{ route('library.index') }}">Mes ressources</a>
                <a class="button primary" href="{{ route('resources.download', $resource) }}">Télécharger</a>
            </div>
        </div>

        <div class="pdf-reader image-reader" aria-label="Lecteur du document {{ $resource->title }}">
            @for ($page = 1; $page <= $pageCount; $page++)
                <figure class="reader-page">
                    <img
                        src="{{ route('resources.page', ['resource' => $resource, 'page' => $page, 'v' => $readerVersion]) }}"
                        alt="Page {{ $page }} de {{ $resource->title }}"
                        loading="{{ $page === 1 ? 'eager' : 'lazy' }}"
                    >
                    <figcaption>Page {{ $page }} / {{ $pageCount }}</figcaption>
                </figure>
            @endfor
        </div>
    </section>
</x-layouts.app>
