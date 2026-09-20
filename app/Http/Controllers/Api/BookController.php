<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookPurchase;
use App\Models\Order;
use App\Models\ReadingDocument;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $books = Book::query()->with(['author:id,name', 'category:id,name'])
            ->when(! $request->user()?->isAdmin(), fn ($query) => $query->where('is_active', true))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim((string) $request->input('q'));
                $query->where(fn ($where) => $where->whereLike('title', "%{$term}%")
                    ->orWhereLike('isbn', "%{$term}%")
                    ->orWhereHas('author', fn ($author) => $author->whereLike('name', "%{$term}%")));
            })
            ->when($request->filled('category'), fn ($query) => $query->where('book_category_id', $request->integer('category')))
            ->when($request->boolean('available'), fn ($query) => $query->where('available_copies', '>', 0))
            ->orderBy('title')
            ->paginate(min(max($request->integer('per_page', 12), 1), 50));

        return response()->json($books);
    }

    public function show(Book $book)
    {
        abort_unless($book->is_active, 404);

        return response()->json(['book' => $book->load(['author', 'category'])]);
    }

    public function purchase(Request $request, Book $book, AuditLogger $audit)
    {
        abort_unless($book->is_active, 404);
        abort_unless($this->downloadableDocument($book), 422, 'Ce livre ne possède pas encore de fichier numérique disponible à l’achat.');

        $purchase = BookPurchase::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->first();

        if ($purchase) {
            return response()->json([
                'already_paid' => true,
                'purchase' => $purchase,
                'download_url' => route('books.download', $book),
            ]);
        }

        $order = DB::transaction(function () use ($request, $book): Order {
            $order = Order::query()
                ->where('user_id', $request->user()->id)
                ->where('purpose', 'book_purchase')
                ->where('book_id', $book->id)
                ->whereIn('status', ['pending', 'awaiting_payment'])
                ->lockForUpdate()
                ->first();

            if ($order) {
                return $order;
            }

            return Order::create([
                'user_id' => $request->user()->id,
                'book_id' => $book->id,
                'purpose' => 'book_purchase',
                'reference' => 'LIV-'.strtoupper(Str::random(14)),
                'subtotal' => $book->price,
                'discount' => 0,
                'total' => $book->price,
                'currency' => config('payments.currency', 'XOF'),
                'status' => 'pending',
            ]);
        });

        $audit->record($request, 'book.purchase_requested', $book, [
            'order_id' => $order->id,
            'amount' => $order->total,
        ]);

        return response()->json([
            'order' => $order->load('book'),
            'payment_url' => route('orders.show', $order),
            'download_url' => null,
        ]);
    }

    public function downloadPurchased(Request $request, Book $book)
    {
        abort_unless($book->is_active || $request->user()->isAdmin(), 404);
        abort_unless(
            $request->user()->isAdmin()
                || BookPurchase::query()->where('user_id', $request->user()->id)->where('book_id', $book->id)->exists(),
            403,
            'Ce livre doit être acheté avant son téléchargement.'
        );

        $document = $this->downloadableDocument($book);
        abort_unless($document && Storage::disk('private')->exists($document->private_path), 404);

        $filename = $document->original_filename ?: Str::slug($book->title).'.pdf';
        $filename = str_replace(['"', '\\'], '', Str::ascii($filename));

        return Storage::disk('private')->download($document->private_path, $filename);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $book = Book::create($this->validated($request));
        $audit->record($request, 'book.created', $book, ['title' => $book->title]);

        return response()->json(['book' => $book->load(['author', 'category'])], 201);
    }

    public function update(Request $request, Book $book, AuditLogger $audit)
    {
        $data = $this->validated($request, $book);
        if (array_key_exists('total_copies', $data)) {
            $activeLoans = $book->loans()->whereIn('status', ['borrowed', 'overdue'])->count();
            abort_if($data['total_copies'] < $activeLoans, 422, 'Le nombre total ne peut pas être inférieur aux emprunts actifs.');
            $data['available_copies'] = $data['total_copies'] - $activeLoans;
        }
        $book->update($data);
        $audit->record($request, 'book.updated', $book, ['title' => $book->title]);

        return response()->json(['book' => $book->fresh()->load(['author', 'category'])]);
    }

    public function destroy(Request $request, Book $book, AuditLogger $audit)
    {
        abort_if($book->loans()->whereIn('status', ['borrowed', 'overdue'])->exists(), 422, 'Un livre emprunté ne peut pas être désactivé.');
        $book->update(['is_active' => false]);
        $audit->record($request, 'book.deactivated', $book, ['title' => $book->title]);

        return response()->noContent();
    }

    private function validated(Request $request, ?Book $book = null): array
    {
        $data = $request->validate([
            'title' => [$book ? 'sometimes' : 'required', 'string', 'max:255'],
            'author_id' => [$book ? 'sometimes' : 'required', 'integer', 'exists:authors,id'],
            'book_category_id' => [$book ? 'sometimes' : 'required', 'integer', 'exists:book_categories,id'],
            'isbn' => ['nullable', 'string', 'max:32', Rule::unique('books', 'isbn')->ignore($book?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_url' => ['nullable', 'url', 'max:2048'],
            'published_year' => ['nullable', 'integer', 'between:1500,'.(now()->year + 1)],
            'shelf_location' => ['nullable', 'string', 'max:64'],
            'price' => ['nullable', 'integer', 'between:0,100000000'],
            'total_copies' => [$book ? 'sometimes' : 'required', 'integer', 'between:1,10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['price'] = $data['price'] ?? $book?->price ?? 10000;

        if (! $book) {
            $data['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(6));
            $data['available_copies'] = $data['total_copies'];
        }

        return $data;
    }

    private function downloadableDocument(Book $book): ?ReadingDocument
    {
        return $book->readingDocuments()
            ->where('is_active', true)
            ->whereNotNull('private_path')
            ->latest()
            ->first();
    }
}
