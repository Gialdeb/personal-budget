<?php

namespace App\Http\Requests\Admin;

use App\Enums\BillingProviderEnum;
use App\Enums\BillingTransactionStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillingTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'provider' => ['sometimes', 'string', Rule::in(BillingProviderEnum::values())],
            'provider_transaction_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'provider_event_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'customer_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'status' => ['sometimes', 'string', Rule::in(BillingTransactionStatusEnum::values())],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'received_at' => ['sometimes', 'nullable', 'date'],
            'is_recurring' => ['sometimes', 'boolean'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }
}
