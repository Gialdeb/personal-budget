<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RichContentAssetController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $image = $validated['image'];
        $extension = $image->guessExtension() ?: $image->extension() ?: 'jpg';
        $directory = 'editorial/rich-content/'.now()->format('Y/m');
        $path = $image->storeAs(
            $directory,
            Str::uuid()->toString().'.'.$extension,
            'public',
        );

        return response()->json([
            'path' => $path,
            'url' => route('editorial-assets.show', ['path' => $path]),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'starts_with:editorial/rich-content/'],
        ]);

        Storage::disk('public')->delete($validated['path']);

        return response()->json([
            'deleted' => true,
        ]);
    }
}
