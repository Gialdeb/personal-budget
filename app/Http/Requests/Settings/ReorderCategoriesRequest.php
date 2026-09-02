<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderCategoriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'parent_uuid' => ['nullable', 'uuid', Rule::exists('categories', 'uuid')],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.uuid' => ['required', 'uuid', 'distinct', Rule::exists('categories', 'uuid')],
            'categories.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
