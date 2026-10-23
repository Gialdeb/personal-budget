<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchBrandsRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandCatalogController extends Controller
{
    public function index(SearchBrandsRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();

        return BrandResource::collection(Brand::query()->published()->search($data['search'] ?? null)
            ->when(filled($data['letter'] ?? null), fn ($query) => $query->where('normalized_name', 'like', strtolower($data['letter']).'%'))
            ->when(filled($data['category'] ?? null), fn ($query) => $query->orderByRaw('CASE WHEN LOWER(category) = ? THEN 0 ELSE 1 END', [Str::lower($data['category'])]))
            ->when(filled($data['search'] ?? null), fn ($query) => $query->orderByRaw('CASE WHEN normalized_name = ? THEN 0 ELSE 1 END', [Brand::normalize($data['search'])]))
            ->orderBy('normalized_name')->orderBy('uuid')->simplePaginate(20)->withQueryString());
    }

    public function logo(Request $request, Brand $brand): StreamedResponse
    {
        abort_unless($brand->is_published || ($request->user()?->hasRole('admin') ?? false), 404);
        abort_unless($brand->logo_path && Storage::disk('public')->exists($brand->logo_path), 404);

        return Storage::disk('public')->response($brand->logo_path, null, ['Content-Type' => $brand->logo_mime_type, 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }
}
