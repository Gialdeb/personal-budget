<?php

namespace App\Http\Requests\Admin;

use App\Enums\BillingProviderEnum;
use App\Enums\BillingTransactionStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillingTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'billing_plan_code' => ['required', 'string', Rule::in(['free', 'supporter'])],
            'provider' => ['required', 'string', Rule::in(BillingProviderEnum::values())],
            'provider_transaction_id' => ['nullable', 'string', 'max:191'],
            'provider_event_id' => ['nullable', 'string', 'max:191'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'status' => ['required', 'string', Rule::in(BillingTransactionStatusEnum::values())],
            'paid_at' => ['nullable', 'date'],
            'received_at' => ['nullable', 'date'],
            'is_recurring' => ['required', 'boolean'],
            'apply_support_window' => ['required', 'boolean'],
            'admin_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
