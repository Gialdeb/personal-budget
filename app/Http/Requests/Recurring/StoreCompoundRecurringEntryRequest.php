<?php

namespace App\Http\Requests\Recurring;

use App\Models\CompoundRecurringEntry;
use App\Services\Accounts\AccessibleAccountsQuery;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompoundRecurringEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $compound = $this->route('compoundRecurringEntry');
        if ($compound instanceof CompoundRecurringEntry) {
            abort_unless($this->user() && app(AccessibleAccountsQuery::class)->canViewAccountId($this->user(), $compound->account_id), 404);

            return app(AccessibleAccountsQuery::class)->canEditAccountId($this->user(), $compound->account_id);
        }

        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $adding = $this->route('compoundRecurringEntry') instanceof CompoundRecurringEntry;

        return [
            'name' => [$adding ? 'prohibited' : 'required', 'string', 'max:150'],
            'account_uuid' => [$adding ? 'prohibited' : 'required', 'uuid'],
            'category_uuid' => [$adding ? 'prohibited' : 'required', 'uuid'],
            'direction' => [$adding ? 'prohibited' : 'required', 'in:income,expense'],
            'reminder_days_before' => ['sometimes', 'array', 'max:'.(int) config('reminders.recurring.max_custom_alerts', 3)],
            'reminder_days_before.*' => ['required', 'integer', 'between:1,'.(int) config('reminders.recurring.max_days_before', 15), 'distinct:strict'],
            'components' => ['required', 'array', 'min:1', 'max:12'],
            'components.*' => ['array:name,date,amount'],
            'components.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'components.*.date' => ['required', 'date_format:Y-m-d'],
            'components.*.amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'decimal:0,2'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return collect([
            'name' => 'name', 'account_uuid' => 'account', 'category_uuid' => 'category',
            'direction' => 'direction', 'components' => 'components', 'components.*' => 'component',
            'components.*.name' => 'component_name', 'components.*.date' => 'date',
            'components.*.amount' => 'amount', 'reminder_days_before' => 'reminders',
            'reminder_days_before.*' => 'reminder',
        ])->map(fn (string $field): string => __('compound_recurring.fields.'.$field))->all();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'components.*.date.required' => __('compound_recurring.date_required'),
            'components.*.date.date_format' => __('compound_recurring.date_invalid'),
            'components.*.name.required' => __('compound_recurring.name_required'),
            'components.*.name.distinct' => __('compound_recurring.duplicate_component'),
            'components.*.amount.required' => __('compound_recurring.amount_required'),
            'components.*.amount.numeric' => __('compound_recurring.amount_invalid'),
            'components.*.amount.gt' => __('compound_recurring.amount_invalid'),
            'components.*.amount.decimal' => __('compound_recurring.amount_invalid'),
        ];
    }
}
