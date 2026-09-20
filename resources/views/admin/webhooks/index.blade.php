<x-layouts.app title="Webhooks | SENI-CNF EDU">
    <section class="admin-shell">
        <p class="eyebrow">Administration</p>
        <h1>Webhooks</h1>
        <p class="lead">Journal des événements reçus. Les payloads complets ne sont pas affichés afin d'éviter toute fuite de secret.</p>

        @include('admin.partials.nav')

        <div class="admin-table">
            <div class="admin-table-head webhooks-grid">
                <span>Event ID</span>
                <span>Provider</span>
                <span>Signature</span>
                <span>Statut</span>
                <span>Erreur</span>
                <span>Traitement</span>
            </div>
            @forelse ($webhooks as $event)
                <div class="admin-table-row webhooks-grid">
                    <span><b>{{ $event->event_id }}</b><small>{{ $event->payload_hash }}</small></span>
                    <span>{{ strtoupper($event->provider) }} · {{ $event->environment }}</span>
                    <span>{{ $event->signature_valid ? 'valide' : 'invalide' }}</span>
                    <span><em>{{ $event->status }}</em></span>
                    <span>{{ $event->error_code ?: '-' }}</span>
                    <span>{{ $event->processed_at?->format('d/m/Y H:i') ?? 'En attente' }}</span>
                </div>
            @empty
                <p>Aucun webhook reçu.</p>
            @endforelse
        </div>

        <div class="pagination">{{ $webhooks->links() }}</div>
    </section>
</x-layouts.app>
