<?php

namespace App\Http\Requests\Settings;

use App\Services\Communication\CommunicationPreferenceCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationPreferencesUpdateRequest extends FormRequest
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
        $allowedCategoryUuids = app(CommunicationPreferenceCatalog::class)
            ->configurableCategoriesQuery()
            ->pluck('uuid')
            ->all();

        return [
            'categories' => ['required', 'array'],
            'categories.*.uuid' => ['required', 'uuid', Rule::in($allowedCategoryUuids)],
            'categories.*.email_enabled' => ['nullable', 'boolean'],
            'categories.*.in_app_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categories.required' => __('settings.profile.notifications.validation.required'),
            'categories.array' => __('settings.profile.notifications.validation.required'),
            'categories.*.uuid.required' => __('settings.profile.notifications.validation.invalid_topic'),
            'categories.*.uuid.uuid' => __('settings.profile.notifications.validation.invalid_topic'),
            'categories.*.uuid.in' => __('settings.profile.notifications.validation.invalid_topic'),
            'categories.*.email_enabled.boolean' => __('settings.profile.notifications.validation.invalid_value'),
            'categories.*.in_app_enabled.boolean' => __('settings.profile.notifications.validation.invalid_value'),
        ];
    }
}
