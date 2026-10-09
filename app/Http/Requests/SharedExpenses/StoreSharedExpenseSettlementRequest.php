<?php

namespace App\Http\Requests\SharedExpenses;

use Illuminate\Foundation\Http\FormRequest;

class StoreSharedExpenseSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d+(?:[\.,]\d{1,2})?$/', 'not_in:0,0.0,0.00'],
            'settled_at' => ['required', 'date'],
            'account_uuid' => ['required', 'uuid'],
            'category_uuid' => ['nullable', 'uuid'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => str_replace(',', '.', trim((string) $this->input('amount'))),
            'reference' => $this->filled('reference') ? trim((string) $this->input('reference')) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }
}
