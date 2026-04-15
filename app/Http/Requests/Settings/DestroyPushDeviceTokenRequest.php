<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class DestroyPushDeviceTokenRequest extends FormRequest
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
            'token' => ['nullable', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'in:web'],
            'device_identifier' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.string' => __('settings.profile.push_web.validation.token_invalid'),
            'token.max' => __('settings.profile.push_web.validation.token_invalid'),
            'platform.string' => __('settings.profile.push_web.validation.platform_invalid'),
            'platform.in' => __('settings.profile.push_web.validation.platform_invalid'),
            'device_identifier.string' => __('settings.profile.push_web.validation.token_invalid'),
            'device_identifier.max' => __('settings.profile.push_web.validation.token_invalid'),
        ];
    }
}
