<?php

namespace App\Http\Requests\Salary;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalaryOccurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'base_expected_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'pay_period_start' => ['required', 'date'], 'pay_period_end' => ['required', 'date', 'after_or_equal:pay_period_start'],
            'components' => ['present', 'array', 'max:50'], 'components.*.label' => ['required', 'string', 'max:150'],
            'components.*.amount' => ['required', 'numeric', 'between:-999999999999.99,999999999999.99', 'decimal:0,2'],
            'components.*.type' => ['nullable', 'string', 'max:50'],
        ];
    }
}
