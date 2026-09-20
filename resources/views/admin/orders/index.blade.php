<x-layouts.app title="Commandes | SENI-CNF EDU">
    <section class="admin-shell">
        <p class="eyebrow">Administration</p>
        <h1>Commandes</h1>
        <p class="lead">Suivi des références, statuts, montants serveur et confirmations de paiement.</p>

        @include('admin.partials.nav')

        <div class="admin-table">
            <div class="admin-table-head orders-grid">
                <span>Référence</span>
                <span>Client</span>
                <span>Montant</span>
                <span>Statut</span>
                <span>Paiements</span>
                <span>Date</span>
            </div>
            @forelse ($orders as $order)
                <div class="admin-table-row orders-grid">
                    <span><b>{{ $order->reference }}</b><small>{{ $order->items->count() }} ressource(s)</small></span>
                    <span>{{ $order->user?->email ?? 'Utilisateur supprimé' }}</span>
                    <span>{{ number_format($order->total, 0, ',', ' ') }} {{ $order->currency }}</span>
                    <span><em>{{ $order->status }}</em></span>
                    <span>{{ $order->payments->pluck('status')->join(', ') ?: 'Aucun' }}</span>
                    <span>{{ $order->created_at->format('d/m/Y H:i') }}</span>
                </div>
            @empty
                <p>Aucune commande.</p>
            @endforelse
        </div>

        <div class="pagination">{{ $orders->links() }}</div>
    </section>
</x-layouts.app>
