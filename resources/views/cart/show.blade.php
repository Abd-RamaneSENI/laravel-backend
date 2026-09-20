<x-layouts.app title="Panier | SENI-CNF EDU">
    @php
        $total = $cart->items->sum(fn ($item) => $item->resource->price);
    @endphp

    <section class="narrow">
        <p class="eyebrow">Votre panier</p>
        <h1>Prêt à poursuivre ?</h1>

        @forelse ($cart->items as $item)
            <div class="line-item">
                <div>
                    <strong>{{ $item->resource->title }}</strong>
                    <span>{{ $item->resource->subject?->name }}</span>
                </div>
                <strong>{{ number_format($item->resource->price, 0, ',', ' ') }} {{ $item->resource->currency }}</strong>
                <form method="POST" action="{{ route('cart.destroy', $item->resource) }}">
                    @csrf
                    @method('DELETE')
                    <button class="text-button">Retirer</button>
                </form>
            </div>
        @empty
            <p>Votre panier est vide.</p>
            <a class="button secondary" href="{{ route('application') }}">Explorer le catalogue</a>
        @endforelse

        @if ($cart->items->isNotEmpty())
            <div class="total">
                <span>Total</span>
                <strong>{{ number_format($total, 0, ',', ' ') }} XOF</strong>
            </div>
            <form method="POST" action="{{ route('checkout.store') }}">
                @csrf
                <button class="button primary">Créer la commande</button>
            </form>
        @endif
    </section>
</x-layouts.app>
