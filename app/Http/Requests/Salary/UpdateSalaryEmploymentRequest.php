<?php

namespace App\Http\Requests\Salary;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalaryEmploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date', 'after:today'], 'name' => ['nullable', 'string', 'max:150'],
            'automatic' => ['required', 'boolean'], 'payments' => ['required', 'array', 'min:1', 'max:25'],
            'payments.*.schedule_id' => ['nullable', 'integer'], 'payments.*.kind' => ['required', Rule::in(['ordinary', 'additional', 'other'])],
            'payments.*.label' => ['required', 'string', 'max:150'], 'payments.*.amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'decimal:0,2'],
            'payments.*.start_date' => ['required', 'date'], 'payments.*.recurrence_type' => ['required', Rule::in(['monthly', 'yearly', 'quarterly', 'weekly', 'daily'])],
            'payments.*.account_uuid' => ['required', 'uuid'], 'payments.*.category_uuid' => ['required', 'uuid'], 'payments.*.reference_uuid' => ['nullable', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return ['effective_from' => __('salary.revision_date'), 'name' => __('salary.fields.name'), 'payments' => __('salary.fields.additional'),
            'payments.*.label' => __('salary.fields.label'), 'payments.*.amount' => __('salary.fields.amount'), 'payments.*.start_date' => __('salary.fields.date'),
            'payments.*.account_uuid' => __('salary.fields.account'), 'payments.*.category_uuid' => __('salary.fields.category'), 'payments.*.reference_uuid' => __('salary.fields.reference'), 'payments.*.recurrence_type' => __('salary.fields.cadence'), 'payments.*.schedule_id' => __('salary.fields.additional'), 'payments.*.kind' => __('salary.fields.kind'), 'automatic' => __('salary.fields.automatic')];
    }
}
