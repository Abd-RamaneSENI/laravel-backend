<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReadingDocument;
use App\Services\PdfFirstPagePreview;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReadingDocumentController extends Controller
{
    public function index(Request $request)
    {
        $documents = ReadingDocument::query()
            ->whereNotNull('book_id')
            ->when(! $request->user()?->isAdmin(), fn ($query) => $query->where('is_active', true))
            ->latest()
            ->with('book:id,title')
            ->get(['id', 'book_id', 'title', 'description', 'original_filename', 'file_size', 'is_active', 'created_at', 'updated_at'])
            ->map(fn (ReadingDocument $document) => [
                'id' => $document->id,
                'book_id' => $document->book_id,
                'title' => $document->title,
                'description' => $document->description,
                'original_filename' => $document->original_filename,
                'file_size' => $document->file_size,
                'is_active' => $document->is_active,
                'created_at' => $document->created_at,
                'book' => $document->book,
                'can_read' => $request->user() !== null && $document->isReadableBy($request->user()),
                'preview_url' => route('api.reading-documents.preview', [
                    'document' => $document,
                    'v' => $document->updated_at?->timestamp ?? $document->id,
                ]),
            ]);

        return response()->json(['documents' => $documents]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:30720'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $file = $data['file'];
        $path = $file->storeAs(
            'reading-documents/'.now()->format('Y/m'),
            Str::uuid().'.pdf',
            'private'
        );

        try {
            $document = ReadingDocument::create([
                'uploaded_by' => $request->user()->id,
                'book_id' => $data['book_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'private_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'is_active' => $request->boolean('is_active', true),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }

        $audit->record($request, 'reading_document.created', $document, ['title' => $document->title, 'book_id' => $document->book_id]);

        return response()->json(['document' => $document], 201);
    }

    public function read(Request $request, ReadingDocument $document)
    {
        abort_unless($document->is_active || $request->user()->isAdmin(), 404);
        abort_unless($document->isReadableBy($request->user()), 403, 'Empruntez ce livre et payez les frais de 5 % pour lire ce document.');
        abort_unless(Storage::disk('private')->exists($document->private_path), 404);

        return response()->file(Storage::disk('private')->path($document->private_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($document->original_filename).'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reader(Request $request, ReadingDocument $document, PdfFirstPagePreview $preview)
    {
        abort_unless($document->is_active || $request->user()->isAdmin(), 404);
        abort_unless($document->isReadableBy($request->user()), 403, 'Empruntez ce livre et payez les frais de 5 % pour lire ce document.');
        abort_unless(Storage::disk('private')->exists($document->private_path), 404);

        $pageCount = $preview->pageCount($document->private_path) ?: 1;
        $pageCount = min($pageCount, 250);
        $version = $document->updated_at?->timestamp ?? $document->id;

        return response()->json([
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'page_count' => $pageCount,
                'pages' => collect(range(1, $pageCount))->map(fn (int $page) => [
                    'number' => $page,
                    'url' => route('api.reading-documents.page', [
                        'document' => $document,
                        'page' => $page,
                        'v' => $version,
                    ]),
                ])->all(),
            ],
        ]);
    }

    public function page(Request $request, ReadingDocument $document, int $page, PdfFirstPagePreview $preview)
    {
        abort_unless($document->is_active || $request->user()->isAdmin(), 404);
        abort_unless($document->isReadableBy($request->user()), 403, 'Empruntez ce livre et payez les frais de 5 % pour lire ce document.');
        abort_unless(Storage::disk('private')->exists($document->private_path), 404);
        abort_if($page < 1 || $page > 250, 404);

        $pagePath = $preview->pagePath($document->private_path, 'reading-document-reader-'.$document->id, $page, 'reader-pages', 150);
        abort_unless($pagePath && Storage::disk('private')->exists($pagePath), 404);

        return response()->file(Storage::disk('private')->path($pagePath), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Request $request, ReadingDocument $document, PdfFirstPagePreview $preview)
    {
        abort_unless($document->is_active || $request->user()?->isAdmin(), 404);
        abort_unless(Storage::disk('private')->exists($document->private_path), 404);

        $previewPath = $preview->previewPath($document->private_path, 'reading-document-'.$document->id);
        abort_unless($previewPath && Storage::disk('private')->exists($previewPath), 404);

        return response()->file(Storage::disk('private')->path($previewPath), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
