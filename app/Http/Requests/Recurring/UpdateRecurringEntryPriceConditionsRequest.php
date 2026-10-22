<?php

namespace App\Http\Requests\Recurring;

use App\Models\RecurringEntry;
use App\Services\Accounts\AccessibleAccountsQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRecurringEntryPriceConditionsRequest extends FormRequest
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
        return [
            'type' => ['sometimes', 'required', Rule::in(['standard', 'locked', 'locked_until', 'announced'])],
            'lock_mode' => ['nullable', Rule::in(['indefinite', 'until'])],
            'change_direction' => ['nullable', Rule::in(['increase', 'decrease'])],
            'locked_until' => [Rule::excludeIf($this->input('lock_mode') === 'indefinite'), 'required_if:lock_mode,until', ...($this->has('type') ? ['exclude_unless:type,locked_until', 'required'] : ['nullable']), 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'list_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'personal_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'change_scope' => ['required_with:change_direction', 'nullable', Rule::in(['list_only', 'personal'])],
            'new_list_price' => [Rule::excludeIf(! $this->filled('change_scope')), 'required_if:change_scope,list_only', 'nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'new_amount' => [Rule::excludeIf($this->input('change_scope') === 'list_only'), 'required_if:change_scope,personal', ...($this->has('type') ? ['exclude_unless:type,announced', 'required'] : ['nullable']), 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'effective_from' => [Rule::excludeIf($this->has('type') ? $this->input('type') !== 'announced' : ! $this->filled('new_amount') && ! $this->filled('new_list_price')), 'nullable', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'new_amount' => __('recurring_price_conditions.amount'),
            'new_list_price' => __('recurring_price_conditions.amount'),
            'locked_until' => __('recurring_price_conditions.date'),
            'effective_from' => __('recurring_price_conditions.date'),
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('change_direction')) {
                return;
            }
            $entry = $this->route('recurringEntry');
            $isPersonal = $this->input('change_scope') === 'personal';
            $field = $isPersonal ? 'new_amount' : 'new_list_price';
            $base = $isPersonal ? ($this->input('personal_price') ?? $entry->price_conditions['personal_price'] ?? $entry->expected_amount)
                : ($this->input('list_price') ?? $entry->price_conditions['list_price'] ?? null);
            if ($base === null || ! $this->filled($field)) {
                return;
            }
            $previous = (int) round((float) $base * 100);
            $next = (int) round((float) $this->input($field) * 100);
            if ($this->input('change_direction') === 'increase' ? $next <= $previous : $next >= $previous) {
                $validator->errors()->add($field, __('recurring_price_conditions.direction'));
            }
        }];
    }
}
