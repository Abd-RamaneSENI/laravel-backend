<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Services\PdfFirstPagePreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('application', $request->only(['q', 'cycle']));
    }

    public function show(Resource $resource)
    {
        abort_unless($resource->status === 'published' && $resource->visibility === 'public', 404);
        $resource->increment('view_count');

        return view('catalog.show', compact('resource'));
    }

    public function preview(Resource $resource, PdfFirstPagePreview $preview)
    {
        abort_unless($resource->status === 'published' && $resource->visibility === 'public', 404);
        abort_unless($this->isPdf($resource) && $resource->private_path, 404);

        $previewPath = $preview->previewPath($resource->private_path, 'resource-'.$resource->id.'-'.$resource->checksum);
        abort_unless($previewPath && Storage::disk('private')->exists($previewPath), 404);

        return response()->file(Storage::disk('private')->path($previewPath), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function isPdf(Resource $resource): bool
    {
        return $resource->mime_type === 'application/pdf'
            || Str::endsWith(Str::lower((string) $resource->original_filename), '.pdf')
            || Str::endsWith(Str::lower((string) $resource->private_path), '.pdf');
    }
}
