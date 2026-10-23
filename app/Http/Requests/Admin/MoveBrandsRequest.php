<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveBrandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'category_uuid' => ['required', 'uuid', Rule::exists('brand_categories', 'uuid')],
            'delete_source' => ['sometimes', 'boolean'],
            'source_category_uuid' => ['required_if:delete_source,true', 'nullable', 'uuid', Rule::exists('brand_categories', 'uuid'), 'different:category_uuid'],
            'brand_uuids' => [Rule::excludeIf($this->filled('source_category_uuid')), 'required_without:source_category_uuid', 'array', 'min:1', 'max:500'],
            'brand_uuids.*' => ['required', 'uuid', 'distinct', Rule::exists('brands', 'uuid')],
        ];
    }
}
