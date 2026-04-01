<?php

namespace App\Http\Requests\Admin;

use App\Enums\BillingSubscriptionStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillingSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(BillingSubscriptionStatusEnum::values())],
            'billing_plan_code' => ['required', 'string', Rule::in(['free', 'supporter'])],
            'is_supporter' => ['required', 'boolean'],
            'started_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'next_reminder_at' => ['nullable', 'date'],
            'admin_notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
