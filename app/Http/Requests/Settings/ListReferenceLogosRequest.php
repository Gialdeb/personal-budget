<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ListReferenceLogosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:150'], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
