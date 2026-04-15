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

        $pushNotificationsEnabled = (bool) config('features.push_notifications.enabled')
            && (bool) config('features.push_notifications.profile_enabled');

        return [
            'categories' => ['present', 'array'],
            'categories.*.uuid' => ['required', 'uuid', Rule::in($allowedCategoryUuids)],
            'categories.*.email_enabled' => ['nullable', 'boolean'],
            'categories.*.in_app_enabled' => ['nullable', 'boolean'],
            'push' => [$pushNotificationsEnabled ? 'nullable' : 'prohibited', 'array'],
            'push.enabled' => [$pushNotificationsEnabled ? 'nullable' : 'prohibited', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categories.present' => __('settings.profile.notifications.validation.required'),
            'categories.array' => __('settings.profile.notifications.validation.required'),
            'categories.*.uuid.required' => __('settings.profile.notifications.validation.invalid_topic'),
            'categories.*.uuid.uuid' => __('settings.profile.notifications.validation.invalid_topic'),
            'categories.*.uuid.in' => __('settings.profile.notifications.validation.invalid_topic'),
            'categories.*.email_enabled.boolean' => __('settings.profile.notifications.validation.invalid_value'),
            'categories.*.in_app_enabled.boolean' => __('settings.profile.notifications.validation.invalid_value'),
            'push.array' => __('settings.profile.notifications.validation.invalid_value'),
            'push.enabled.boolean' => __('settings.profile.notifications.validation.invalid_value'),
            'push.prohibited' => __('settings.profile.notifications.validation.invalid_value'),
            'push.enabled.prohibited' => __('settings.profile.notifications.validation.invalid_value'),
        ];
    }
}
