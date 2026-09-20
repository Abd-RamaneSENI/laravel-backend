<x-layouts.app title="Mes ressources | SENI-CNF EDU">
    <section class="section">
        <p class="eyebrow">Compte étudiant</p>
        <h1>Mes ressources achetées</h1>
        <p class="lead">Les documents confirmés après paiement restent ici. Vous pouvez les lire sur la plateforme, les télécharger pour une consultation hors ligne et les imprimer depuis votre lecteur PDF.</p>

        <div class="resource-grid library-grid">
            @forelse($entitlements as $entitlement)
                @php($resource = $entitlement->resource)
                <article class="resource-card">
                    <div class="cover">{{ str($resource->original_filename ?: $resource->title)->endsWith('.pdf') || $resource->mime_type === 'application/pdf' ? 'PDF' : 'DOC' }}</div>
                    <div>
                        <span class="tag">Acheté le {{ $entitlement->granted_at->format('d/m/Y') }}</span>
                        <h3>{{ $resource->title }}</h3>
                        <p>{{ $resource->resourceType?->name ?? 'Ressource' }} · {{ number_format($resource->price, 0, ',', ' ') }} XOF</p>
                        <div class="library-actions">
                            @if ($resource->mime_type === 'application/pdf' || str($resource->original_filename ?: $resource->private_path)->lower()->endsWith('.pdf'))
                                <a class="button primary" href="{{ route('resources.read', $resource) }}">Lire en ligne</a>
                            @endif
                            <a class="button secondary" href="{{ route('resources.download', $resource) }}">Télécharger</a>
                        </div>
                    </div>
                </article>
            @empty
                <p>Vos ressources achetées apparaîtront ici après confirmation du paiement.</p>
            @endforelse
        </div>

        <div class="pagination">{{ $entitlements->links() }}</div>
    </section>

    @if ($autoDownloadEntitlements->isNotEmpty())
        <div class="auto-download-notice" role="status">
            Téléchargement automatique en cours. Si rien ne démarre, utilisez le bouton Télécharger sur la ressource achetée.
        </div>
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                const urls = @json($autoDownloadEntitlements->map(fn ($entitlement) => route('resources.download', $entitlement->resource))->values());
                urls.forEach((url, index) => {
                    window.setTimeout(() => {
                        const frame = document.createElement('iframe');
                        frame.src = url;
                        frame.hidden = true;
                        frame.setAttribute('aria-hidden', 'true');
                        document.body.appendChild(frame);
                    }, index * 700);
                });
            });
        </script>
    @endif
</x-layouts.app>
