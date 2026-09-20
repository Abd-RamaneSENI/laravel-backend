<x-layouts.app title="Nouveau mot de passe | SENI-CNF EDU">
    <section class="auth-panel">
        <p class="eyebrow">Compte</p>
        <h1>Nouveau mot de passe</h1>
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label>E-mail<input type="email" name="email" value="{{ old('email', $email) }}" required autofocus></label>
            <label>Nouveau mot de passe<input type="password" name="password" required></label>
            <label>Confirmer le mot de passe<input type="password" name="password_confirmation" required></label>
            <button class="button primary">Réinitialiser</button>
        </form>
    </section>
</x-layouts.app>
