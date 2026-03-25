<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpsertCommunicationTemplateOverrideRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'subject_template' => ['nullable', 'string', 'max:255'],
            'title_template' => ['nullable', 'string', 'max:255'],
            'body_template' => ['nullable', 'string'],
            'cta_label_template' => ['nullable', 'string', 'max:255'],
            'cta_url_template' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_active.required' => __('admin.communication_templates.validation.is_active_required'),
            'subject_template.max' => __('admin.communication_templates.validation.subject_too_long'),
            'title_template.max' => __('admin.communication_templates.validation.title_too_long'),
            'cta_label_template.max' => __('admin.communication_templates.validation.cta_label_too_long'),
            'cta_url_template.max' => __('admin.communication_templates.validation.cta_url_too_long'),
        ];
    }
}
