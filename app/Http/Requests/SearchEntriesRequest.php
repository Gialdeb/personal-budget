<?php

namespace App\Http\Requests;

use App\Enums\RecurringEntryStatusEnum;
use App\Enums\TransactionDirectionEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchEntriesRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:120'],
            'scope' => ['nullable', 'string', Rule::in(['all', 'transactions', 'recurring'])],
            'across_months' => ['nullable', 'boolean'],
            'current_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'current_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'account_uuid' => ['nullable', 'uuid'],
            'category_uuid' => ['nullable', 'uuid'],
            'direction' => ['nullable', 'string', Rule::in(TransactionDirectionEnum::values())],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0'],
            'with_notes' => ['nullable', 'boolean'],
            'with_reference' => ['nullable', 'boolean'],
            'recurring_status' => ['nullable', 'string', Rule::in(RecurringEntryStatusEnum::values())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'scope' => $this->filled('scope') ? (string) $this->input('scope') : 'all',
            'across_months' => $this->boolean('across_months'),
            'with_notes' => $this->boolean('with_notes'),
            'with_reference' => $this->boolean('with_reference'),
        ]);
    }
}
