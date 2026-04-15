<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StorePushDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && (bool) config('features.push_notifications.enabled');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', 'string', 'in:web'],
            'locale' => ['nullable', 'string', 'max:12'],
            'device_identifier' => ['nullable', 'string', 'max:120'],
            'service_worker_version' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => __('settings.profile.push_web.validation.token_required'),
            'token.string' => __('settings.profile.push_web.validation.token_invalid'),
            'token.max' => __('settings.profile.push_web.validation.token_invalid'),
            'platform.required' => __('settings.profile.push_web.validation.platform_invalid'),
            'platform.string' => __('settings.profile.push_web.validation.platform_invalid'),
            'platform.in' => __('settings.profile.push_web.validation.platform_invalid'),
            'locale.string' => __('settings.profile.push_web.validation.locale_invalid'),
            'locale.max' => __('settings.profile.push_web.validation.locale_invalid'),
            'device_identifier.string' => __('settings.profile.push_web.validation.token_invalid'),
            'device_identifier.max' => __('settings.profile.push_web.validation.token_invalid'),
            'service_worker_version.string' => __('settings.profile.push_web.validation.token_invalid'),
            'service_worker_version.max' => __('settings.profile.push_web.validation.token_invalid'),
        ];
    }
}
