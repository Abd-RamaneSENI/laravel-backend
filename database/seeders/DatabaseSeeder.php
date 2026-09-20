<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\Cycle;
use App\Models\ReadingDocument;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = Hash::make('password2026');
        $admin = User::factory()->create(['name' => 'Administrateur SENI-CNF', 'email' => 'senicnf@gmail.com', 'role' => 'admin', 'password' => $password]);
        $author = User::factory()->create(['name' => 'Auteur SENI-CNF', 'email' => 'auteur.senicnf@gmail.com', 'role' => 'vendor']);
        User::factory()->create(['name' => 'Utilisateur d’essai', 'email' => 'seniramane@gmail.com', 'role' => 'student', 'password' => $password]);
        $cycles = collect(['Primaire' => ['CI', 'CP', 'CE1', 'CE2', 'CM1', 'CM2'], 'Collège' => ['6e', '5e', '4e', '3e'], 'Lycée' => ['Seconde', 'Première', 'Terminale'], 'Université' => ['Licence 1', 'Licence 2', 'Licence 3']])->map(function ($classes, $name) {
            $cycle = Cycle::create(['name' => $name, 'slug' => str($name)->slug(), 'sort_order' => Cycle::count() + 1]);
            foreach ($classes as $position => $class) {
                SchoolClass::create(['cycle_id' => $cycle->id, 'name' => $class, 'slug' => str($class)->slug(), 'sort_order' => $position + 1]);
            }

            return $cycle;
        });
        $subjects = collect(['Français', 'Mathématiques', 'Anglais', 'Histoire-Géographie', 'SVT', 'Informatique'])->map(fn ($name) => Subject::create(['name' => $name, 'slug' => str($name)->slug()]));
        $types = collect(['Cours', 'Exercices', 'Corrigés', 'Annales', 'Fiches de révision'])->map(fn ($name) => ResourceType::create(['name' => $name, 'slug' => str($name)->slug()]));
        foreach ([['Révisions Mathématiques - Terminale', 1500], ['Méthode de dissertation française - Première', 1000], ['Algorithmique fondamentale - Licence 1', 2000], ['Annales SVT - 3e', 800]] as $index => [$title, $price]) {
            $pdf = $this->samplePdf($title);
            $filename = str($title)->slug().'.pdf';
            $path = 'resources/catalogue/'.$filename;
            Storage::disk('private')->put($path, $pdf);

            Resource::create(['author_id' => $author->id, 'school_class_id' => SchoolClass::orderBy('id')->skip($index + 2)->first()->id, 'subject_id' => $subjects[$index % $subjects->count()]->id, 'resource_type_id' => $types[$index % $types->count()]->id, 'title' => $title, 'slug' => str($title)->slug(), 'description' => 'Ressource pédagogique autorisée. Seuls les contenus dont la diffusion est autorisée peuvent être publiés.', 'price' => $price, 'status' => 'published', 'visibility' => 'public', 'academic_year' => '2026-2027', 'private_path' => $path, 'original_filename' => $filename, 'mime_type' => 'application/pdf', 'file_size' => strlen($pdf), 'checksum' => hash('sha256', $pdf), 'page_count' => 1, 'rights_statement' => 'Contenu pédagogique autorisé.', 'published_at' => now()]);
        }
        $authors = collect([
            ['name' => 'Mariama Koné', 'biography' => 'Autrice de ressources pédagogiques.'],
            ['name' => 'Yao Kouassi', 'biography' => 'Enseignant et auteur de supports scolaires.'],
            ['name' => 'Fatou Diallo', 'biography' => 'Spécialiste des sciences et techniques.'],
        ])->map(fn ($data) => Author::create([...$data, 'slug' => str($data['name'])->slug()]));
        $categories = collect(['Sciences', 'Lettres', 'Informatique', 'Préparation aux examens'])->map(fn ($name) => BookCategory::create(['name' => $name, 'slug' => str($name)->slug(), 'description' => "Ouvrages de {$name}."]));
        $seededBooks = collect();
        foreach ([
            ['Méthodes de mathématiques', 0, 0, 4, 2, 'SCI-A-01', 12000],
            ['Réussir son mémoire', 1, 1, 3, 1, 'LET-B-12', 9000],
            ['Initiation à l’algorithmique', 2, 2, 2, 0, 'INF-C-04', 15000],
            ['Annales de sciences physiques', 0, 3, 5, 3, 'EXA-D-07', 8000],
            ['Techniques de rédaction', 1, 1, 2, 2, 'LET-B-06', 7000],
            ['Bases de données relationnelles', 2, 2, 3, 1, 'INF-C-11', 14000],
        ] as $index => [$title, $authorIndex, $categoryIndex, $total, $available, $shelf, $price]) {
            $seededBooks->push(Book::create(['author_id' => $authors[$authorIndex]->id, 'book_category_id' => $categories[$categoryIndex]->id, 'title' => $title, 'slug' => str($title)->slug(), 'isbn' => '97800000000'.($index + 1), 'description' => 'Ouvrage sélectionné pour le catalogue, les emprunts et les réservations.', 'price' => $price, 'published_year' => 2026, 'shelf_location' => $shelf, 'total_copies' => $total, 'available_copies' => $available]));
        }

        $this->seedReadingDocumentForBook($admin, $seededBooks->firstWhere('title', 'Méthodes de mathématiques'));
    }

    private function samplePdf(string $title): string
    {
        $safeTitle = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) str($title)->ascii());
        $stream = "BT /F1 22 Tf 72 730 Td (SENI-CNF EDU) Tj 0 -42 Td /F1 15 Tf ({$safeTitle}) Tj 0 -30 Td (Document PDF autorise.) Tj 0 -24 Td (Apres achat, il peut etre lu en ligne, telecharge et imprime.) Tj ET";
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >> endobj',
            '4 0 obj << /Length '.strlen($stream).' >> stream'."\n".$stream."\n".'endstream endobj',
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer << /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    private function seedReadingDocumentForBook(User $admin, ?Book $book): void
    {
        if (! $book) {
            return;
        }

        $pdf = $this->samplePdf('Document numerique '.$book->title);
        $filename = str($book->title)->slug().'-lecture.pdf';
        $path = 'reading-documents/sample/'.$filename;
        Storage::disk('private')->put($path, $pdf);

        ReadingDocument::create([
            'uploaded_by' => $admin->id,
            'book_id' => $book->id,
            'title' => 'Version numérique - '.$book->title,
            'description' => 'PDF attaché au livre pour la lecture en ligne, l’emprunt payant à 5 % et l’achat complet avec téléchargement.',
            'private_path' => $path,
            'original_filename' => $filename,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdf),
            'is_active' => true,
        ]);
    }
}
