<x-layouts.app title="Utilisateurs | SENI-CNF EDU">
    <section class="admin-shell">
        <p class="eyebrow">Administration</p>
        <h1>Utilisateurs</h1>
        <p class="lead">Vue de contrôle des comptes, roles et activité commerce.</p>

        @include('admin.partials.nav')

        <div class="admin-table">
            <div class="admin-table-head users-grid">
                <span>Compte</span>
                <span>Role</span>
                <span>Commandes</span>
                <span>Ressources</span>
                <span>Droits d'accès</span>
                <span>Création</span>
            </div>
            @forelse ($users as $user)
                <div class="admin-table-row users-grid">
                    <span><b>{{ $user->name }}</b><small>{{ $user->email }}</small></span>
                    <span><em>{{ $user->role ?? 'student' }}</em></span>
                    <span>{{ $user->orders_count }}</span>
                    <span>{{ $user->resources_count }}</span>
                    <span>{{ $user->entitlements_count }}</span>
                    <span>{{ $user->created_at->format('d/m/Y') }}</span>
                </div>
            @empty
                <p>Aucun utilisateur.</p>
            @endforelse
        </div>

        <div class="pagination">{{ $users->links() }}</div>
    </section>
</x-layouts.app>
