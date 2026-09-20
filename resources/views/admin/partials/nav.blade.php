<nav class="admin-tabs" aria-label="Administration">
    <a @class(['active' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}">Dashboard</a>
    <a @class(['active' => request()->routeIs('admin.resources.*')]) href="{{ route('admin.resources.index') }}">Ressources</a>
    <a @class(['active' => request()->routeIs('admin.orders.*')]) href="{{ route('admin.orders.index') }}">Commandes</a>
    <a @class(['active' => request()->routeIs('admin.payments.*')]) href="{{ route('admin.payments.index') }}">Paiements</a>
    <a @class(['active' => request()->routeIs('admin.webhooks.*')]) href="{{ route('admin.webhooks.index') }}">Webhooks</a>
    <a @class(['active' => request()->routeIs('admin.users.*')]) href="{{ route('admin.users.index') }}">Utilisateurs</a>
</nav>
