<?php

namespace App\Http\Requests\Recurring;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewRecurringEntryExchangeRequest extends FormRequest
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
            'account_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'start_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_uuid.required' => __('transactions.validation.preview_account_required'),
            'amount.required' => __('transactions.validation.preview_amount_required'),
            'amount.numeric' => __('transactions.validation.preview_amount_numeric'),
            'amount.gt' => __('transactions.validation.preview_amount_positive'),
            'start_date.required' => __('transactions.validation.preview_date_invalid'),
            'start_date.date' => __('transactions.validation.preview_date_invalid'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $startDate = null;

        if ($this->filled('start_date')) {
            try {
                $startDate = CarbonImmutable::parse(
                    (string) $this->input('start_date'),
                )->toDateString();
            } catch (\Throwable) {
                $startDate = null;
            }
        }

        $this->merge([
            'account_uuid' => $this->filled('account_uuid')
                ? (string) $this->input('account_uuid')
                : null,
            'amount' => $this->filled('amount')
                ? round((float) $this->input('amount'), 2)
                : null,
            'start_date' => $startDate,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('start_date')) {
                return;
            }

            if (! $this->filled('start_date')) {
                $validator->errors()->add(
                    'start_date',
                    __('transactions.validation.preview_date_invalid'),
                );
            }
        });
    }
}
