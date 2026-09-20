<x-layouts.app :title="'Facture '.$order->reference.' | SENI-CNF EDU'">
    @php($isLoanFee = $order->purpose === 'loan_borrowing_fee')
    @php($isBookPurchase = $order->purpose === 'book_purchase')
    <section class="narrow invoice">
        <p class="eyebrow">Facture</p>
        <h1>{{ $order->reference }}</h1>

        <div class="invoice-meta">
            <div>
                <strong>SENI-CNF EDU</strong>
                <span>Ressources éducatives numériques</span>
            </div>
            <div>
                <strong>{{ $order->billing_name ?: $order->user?->name }}</strong>
                <span>{{ $order->billing_email ?: $order->user?->email }}</span>
            </div>
            <div>
                <strong>Date</strong>
                <span>{{ ($order->paid_at ?: $order->created_at)->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        @if ($isLoanFee && $order->loan?->book)
            <div class="line-item">
                <span>Frais d'emprunt 5 % · {{ $order->loan->book->title }}</span>
                <strong>{{ number_format($order->total, 0, ',', ' ') }} {{ $order->currency }}</strong>
            </div>
        @endif

        @if ($isBookPurchase && $order->book)
            <div class="line-item">
                <span>Achat du livre numérique · {{ $order->book->title }}</span>
                <strong>{{ number_format($order->total, 0, ',', ' ') }} {{ $order->currency }}</strong>
            </div>
        @endif

        @foreach ($order->items as $item)
            <div class="line-item">
                <span>{{ $item->title_snapshot }}</span>
                <strong>{{ number_format($item->total, 0, ',', ' ') }} {{ $order->currency }}</strong>
            </div>
        @endforeach

        <div class="total">
            <span>Total payé</span>
            <strong>{{ number_format($order->total, 0, ',', ' ') }} {{ $order->currency }}</strong>
        </div>

        <p class="note">Paiement confirmé par le serveur. Référence provider : {{ $order->payments->last()?->external_reference ?: $order->payments->last()?->internal_reference ?: 'Non renseignée' }}.</p>
        @if ($order->invoice_sent_at)
            <p class="note">Facture envoyée à {{ $order->billing_email }} le {{ $order->invoice_sent_at->format('d/m/Y à H:i') }}.</p>
        @elseif ($order->billing_email)
            @php($invoiceSmtpBlocked = \Illuminate\Support\Str::contains((string) $order->invoice_email_error, ['socket', 'smtp.gmail.com', '10013']))
            @if ($invoiceSmtpBlocked)
                <p class="note warning-note">Le paiement est confirmé, mais l'envoi e-mail attend l'accès SMTP vers <strong>smtp.gmail.com:587</strong>. Aucun nouveau paiement n'est nécessaire.</p>
            @else
                <p class="note warning-note">La facture n'a pas encore été envoyée à {{ $order->billing_email }}. Utilisez le bouton d'envoi après vérification SMTP.</p>
            @endif
        @endif
        <button class="button primary" type="button" onclick="window.print()">Imprimer la facture</button>
        @if ($order->billing_email)
            <form method="POST" action="{{ route('orders.invoice.resend', $order) }}" style="display:inline-flex;margin-left:8px">
                @csrf
                <button class="button secondary">Envoyer à mon e-mail</button>
            </form>
        @endif
    </section>
</x-layouts.app>
