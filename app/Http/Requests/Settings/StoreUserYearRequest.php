<?php

namespace App\Http\Requests\Settings;

use App\Services\UserYearCreationPolicy;
use Closure;
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
    public function rules(UserYearCreationPolicy $creationPolicy): array
    {
        return [
            'year' => [
                'required',
                'integer',
                'between:1900,2200',
                Rule::unique('user_years', 'year')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
                function (string $attribute, mixed $value, Closure $fail) use ($creationPolicy): void {
                    $year = (int) $value;

                    if (
                        $year < UserYearCreationPolicy::MINIMUM_YEAR
                        || $year > UserYearCreationPolicy::MAXIMUM_YEAR
                        || $creationPolicy->allows($this->user(), $year)
                    ) {
                        return;
                    }

                    $fail(__('settings.years.validation.future_year_not_allowed', [
                        'year' => $creationPolicy->maximumCreatableYear($this->user()),
                    ]));
                },
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
}
