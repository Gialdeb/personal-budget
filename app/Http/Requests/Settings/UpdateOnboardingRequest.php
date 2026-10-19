<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['complete', 'skip', 'disable', 'reset', 'migrate'])],
            'section' => ['sometimes', 'string', Rule::in(['dashboard', 'transactions', 'recurring', 'shared-expenses'])],
            'quick_start_dismissed' => ['sometimes', 'boolean'],
        ];
    }
}
