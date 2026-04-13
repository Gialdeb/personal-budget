<?php

namespace App\Http\Requests\Transactions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewTransactionExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'transaction_day' => ['nullable', 'integer', 'between:1,31'],
            'transaction_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_uuid.required' => __('transactions.validation.preview_account_required'),
            'amount.required' => __('transactions.validation.preview_amount_required'),
            'amount.numeric' => __('transactions.validation.preview_amount_numeric'),
            'amount.gt' => __('transactions.validation.preview_amount_positive'),
            'transaction_date.date' => __('transactions.validation.preview_date_invalid'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $routeYear = (int) $this->route('year');
        $routeMonth = (int) $this->route('month');
        $transactionDate = null;

        if ($this->filled('transaction_date')) {
            try {
                $transactionDate = CarbonImmutable::parse(
                    (string) $this->input('transaction_date'),
                )->toDateString();
            } catch (\Throwable) {
                $transactionDate = null;
            }
        } elseif ($this->filled('transaction_day')) {
            $transactionDate = sprintf(
                '%04d-%02d-%02d',
                $routeYear,
                $routeMonth,
                (int) $this->input('transaction_day'),
            );
        }

        $this->merge([
            'account_uuid' => $this->filled('account_uuid')
                ? (string) $this->input('account_uuid')
                : null,
            'amount' => $this->filled('amount')
                ? round((float) $this->input('amount'), 2)
                : null,
            'transaction_day' => $this->filled('transaction_day')
                ? (int) $this->input('transaction_day')
                : null,
            'transaction_date' => $transactionDate,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny([
                'transaction_day',
                'transaction_date',
            ])) {
                return;
            }

            if (! $this->filled('transaction_date')) {
                $validator->errors()->add(
                    'transaction_day',
                    __('transactions.validation.date_invalid'),
                );
            }
        });
    }
}
