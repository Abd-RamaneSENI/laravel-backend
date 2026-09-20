<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\BookCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MetadataController extends Controller
{
    public function index()
    {
        return response()->json([
            'authors' => Author::query()->orderBy('name')->get(['id', 'name']),
            'categories' => BookCategory::query()->orderBy('name')->get(['id', 'name', 'description']),
        ]);
    }

    public function storeAuthor(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'biography' => ['nullable', 'string', 'max:3000']]);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(5));

        return response()->json(['author' => Author::create($data)], 201);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:500']]);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(5));

        return response()->json(['category' => BookCategory::create($data)], 201);
    }
}
