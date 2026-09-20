<x-layouts.app title="Administration | SENI-CNF EDU">
    <section class="admin-shell">
        <p class="eyebrow">Administration</p>
        <h1>Tableau de bord opérationnel</h1>
        <p class="lead">Vue rapide sur les commandes, paiements, webhooks, ressources et droits d'accès.</p>

        @include('admin.partials.nav')

        <div class="metric-grid">
            <div><span>Chiffre d'affaires</span><strong>{{ number_format($stats['revenue'], 0, ',', ' ') }} XOF</strong></div>
            <div><span>Commandes</span><strong>{{ $stats['orders'] }}</strong></div>
            <div><span>Paiements en attente</span><strong>{{ $stats['pendingPayments'] }}</strong></div>
            <div><span>Ressources</span><strong>{{ $stats['resources'] }}</strong></div>
            <div><span>Utilisateurs</span><strong>{{ $stats['users'] }}</strong></div>
            <div><span>Droits d'accès</span><strong>{{ $stats['entitlements'] }}</strong></div>
            <div><span>Téléchargements</span><strong>{{ $stats['downloads'] }}</strong></div>
            <div><span>Webhooks rejetés</span><strong>{{ $stats['webhookErrors'] }}</strong></div>
        </div>

        <div class="admin-columns">
            <section class="admin-panel">
                <div class="section-heading">
                    <h2>Commandes récentes</h2>
                    <a href="{{ route('admin.orders.index') }}">Tout voir</a>
                </div>
                @forelse ($orders as $order)
                    <div class="compact-row">
                        <span><b>{{ $order->reference }}</b><small>{{ $order->user?->email }} · {{ $order->created_at->format('d/m/Y H:i') }}</small></span>
                        <strong>{{ number_format($order->total, 0, ',', ' ') }} {{ $order->currency }}</strong>
                        <em>{{ $order->status }}</em>
                    </div>
                @empty
                    <p>Aucune commande.</p>
                @endforelse
            </section>

            <section class="admin-panel">
                <div class="section-heading">
                    <h2>Paiements récents</h2>
                    <a href="{{ route('admin.payments.index') }}">Tout voir</a>
                </div>
                @forelse ($payments as $payment)
                    <div class="compact-row">
                        <span><b>{{ $payment->internal_reference }}</b><small>{{ strtoupper($payment->provider) }} · {{ $payment->environment }}</small></span>
                        <strong>{{ number_format($payment->amount, 0, ',', ' ') }} {{ $payment->currency }}</strong>
                        <em>{{ $payment->status }}</em>
                    </div>
                @empty
                    <p>Aucun paiement.</p>
                @endforelse
            </section>

            <section class="admin-panel">
                <div class="section-heading">
                    <h2>Webhooks récents</h2>
                    <a href="{{ route('admin.webhooks.index') }}">Tout voir</a>
                </div>
                @forelse ($webhooks as $event)
                    <div class="compact-row">
                        <span><b>{{ $event->event_id }}</b><small>{{ strtoupper($event->provider) }} · {{ $event->received_at?->format('d/m/Y H:i') }}</small></span>
                        <em>{{ $event->status }}</em>
                    </div>
                @empty
                    <p>Aucun webhook reçu.</p>
                @endforelse
            </section>

            <section class="admin-panel">
                <div class="section-heading">
                    <h2>Ressources récentes</h2>
                    <a href="{{ route('admin.resources.index') }}">Tout voir</a>
                </div>
                @forelse ($resources as $resource)
                    <div class="compact-row">
                        <span><b>{{ $resource->title }}</b><small>{{ $resource->resourceType?->name ?? 'Type non défini' }} · {{ $resource->author?->email }}</small></span>
                        <strong>{{ number_format($resource->price, 0, ',', ' ') }} XOF</strong>
                    </div>
                @empty
                    <p>Aucune ressource.</p>
                @endforelse
            </section>
        </div>
    </section>
</x-layouts.app>
