<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchBrandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['category' => ['nullable', 'string', 'max:100'], 'without_category' => ['sometimes', 'boolean'], 'search' => ['nullable', 'string', 'max:150'], 'letter' => ['nullable', 'regex:/^[A-Za-z]$/'], 'sort' => ['nullable', Rule::in(['name_asc', 'name_desc', 'updated_desc'])], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
