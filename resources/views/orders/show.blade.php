<x-layouts.app :title="$order->reference.' | SENI-CNF EDU'">
    @php($isLoanFee = $order->purpose === 'loan_borrowing_fee')
    @php($isBookPurchase = $order->purpose === 'book_purchase')
    <section class="narrow">
        <p class="eyebrow">{{ $isLoanFee ? 'Frais d’emprunt' : ($isBookPurchase ? 'Achat numérique' : 'Commande') }} {{ $order->reference }}</p>
        <h1>{{ $order->status === 'fulfilled' ? ($isLoanFee ? 'Votre emprunt est activé.' : ($isBookPurchase ? 'Votre livre numérique est prêt.' : 'Votre bibliothèque est prête.')) : 'En attente de paiement.' }}</h1>

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
                <strong>{{ number_format($item->total, 0, ',', ' ') }} XOF</strong>
            </div>
        @endforeach

        <div class="total">
            <span>Total à régler</span>
            <strong>{{ number_format($order->total, 0, ',', ' ') }} {{ $order->currency }}</strong>
        </div>

        @if (in_array($order->status, ['pending', 'awaiting_payment'], true))
            <div class="payment-options">
                <p>Choisissez un moyen de paiement. Le document ne sera débloqué qu’après confirmation réelle du serveur MTN/Moov ou du webhook provider.</p>
                <form class="payment-provider-form unified-payment-form" method="POST" action="{{ route('payments.initiate.selected', $order) }}">
                    @csrf
                    <fieldset class="payment-provider-choice">
                        <legend>Moyen de paiement</legend>
                        @foreach (config('payments.enabled') as $provider)
                            <label>
                                <input type="radio" name="provider" value="{{ $provider }}" @checked(old('provider', config('payments.default')) === $provider) required>
                                {{ strtoupper($provider) }}
                            </label>
                        @endforeach
                    </fieldset>
                    <label>
                        Nom et prénom pour la facture
                        <input name="billing_name" autocomplete="name" value="{{ old('billing_name', $order->billing_name ?: $order->user?->name) }}" required>
                    </label>
                    <label>
                        E-mail pour recevoir la facture
                        <input name="billing_email" type="email" autocomplete="email" value="{{ old('billing_email', $order->billing_email ?: $order->user?->email) }}" required>
                    </label>
                    <label>
                        Numéro Mobile Money du payeur
                        <input name="payer_phone" inputmode="tel" autocomplete="tel" placeholder="Obligatoire pour MTN ou Moov">
                    </label>
                    <button class="button primary">Continuer le paiement</button>
                </form>
            </div>
        @else
            @php($mailDeliveryIsSimulated = ! app()->environment('testing') && in_array((string) config('mail.default'), ['array', 'log'], true))
            <div class="payment-options">
                <a class="button secondary" href="{{ route('orders.invoice', $order) }}">Ouvrir la facture</a>
                @if ($order->billing_email)
                    <form method="POST" action="{{ route('orders.invoice.resend', $order) }}">
                        @csrf
                        <button class="button secondary">Envoyer la facture à mon e-mail</button>
                    </form>
                @endif
                @if ($isLoanFee)
                    <p>Votre paiement est confirmé. Le document lié à cet emprunt est disponible pendant 30 jours dans la page Mes emprunts.</p>
                    <a class="button primary" href="{{ route('application') }}">Ouvrir mes emprunts</a>
                @elseif ($isBookPurchase && $order->book)
                    <p>Votre paiement est confirmé. Le fichier numérique est maintenant disponible sur votre appareil.</p>
                    <a class="button primary" href="{{ route('books.download', $order->book) }}">Télécharger le livre</a>
                    <a class="button secondary" href="{{ route('application') }}">Revenir au catalogue</a>
                    @if ($autoDownloadBook)
                        <div class="auto-download-notice" role="status">Téléchargement du livre en cours. Si rien ne démarre, utilisez le bouton Télécharger le livre.</div>
                        <script>
                            window.addEventListener('DOMContentLoaded', () => {
                                const frame = document.createElement('iframe');
                                frame.src = @json(route('books.download', $order->book));
                                frame.hidden = true;
                                frame.setAttribute('aria-hidden', 'true');
                                document.body.appendChild(frame);
                            });
                        </script>
                    @endif
                @else
                    <p>Votre paiement est confirmé. Les ressources sont disponibles dans votre bibliothèque.</p>
                    <a class="button primary" href="{{ route('library.index', ['download_order' => $order->id]) }}">Ouvrir mes ressources</a>
                    @foreach ($order->items as $item)
                        @if ($item->resource)
                            @if ($item->resource->mime_type === 'application/pdf' || str($item->resource->original_filename ?: $item->resource->private_path)->lower()->endsWith('.pdf'))
                                <a class="button secondary" href="{{ route('resources.read', $item->resource) }}">Lire {{ $item->resource->title }}</a>
                            @endif
                            <a class="button secondary" href="{{ route('resources.download', $item->resource) }}">Télécharger {{ $item->resource->title }}</a>
                        @endif
                    @endforeach
                @endif
                @if ($mailDeliveryIsSimulated)
                    <p class="note warning-note">Mode e-mail actuel : {{ config('mail.default') }}. La facture est consultable et imprimable ici, mais aucun e-mail réel ne sera remis tant que SMTP n'est pas configuré.</p>
                @elseif ($order->invoice_sent_at)
                    <p class="note">Facture envoyée à {{ $order->billing_email }} le {{ $order->invoice_sent_at->format('d/m/Y à H:i') }}.</p>
                @elseif ($order->billing_email)
                    @php($invoiceSmtpBlocked = \Illuminate\Support\Str::contains((string) $order->invoice_email_error, ['socket', 'smtp.gmail.com', '10013']))
                    @if ($invoiceSmtpBlocked)
                        <p class="note warning-note">Le paiement est bien confirmé, mais Windows bloque la connexion SMTP vers Gmail. Autorisez PHP/Apache à sortir vers <strong>smtp.gmail.com:587</strong>, puis cliquez sur « Envoyer la facture à mon e-mail ». Aucun nouveau paiement n'est nécessaire.</p>
                    @else
                        <p class="note warning-note">
                            La facture n'a pas encore pu être envoyée par e-mail.
                            @if ($order->invoice_email_last_attempt_at)
                                Dernière tentative le {{ $order->invoice_email_last_attempt_at->format('d/m/Y à H:i') }}.
                            @endif
                            Utilisez le bouton de renvoi après vérification de SMTP.
                        </p>
                    @endif
                @endif
            </div>
        @endif

        @if ($order->payments->isNotEmpty())
            @php($lastPayment = $order->payments->last())
            <p class="note">
                Dernier statut de paiement : {{ $lastPayment->status }}
                @if ($lastPayment->external_reference)
                    · Référence provider : {{ $lastPayment->external_reference }}
                @endif
            </p>
        @endif
    </section>
</x-layouts.app>
