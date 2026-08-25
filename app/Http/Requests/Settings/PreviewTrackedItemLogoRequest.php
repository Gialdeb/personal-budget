<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewTrackedItemLogoRequest extends FormRequest
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
            'website_url' => ['required', 'string', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'website_url.required' => __('tracked_items.logo.validation.required'),
            'website_url.max' => __('tracked_items.logo.validation.invalid_url'),
        ];
    }
}
