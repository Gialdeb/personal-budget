<?php

namespace App\Http\Requests\Recurring;

use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactivateRecurringEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'auto_create_transaction' => ['sometimes', 'boolean'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'expected_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'recurrence_type' => ['required', Rule::in(RecurringEntryRecurrenceTypeEnum::values())],
            'recurrence_interval' => ['required', 'integer', 'min:1', 'max:1000'],
            'recurrence_rule' => ['nullable', 'array'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'end_mode' => ['required', Rule::in(RecurringEndModeEnum::values())],
            'end_date' => ['nullable', 'required_if:end_mode,until_date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'occurrences_limit' => ['nullable', 'required_if:end_mode,after_occurrences', 'integer', 'min:1'],
        ];
    }
}
