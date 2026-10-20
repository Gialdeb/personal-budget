<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecurringEntryDetailRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'occurrence_year' => ['nullable', 'integer', 'between:1,9998'],
            'occurrence_offset' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'highlight' => ['nullable', 'uuid'],
            'show_cancelled' => ['nullable', 'boolean'],
            'component' => ['nullable', 'uuid'],
        ];
    }
}
