<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_closed' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_closed.required' => __('ui.specify_whether_the_year_should_be_open_or_closed_a12827b2'),
            'is_closed.boolean' => __('ui.the_open_closed_value_is_invalid_70657c59'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_closed' => $this->boolean('is_closed'),
        ]);
    }
}
