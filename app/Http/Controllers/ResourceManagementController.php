<?php

namespace App\Http\Controllers;

use App\Models\Cycle;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ResourceManagementController extends Controller
{
    public function index()
    {
        return view('admin.resources.index', [
            'resources' => Resource::query()->with(['author', 'schoolClass.cycle', 'subject', 'resourceType'])->latest()->paginate(20),
            'cycles' => Cycle::query()->where('active', true)->orderBy('sort_order')->get(),
            'classes' => SchoolClass::query()->where('active', true)->orderBy('sort_order')->get(),
            'subjects' => Subject::query()->where('active', true)->orderBy('name')->get(),
            'types' => ResourceType::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'resource_type_id' => ['required', 'integer', 'exists:resource_types,id'],
            'academic_year' => ['nullable', 'string', 'max:16'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'rights_statement' => ['required', 'string', 'max:2000'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx', 'max:30720'],
        ]);

        $file = $data['file'];
        $filename = $file->getClientOriginalName();
        $path = $file->storeAs('resources/'.now()->format('Y/m'), Str::uuid().'.'.$file->getClientOriginalExtension(), 'private');

        try {
            $resource = DB::transaction(function () use ($request, $data, $file, $filename, $path) {
                return Resource::create([
                    'author_id' => $request->user()->id,
                    'school_class_id' => $data['school_class_id'] ?? null,
                    'subject_id' => $data['subject_id'] ?? null,
                    'resource_type_id' => $data['resource_type_id'],
                    'title' => $data['title'],
                    'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(7)),
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'currency' => config('payments.currency'),
                    'status' => 'published',
                    'visibility' => 'public',
                    'academic_year' => $data['academic_year'] ?? null,
                    'private_path' => $path,
                    'original_filename' => $filename,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'checksum' => hash_file('sha256', $file->getPathname()),
                    'rights_statement' => $data['rights_statement'],
                    'published_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }

        return redirect()->route('admin.resources.index')->with('success', "La ressource « {$resource->title} » a été ajoutée. Elle sera téléchargeable uniquement après paiement confirmé.");
    }

    public function edit(Resource $resource)
    {
        return view('admin.resources.edit', [
            'resource' => $resource,
            'classes' => SchoolClass::query()->where('active', true)->orderBy('sort_order')->get(),
            'subjects' => Subject::query()->where('active', true)->orderBy('name')->get(),
            'types' => ResourceType::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Resource $resource): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'resource_type_id' => ['required', 'integer', 'exists:resource_types,id'],
            'academic_year' => ['nullable', 'string', 'max:16'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'rights_statement' => ['required', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx', 'max:30720'],
        ]);

        $oldPath = $resource->private_path;
        $newPath = null;
        if ($request->hasFile('file')) {
            $file = $data['file'];
            $newPath = $file->storeAs('resources/'.now()->format('Y/m'), Str::uuid().'.'.$file->getClientOriginalExtension(), 'private');
            $data = [...$data, ...[
                'private_path' => $newPath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getPathname()),
            ]];
        }
        unset($data['file']);

        if ($data['status'] === 'published' && ! $resource->published_at) {
            $data['published_at'] = now();
        }

        try {
            DB::transaction(fn () => $resource->update($data));
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('private')->delete($newPath);
            }
            throw $exception;
        }

        if ($newPath && $oldPath && $oldPath !== $newPath) {
            Storage::disk('private')->delete($oldPath);
        }

        return redirect()->route('admin.resources.index')->with('success', "La ressource « {$resource->fresh()->title} » a été mise à jour.");
    }
}
