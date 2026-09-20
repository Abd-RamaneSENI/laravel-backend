<x-layouts.app title="Administration des ressources | SENI-CNF EDU">
    <section class="narrow admin-resources">
        <p class="eyebrow">Administration</p>
        <h1>Ajouter une ressource payante</h1>
        <p class="lead">Le fichier sera enregistré hors du dossier public. Seul un acheteur dont le paiement a été confirmé pourra le télécharger.</p>

        @include('admin.partials.nav')

        <form class="upload-form" method="POST" action="{{ route('admin.resources.store') }}" enctype="multipart/form-data">
            @csrf
            <label>
                Titre
                <input name="title" value="{{ old('title') }}" required maxlength="255">
            </label>
            <label>
                Type de ressource
                <select name="resource_type_id" required>
                    <option value="">Sélectionner</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected(old('resource_type_id') == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Classe ou niveau
                <select name="school_class_id">
                    <option value="">Non précisé</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(old('school_class_id') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Matière
                <select name="subject_id">
                    <option value="">Non précisée</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Année scolaire
                <input name="academic_year" value="{{ old('academic_year') }}" placeholder="2026-2027" maxlength="16">
            </label>
            <label>
                Prix en XOF
                <input name="price" type="number" value="{{ old('price') }}" min="0" max="10000000" required>
            </label>
            <label class="full-width">
                Description
                <textarea name="description" rows="4" required maxlength="5000">{{ old('description') }}</textarea>
            </label>
            <label class="full-width">
                Déclaration des droits de diffusion
                <textarea name="rights_statement" rows="3" required maxlength="2000" placeholder="Ex. Je suis l'auteur ou j'ai l'autorisation écrite de diffuser ce document.">{{ old('rights_statement') }}</textarea>
            </label>
            <label class="full-width">
                Fichier à vendre
                <input name="file" type="file" required accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx">
                <small>PDF, Word, PowerPoint ou Excel; taille maximale 30 Mo.</small>
            </label>
            <div class="full-width">
                <button class="button primary">Ajouter et publier</button>
            </div>
        </form>

        <div class="section-heading admin-list-heading">
            <div>
                <p class="eyebrow">Catalogue interne</p>
                <h2>Ressources publiées</h2>
            </div>
        </div>
        @forelse ($resources as $resource)
            <div class="order-row">
                <span>
                    <b>{{ $resource->title }}</b>
                    <small>{{ $resource->resourceType?->name }} · {{ $resource->original_filename ?: 'Sans fichier' }}</small>
                </span>
                <span>{{ $resource->status }}</span>
                <strong>{{ number_format($resource->price, 0, ',', ' ') }} XOF</strong>
                <a class="button secondary" href="{{ route('admin.resources.edit', $resource) }}">Modifier</a>
            </div>
        @empty
            <p>Aucune ressource publiée.</p>
        @endforelse
        <div class="pagination">{{ $resources->links() }}</div>
    </section>
</x-layouts.app>
