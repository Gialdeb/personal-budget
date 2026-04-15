<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ShowPushDeviceStatusRequest extends FormRequest
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
            'token' => ['nullable', 'required_without:device_identifier', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'in:web'],
            'device_identifier' => ['nullable', 'required_without:token', 'string', 'max:120'],
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
            'token.required_without' => __('settings.profile.push_web.validation.token_required'),
            'platform.string' => __('settings.profile.push_web.validation.platform_invalid'),
            'platform.in' => __('settings.profile.push_web.validation.platform_invalid'),
            'device_identifier.string' => __('settings.profile.push_web.validation.token_invalid'),
            'device_identifier.max' => __('settings.profile.push_web.validation.token_invalid'),
            'device_identifier.required_without' => __('settings.profile.push_web.validation.token_required'),
        ];
    }
}
