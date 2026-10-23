<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoveBrandsRequest;
use App\Http\Requests\Admin\SaveBrandCategoryRequest;
use App\Models\Brand;
use App\Models\BrandCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BrandCategoryController extends Controller
{
    public function store(SaveBrandCategoryRequest $request): RedirectResponse
    {
        BrandCategory::create($request->validated());

        return back()->with('success', 'Categoria creata.');
    }

    public function update(SaveBrandCategoryRequest $request, BrandCategory $brandCategory): RedirectResponse
    {
        DB::transaction(function () use ($request, $brandCategory): void {
            $category = BrandCategory::query()->lockForUpdate()->findOrFail($brandCategory->id);
            $previous = $category->name;
            $category->update($request->validated());
            DB::table('brands')->where('category', $previous)->update(['category' => $category->name]);
        });

        return back()->with('success', 'Categoria rinominata.');
    }

    public function destroy(BrandCategory $brandCategory): RedirectResponse
    {
        DB::transaction(function () use ($brandCategory): void {
            $category = BrandCategory::query()->lockForUpdate()->findOrFail($brandCategory->id);
            if ($category->brands()->exists()) {
                throw ValidationException::withMessages(['category' => 'Sposta prima i marchi in un’altra categoria.']);
            }
            $category->delete();
        });

        return back()->with('success', 'Categoria eliminata.');
    }

    public function move(MoveBrandsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data): void {
            $categories = BrandCategory::query()->whereIn('uuid', array_filter([$data['category_uuid'], $data['source_category_uuid'] ?? null]))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('uuid');
            $target = $categories->get($data['category_uuid']);
            $source = isset($data['source_category_uuid']) ? $categories->get($data['source_category_uuid']) : null;
            if (! $target || (isset($data['source_category_uuid']) && ! $source)) {
                throw ValidationException::withMessages(['category_uuid' => 'Categoria non più disponibile.']);
            }
            $brands = Brand::query()->when($source, fn ($query) => $query->where('category', $source->name),
                fn ($query) => $query->whereIn('uuid', $data['brand_uuids']))->lockForUpdate()->get(['id']);
            if (! $source && $brands->count() !== count($data['brand_uuids'])) {
                throw ValidationException::withMessages(['brand_uuids' => 'Uno o più marchi non sono più disponibili.']);
            }
            DB::table('brands')->whereIn('id', $brands->modelKeys())->update(['category' => $target->name]);
            if ($data['delete_source'] ?? false) {
                $source->delete();
            }
        });

        return back()->with('success', ($data['delete_source'] ?? false) ? 'Marchi trasferiti e categoria eliminata.' : 'Marchi spostati.');
    }
}
