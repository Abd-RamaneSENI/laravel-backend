<x-layouts.app :title="'Modifier '.$resource->title.' | SENI-CNF EDU'">
    <section class="narrow admin-resources">
        <p class="eyebrow">Administration</p>
        <h1>Modifier la ressource</h1>
        <p class="lead">Les clients ayant déjà acheté cette ressource gardent leur droit de téléchargement. Remplacez le fichier seulement lorsqu’une nouvelle version doit être distribuée.</p>

        @include('admin.partials.nav')

        <form class="upload-form" method="POST" action="{{ route('admin.resources.update', $resource) }}" enctype="multipart/form-data">
            @csrf
            @method('PATCH')
            <label>
                Titre
                <input name="title" value="{{ old('title', $resource->title) }}" required maxlength="255">
            </label>
            <label>
                Statut
                <select name="status" required>
                    @foreach (['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $resource->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Type de ressource
                <select name="resource_type_id" required>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected(old('resource_type_id', $resource->resource_type_id) == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Classe ou niveau
                <select name="school_class_id">
                    <option value="">Non précisé</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(old('school_class_id', $resource->school_class_id) == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Matière
                <select name="subject_id">
                    <option value="">Non précisée</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(old('subject_id', $resource->subject_id) == $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Année scolaire
                <input name="academic_year" value="{{ old('academic_year', $resource->academic_year) }}" maxlength="16">
            </label>
            <label>
                Prix en XOF
                <input name="price" type="number" value="{{ old('price', $resource->price) }}" min="0" max="10000000" required>
            </label>
            <label class="full-width">
                Description
                <textarea name="description" rows="4" required maxlength="5000">{{ old('description', $resource->description) }}</textarea>
            </label>
            <label class="full-width">
                Déclaration des droits de diffusion
                <textarea name="rights_statement" rows="3" required maxlength="2000">{{ old('rights_statement', $resource->rights_statement) }}</textarea>
            </label>
            <label class="full-width">
                Remplacer le fichier (facultatif)
                <input name="file" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx">
                <small>Fichier actuel : {{ $resource->original_filename ?: 'aucun fichier associé' }}. Un nouveau fichier remplace l’ancien dans le stockage privé.</small>
            </label>
            <div class="full-width form-actions">
                <a class="button secondary" href="{{ route('admin.resources.index') }}">Annuler</a>
                <button class="button primary">Enregistrer les modifications</button>
            </div>
        </form>
    </section>
</x-layouts.app>
