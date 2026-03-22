<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserYearRequest extends FormRequest
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
            'year' => [
                'required',
                'integer',
                'between:1900,2200',
                Rule::unique('user_years', 'year')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.required' => __('settings.years.validation.required'),
            'year.integer' => __('settings.years.validation.integer'),
            'year.between' => __('settings.years.validation.between'),
            'year.unique' => __('settings.years.validation.unique'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => $this->integer('year'),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $year = $this->integer('year');

            if ($year > now()->year) {
                $validator->errors()->add('year', __('settings.years.validation.future_year_not_allowed', [
                    'year' => now()->year,
                ]));
            }
        });
    }
}
