<?php

namespace App\Http\Requests\Salary;

use Illuminate\Foundation\Http\FormRequest;

class EndSalaryEmploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['end_date' => ['required', 'date', 'after_or_equal:today']];
    }
}
