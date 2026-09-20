<x-layouts.app title="Mot de passe oublié | SENI-CNF EDU">
    <section class="auth-panel">
        <p class="eyebrow">Compte</p>
        <h1>Récupérer le mot de passe</h1>
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label>E-mail du compte<input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
            <button class="button primary">Envoyer le lien</button>
        </form>
        <p><a href="{{ route('login') }}">Retour à la connexion</a></p>
    </section>
</x-layouts.app>
