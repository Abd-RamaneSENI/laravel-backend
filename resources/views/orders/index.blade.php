<x-layouts.app title="Mes commandes | SENI-CNF EDU">
    <section class="narrow">
        <p class="eyebrow">Compte</p>
        <h1>Mes commandes</h1>

        @forelse ($orders as $order)
            <a class="order-row" href="{{ route('orders.show', $order) }}">
                <span>
                    <b>{{ $order->reference }}</b>
                    <small>{{ $order->created_at->format('d/m/Y') }}</small>
                </span>
                <span>{{ ucfirst($order->status) }}</span>
                <strong>{{ number_format($order->total, 0, ',', ' ') }} XOF</strong>
            </a>
        @empty
            <p>Aucune commande pour le moment.</p>
        @endforelse
    </section>
</x-layouts.app>
