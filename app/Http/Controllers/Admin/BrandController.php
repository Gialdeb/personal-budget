<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBrandRequest;
use App\Http\Requests\SearchBrandsRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Models\BrandCategory;
use App\Services\Brands\BrandImportService;
use App\Services\Brands\BrandLogoService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(SearchBrandsRequest $request): Response
    {
        $data = $request->validated();
        $sort = $data['sort'] ?? 'name_asc';
        $brands = Brand::query()->search($data['search'] ?? null)
            ->when($request->boolean('without_category'), fn ($query) => $query->where(fn ($query) => $query->whereNull('category')->orWhere('category', '')),
                fn ($query) => $query->when(filled($data['category'] ?? null), fn ($query) => $query->where('category', $data['category'])))
            ->when(filled($data['letter'] ?? null), fn ($query) => $query->where('normalized_name', 'like', strtolower($data['letter']).'%'))
            ->orderBy($sort === 'updated_desc' ? 'updated_at' : 'normalized_name', $sort === 'name_asc' ? 'asc' : 'desc')->orderBy('uuid')
            ->paginate(20)->withQueryString();

        return Inertia::render('admin/Brands/Index', ['brands' => $brands->through(fn (Brand $brand): array => [...(new BrandResource($brand))->resolve(), 'is_published' => $brand->is_published]), 'categories' => BrandCategory::query()->orderBy('name')->pluck('name')->all(), 'category_management' => BrandCategory::query()->withCount('brands')->orderBy('name')->get(['id', 'uuid', 'name'])->map(fn (BrandCategory $category): array => ['uuid' => $category->uuid, 'name' => $category->name, 'brands_count' => $category->brands_count]), 'filters' => ['category' => $data['category'] ?? '', 'without_category' => $request->boolean('without_category'), 'search' => $data['search'] ?? '', 'letter' => $data['letter'] ?? '', 'sort' => $sort]]);
    }

    public function importCandidates(SearchBrandsRequest $request, BrandImportService $imports): JsonResponse
    {
        $data = $request->validated();

        return response()->json($imports->candidates($data['search'] ?? null, (int) ($data['page'] ?? 1)));
    }

    public function store(SaveBrandRequest $request, BrandLogoService $logos): RedirectResponse
    {
        return $this->save($request, new Brand, $logos);
    }

    public function update(SaveBrandRequest $request, Brand $brand, BrandLogoService $logos): RedirectResponse
    {
        return $this->save($request, $brand, $logos);
    }

    protected function save(SaveBrandRequest $request, Brand $brand, BrandLogoService $logos): RedirectResponse
    {
        $data = $request->validated();
        $data['domain'] = $request->input('domain');
        $data['alternative_domains'] = $request->input('alternative_domains', $brand->alternative_domains ?? []);
        $attributes = collect($data)->only(['name', 'aliases', 'alternative_domains', 'domain', 'category', 'is_published'])->all();
        if ($request->hasFile('logo')) {
            $attributes += $logos->upload($request->file('logo'));
        } elseif ($request->boolean('fetch_logo')) {
            $attributes += $logos->fromWebsite($data['domain']);
        } elseif ($request->boolean('remove_logo')) {
            $attributes += ['logo_path' => null, 'logo_mime_type' => null];
        }
        try {
            Cache::lock('brand-domain-catalog', 30)->block(10, fn () => DB::transaction(function () use ($request, $attributes, $brand): void {
                if (Brand::conflictingDomains([$attributes['domain'], ...$attributes['alternative_domains']], $brand)) {
                    throw ValidationException::withMessages(['domain' => __('form_errors.a_domain_is_already_associated_with_another_brand')]);
                }
                if (filled($attributes['category'] ?? null) && ! BrandCategory::query()->where('name', $attributes['category'])->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['category' => __('form_errors.category_no_longer_available')]);
                }
                if ($request->filled('source_logo_uuid')) {
                    app(BrandImportService::class)->import($request->string('source_logo_uuid')->toString(), $attributes);
                } else {
                    $brand->fill($attributes)->save();
                }
            }));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => __('form_errors.a_brand_with_this_name_and_domain_already_exists')]);
        }

        return back()->with('success', 'Marchio salvato.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return back()->with('success', 'Marchio rimosso.');
    }
}
