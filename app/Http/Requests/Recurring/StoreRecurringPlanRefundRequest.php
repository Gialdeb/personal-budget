<?php

namespace App\Http\Requests\Recurring;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecurringPlanRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'request_uuid' => ['required', 'uuid'],
            'account_uuid' => ['required', 'uuid'],
            'transaction_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'expected_total_cents' => ['required', 'integer', 'min:1'],
            'irreversible' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'request_uuid' => __('recurring_refunds.request'),
            'account_uuid' => __('recurring_refunds.account'),
            'transaction_date' => __('recurring_refunds.date'),
            'expected_total_cents' => __('recurring_refunds.total'),
            'irreversible' => __('recurring_refunds.confirmation'),
        ];
    }
}
