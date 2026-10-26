<?php

namespace App\Http\Requests\Recurring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageOccurrencePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'reason' => ['required', Rule::in(['not_charged', 'bank_problem', 'insufficient_funds', 'postponed', 'cancelled', 'already_paid', 'different_amount', 'other', 'declined', 'invalid_method', 'processing', 'other_account', 'duplicate_charge', 'bank_reversal'])],
            'action' => ['required', Rule::in(['pending', 'cancel', 'confirm', 'correct', 'record'])],
            'payment_type' => ['nullable', Rule::in(['advance', 'final'])],
            'final_total_amount' => ['required_if:payment_type,final', 'nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'estimated_date' => ['nullable', 'date_format:Y-m-d'],
            'actual_date' => ['required_if:action,correct,record', 'nullable', 'date_format:Y-m-d'],
            'actual_amount' => ['required_if:action,correct,record', 'nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999999.99'],
            'transaction_uuid' => ['nullable', 'uuid'],
            'confirm_new_payment' => ['sometimes', 'boolean'],
            'account_uuid' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reminder_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tomorrow'],
            'confirm_rectification' => ['sometimes', 'boolean'],
        ];
    }
}
