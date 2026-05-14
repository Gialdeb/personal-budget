<?php

namespace App\Http\Requests\CreditDebts;

use App\Enums\CreditDebtTypeEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditDebtItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $editableAccounts = app(AccessibleAccountsQuery::class)->editable($this->user())->get(['accounts.*']);
        $editableAccountIds = $editableAccounts->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $operationalCategoryResolver = app(OperationalTransactionCategoryResolver::class);
        $categoryIds = $editableAccounts
            ->flatMap(fn (Account $account) => $operationalCategoryResolver->categoriesForAccount($account)->pluck('id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $referenceIds = $editableAccounts
            ->flatMap(fn (Account $account) => $operationalCategoryResolver->trackedItemsForAccount($account)->pluck('id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return [
            'type' => ['required', Rule::in(CreditDebtTypeEnum::values())],
            'description' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'currency_code' => ['required', 'string', 'size:3', Rule::in(array_keys(config('currencies.supported', [])))],
            'reference_id' => ['nullable', 'integer', Rule::in($referenceIds)],
            'account_id' => ['required', 'integer', Rule::in($editableAccountIds)],
            'category_id' => ['required', 'integer', Rule::in($categoryIds)],
            'due_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => __('credit_debts.validation.type_required'),
            'type.in' => __('credit_debts.validation.type_invalid'),
            'description.required' => __('credit_debts.validation.description_required'),
            'total_amount.required' => __('credit_debts.validation.amount_required'),
            'total_amount.numeric' => __('credit_debts.validation.amount_numeric'),
            'total_amount.gt' => __('credit_debts.validation.amount_gt_zero'),
            'currency_code.required' => __('credit_debts.validation.currency_required'),
            'currency_code.in' => __('credit_debts.validation.currency_invalid'),
            'account_id.required' => __('credit_debts.validation.account_required'),
            'account_id.in' => __('credit_debts.validation.account_unavailable'),
            'category_id.required' => __('credit_debts.validation.category_required'),
            'category_id.in' => __('credit_debts.validation.category_unavailable'),
            'reference_id.in' => __('credit_debts.validation.reference_unavailable'),
            'due_date.required' => __('credit_debts.validation.due_date_required'),
            'due_date.date' => __('credit_debts.validation.date_invalid'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $prepared = [];

        if ($this->filled('currency_code')) {
            $prepared['currency_code'] = strtoupper((string) $this->input('currency_code'));
        }

        if ($this->filled('account_uuid') && ! $this->filled('account_id')) {
            $prepared['account_id'] = Account::query()->where('uuid', $this->input('account_uuid'))->value('id');
        }

        if ($this->filled('category_uuid') && ! $this->filled('category_id')) {
            $prepared['category_id'] = Category::query()->where('uuid', $this->input('category_uuid'))->value('id');
        }

        if ($this->filled('reference_uuid') && ! $this->filled('reference_id')) {
            $prepared['reference_id'] = TrackedItem::query()->where('uuid', $this->input('reference_uuid'))->value('id');
        }

        $this->merge($prepared);
    }
}
