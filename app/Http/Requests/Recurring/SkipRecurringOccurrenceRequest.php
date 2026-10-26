<?php

namespace App\Http\Requests\Recurring;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SkipRecurringOccurrenceRequest extends FormRequest
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
            'not_due' => ['sometimes', 'boolean'],
            'confirmed' => ['exclude_unless:not_due,true', 'required', 'accepted'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
