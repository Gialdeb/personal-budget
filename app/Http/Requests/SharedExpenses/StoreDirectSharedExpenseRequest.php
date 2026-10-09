<?php

namespace App\Http\Requests\SharedExpenses;

use Illuminate\Foundation\Http\FormRequest;

class StoreDirectSharedExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'], 'entry_date' => ['required', 'date'], 'amount' => ['required', 'regex:/^\d+(?:[\.,]\d{1,2})?$/'],
            'counterparty_uuid' => ['required', 'uuid'], 'paid_by' => ['required', 'in:self,counterparty'], 'my_share' => ['nullable', 'regex:/^\d+(?:[\.,]\d{1,2})?$/'], 'counterparty_share' => ['nullable', 'regex:/^\d+(?:[\.,]\d{1,2})?$/'],
            'account_uuid' => ['nullable', 'uuid'], 'category_uuid' => ['nullable', 'uuid'], 'reference_uuid' => ['nullable', 'uuid'], 'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['amount' => str_replace(',', '.', (string) $this->input('amount')), 'my_share' => $this->filled('my_share') ? str_replace(',', '.', (string) $this->input('my_share')) : null, 'counterparty_share' => $this->filled('counterparty_share') ? str_replace(',', '.', (string) $this->input('counterparty_share')) : null]);
    }
}
