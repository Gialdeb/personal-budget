<?php

namespace App\Http\Requests\Salary;

use Illuminate\Foundation\Http\FormRequest;

class ExplainSalaryDifferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['label' => ['required', 'string', 'max:150'], 'type' => ['nullable', 'string', 'max:50']];
    }

    public function attributes(): array
    {
        return ['label' => __('salary.fields.label'), 'type' => __('salary.fields.kind')];
    }
}
