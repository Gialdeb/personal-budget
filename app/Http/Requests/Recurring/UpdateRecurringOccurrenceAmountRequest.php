<?php

namespace App\Http\Requests\Recurring;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRecurringOccurrenceAmountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999999.99',
                'decimal:0,2',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => __('transactions.validation.recurring_occurrence_amount_required'),
            'amount.numeric' => __('transactions.validation.recurring_occurrence_amount_invalid'),
            'amount.gt' => __('transactions.validation.recurring_occurrence_amount_positive'),
            'amount.max' => __('transactions.validation.recurring_occurrence_amount_too_large'),
            'amount.decimal' => __('transactions.validation.recurring_occurrence_amount_decimals'),
        ];
    }
}
