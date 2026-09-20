<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\Order;
use App\Models\Resource;
use App\Services\PdfFirstPagePreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $entitlements = $request->user()->entitlements()->with('resource')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('granted_at')->paginate(15);
        $autoDownloadEntitlements = collect();

        if ($request->filled('download_order')) {
            $order = Order::query()
                ->where('user_id', $request->user()->id)
                ->where('status', 'fulfilled')
                ->whereKey($request->integer('download_order'))
                ->first();

            if ($order) {
                $autoDownloadEntitlements = $request->user()->entitlements()
                    ->with('resource')
                    ->where('order_id', $order->id)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->get();
            }
        }

        return view('library.index', compact('entitlements', 'autoDownloadEntitlements'));
    }

    public function download(Request $request, Resource $resource)
    {
        Gate::authorize('download', $resource);
        abort_unless($resource->status === 'published' && $resource->private_path && Storage::disk('private')->exists($resource->private_path), 404);
        $entitlement = $request->user()->entitlements()->where('resource_id', $resource->id)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->firstOrFail();
        Download::create(['entitlement_id' => $entitlement->id, 'user_id' => $request->user()->id, 'resource_id' => $resource->id, 'ip_hash' => hash('sha256', (string) $request->ip()), 'user_agent' => substr((string) $request->userAgent(), 0, 500), 'downloaded_at' => now()]);
        $resource->increment('download_count');

        return Storage::disk('private')->download($resource->private_path, $resource->original_filename);
    }

    public function read(Request $request, Resource $resource, PdfFirstPagePreview $renderer)
    {
        Gate::authorize('download', $resource);
        abort_unless($this->isReadablePdf($resource), 404);
        abort_unless($resource->status === 'published' && $resource->private_path && Storage::disk('private')->exists($resource->private_path), 404);

        $pageCount = $renderer->pageCount($resource->private_path) ?: max(1, (int) ($resource->page_count ?: 1));
        $pageCount = min($pageCount, 250);
        $readerVersion = $resource->checksum ?: ($resource->updated_at?->timestamp ?? $resource->id);

        return view('library.reader', compact('resource', 'pageCount', 'readerVersion'));
    }

    public function inline(Request $request, Resource $resource)
    {
        Gate::authorize('download', $resource);
        abort_unless($this->isReadablePdf($resource), 404);
        abort_unless($resource->status === 'published' && $resource->private_path && Storage::disk('private')->exists($resource->private_path), 404);

        $filename = $resource->original_filename ?: Str::slug($resource->title).'.pdf';
        $filename = str_replace(['"', '\\'], '', Str::ascii($filename));

        return response()->file(Storage::disk('private')->path($resource->private_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function page(Request $request, Resource $resource, int $page, PdfFirstPagePreview $renderer)
    {
        Gate::authorize('download', $resource);
        abort_unless($this->isReadablePdf($resource), 404);
        abort_unless($resource->status === 'published' && $resource->private_path && Storage::disk('private')->exists($resource->private_path), 404);
        abort_if($page < 1 || $page > 250, 404);

        $pagePath = $renderer->pagePath($resource->private_path, 'reader-resource-'.$resource->id, $page, 'reader-pages', 150);
        abort_unless($pagePath && Storage::disk('private')->exists($pagePath), 404);

        return response()->file(Storage::disk('private')->path($pagePath), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function isReadablePdf(Resource $resource): bool
    {
        return $resource->mime_type === 'application/pdf'
            || Str::endsWith(Str::lower((string) $resource->original_filename), '.pdf')
            || Str::endsWith(Str::lower((string) $resource->private_path), '.pdf');
    }
}
