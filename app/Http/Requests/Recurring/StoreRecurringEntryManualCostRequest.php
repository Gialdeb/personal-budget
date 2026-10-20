<?php

namespace App\Http\Requests\Recurring;

use App\Models\RecurringEntry;
use App\Models\RecurringEntryManualCost;
use App\Services\Accounts\AccessibleAccountsQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRecurringEntryManualCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('recurringEntry');
        abort_unless($entry instanceof RecurringEntry && $entry->entry_type?->value === 'recurring'
            && $this->user() && app(AccessibleAccountsQuery::class)->canViewAccountId($this->user(), $entry->account_id), 404);

        return ! $entry->isArchivedComponent() && app(AccessibleAccountsQuery::class)->canEditAccountId($this->user(), $entry->account_id);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->isMethod('delete') ? [] : [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:0001-01-01', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'decimal:0,2'],
            'currency' => ['prohibited'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->isMethod('delete') || $validator->errors()->isNotEmpty()) {
                return;
            }
            $entry = $this->route('recurringEntry');
            $cost = $this->route('manualCost');
            $duplicate = $entry->manualCosts()->where('date', $this->input('date'))
                ->where('amount', $this->input('amount'))->where('currency', $entry->currency)
                ->when($cost instanceof RecurringEntryManualCost, fn ($query) => $query->whereKeyNot($cost->id))
                ->exists();
            if ($duplicate) {
                $validator->errors()->add('amount', __('recurring_costs.duplicate'));
            }
        }];
    }
}
