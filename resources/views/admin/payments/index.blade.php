<x-layouts.app title="Paiements | SENI-CNF EDU">
    <section class="admin-shell">
        <p class="eyebrow">Administration</p>
        <h1>Paiements</h1>
        <p class="lead">Configuration active, transactions et rapprochement de base sans affichage de secrets.</p>

        @include('admin.partials.nav')

        <div class="config-strip">
            <span><b>Environnement</b>{{ strtoupper($environment) }}</span>
            <span><b>Provider par défaut</b>{{ strtoupper($defaultProvider) }}</span>
            <span><b>Providers actifs</b>{{ collect($enabledProviders)->map(fn ($provider) => strtoupper($provider))->join(', ') }}</span>
        </div>

        <div class="admin-table">
            <div class="admin-table-head payments-grid">
                <span>Référence interne</span>
                <span>Provider</span>
                <span>Commande</span>
                <span>Montant</span>
                <span>Statut</span>
                <span>Contrôle</span>
            </div>
            @forelse ($payments as $payment)
                <div class="admin-table-row payments-grid">
                    <span><b>{{ $payment->internal_reference }}</b><small>{{ $payment->external_reference ?: 'Sans référence externe' }}</small></span>
                    <span>{{ strtoupper($payment->provider) }} · {{ $payment->environment }}</span>
                    <span>{{ $payment->order?->reference ?? 'Commande supprimée' }}</span>
                    <span>{{ number_format($payment->amount, 0, ',', ' ') }} {{ $payment->currency }}</span>
                    <span><em>{{ $payment->status }}</em></span>
                    <span>
                        {{ $payment->paid_at?->format('d/m/Y H:i') ?? '-' }}
                        @if ($payment->status_checked_at)
                            <small>Vérifié {{ $payment->status_checked_at->format('d/m/Y H:i') }}</small>
                        @endif
                    </span>
                </div>
            @empty
                <p>Aucun paiement.</p>
            @endforelse
        </div>

        <div class="pagination">{{ $payments->links() }}</div>
    </section>
</x-layouts.app>
