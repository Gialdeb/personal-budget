<?php

namespace App\Http\Requests\CreditDebts;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditDebtPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('user_id', (int) $this->user()->id)],
            'paid_at' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => __('credit_debts.validation.amount_required'),
            'amount.numeric' => __('credit_debts.validation.amount_numeric'),
            'amount.gt' => __('credit_debts.validation.amount_gt_zero'),
            'account_id.required' => __('credit_debts.validation.account_required'),
            'account_id.exists' => __('credit_debts.validation.account_unavailable'),
            'paid_at.required' => __('credit_debts.validation.paid_at_required'),
            'paid_at.date' => __('credit_debts.validation.date_invalid'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'account_id' => $this->filled('account_uuid') && ! $this->filled('account_id')
                ? Account::query()->where('uuid', $this->input('account_uuid'))->value('id')
                : $this->input('account_id'),
        ]);
    }
}
