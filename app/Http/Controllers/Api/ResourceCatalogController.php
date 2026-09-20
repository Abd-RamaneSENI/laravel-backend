<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Http\Request;

class ResourceCatalogController extends Controller
{
    public function index(Request $request)
    {
        $resources = Resource::published()
            ->with(['schoolClass.cycle:id,name', 'subject:id,name', 'resourceType:id,name'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim((string) $request->input('q'));
                $query->where(fn ($where) => $where
                    ->whereLike('title', "%{$term}%")
                    ->orWhereLike('description', "%{$term}%"));
            })
            ->when($request->filled('cycle'), fn ($query) => $query->whereHas('schoolClass.cycle', fn ($cycle) => $cycle->where('slug', $request->input('cycle'))))
            ->latest('published_at')
            ->take(24)
            ->get(['id', 'school_class_id', 'subject_id', 'resource_type_id', 'title', 'slug', 'description', 'price', 'currency', 'academic_year', 'mime_type', 'original_filename', 'private_path', 'checksum', 'updated_at'])
            ->map(fn (Resource $resource) => [
                'id' => $resource->id,
                'school_class' => $resource->schoolClass,
                'subject' => $resource->subject,
                'resource_type' => $resource->resourceType,
                'title' => $resource->title,
                'slug' => $resource->slug,
                'description' => $resource->description,
                'price' => $resource->price,
                'currency' => $resource->currency,
                'academic_year' => $resource->academic_year,
                'preview_url' => route('resources.preview', [
                    'resource' => $resource->slug,
                    'v' => $resource->checksum ?: ($resource->updated_at?->timestamp ?? $resource->id),
                ]),
            ]);

        return response()->json(['resources' => $resources]);
    }
}
