<?php

namespace App\Http\Requests\Transactions;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Scope;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Services\UserYearService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionRequest extends FormRequest
{
    public const BALANCE_ADJUSTMENT_TYPE_KEY = 'balance_adjustment';

    public const MOVE_TYPE_KEY = 'move';

    private const MOVE_ELIGIBLE_TYPE_KEYS = [
        CategoryGroupTypeEnum::INCOME->value,
        CategoryGroupTypeEnum::EXPENSE->value,
        CategoryGroupTypeEnum::BILL->value,
        CategoryGroupTypeEnum::DEBT->value,
        CategoryGroupTypeEnum::SAVING->value,
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;
        $routeYear = (int) $this->route('year');
        $routeMonth = (int) $this->route('month');
        $daysInMonth = CarbonImmutable::create($routeYear, $routeMonth, 1)->daysInMonth;

        return [
            'transaction_day' => ['required', 'integer', 'between:1,'.$daysInMonth],
            'target_month' => ['nullable', 'integer', 'between:1,12'],
            'transaction_date' => ['nullable', 'date'],
            'type_key' => ['required', Rule::in([
                CategoryGroupTypeEnum::INCOME->value,
                CategoryGroupTypeEnum::EXPENSE->value,
                CategoryGroupTypeEnum::BILL->value,
                CategoryGroupTypeEnum::DEBT->value,
                CategoryGroupTypeEnum::SAVING->value,
                CategoryGroupTypeEnum::TRANSFER->value,
                self::BALANCE_ADJUSTMENT_TYPE_KEY,
                self::MOVE_TYPE_KEY,
            ])],
            'kind' => ['prohibited'],
            'account_uuid' => [
                Rule::requiredIf(fn (): bool => ! $this->filled('account_id')),
                'nullable',
                'uuid',
            ],
            'account_id' => [
                'nullable',
                'integer',
            ],
            'destination_account_uuid' => ['nullable', 'uuid'],
            'destination_account_id' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('type_key') === CategoryGroupTypeEnum::TRANSFER->value
                ),
                'nullable',
                'integer',
            ],
            'category_uuid' => ['nullable', 'uuid'],
            'category_id' => [
                Rule::requiredIf(
                    fn (): bool => ! in_array(
                        $this->input('type_key'),
                        [CategoryGroupTypeEnum::TRANSFER->value, self::BALANCE_ADJUSTMENT_TYPE_KEY, self::MOVE_TYPE_KEY],
                        true
                    )
                ),
                'nullable',
                'integer',
            ],
            'amount' => [
                Rule::requiredIf(fn (): bool => $this->input('type_key') !== self::BALANCE_ADJUSTMENT_TYPE_KEY),
                'nullable',
                'numeric',
                'gt:0',
                'max:999999999999.99',
            ],
            'desired_balance' => [
                Rule::requiredIf(fn (): bool => $this->input('type_key') === self::BALANCE_ADJUSTMENT_TYPE_KEY),
                'nullable',
                'numeric',
                'min:-999999999999.99',
                'max:999999999999.99',
            ],
            'tracked_item_uuid' => ['nullable', 'uuid'],
            'tracked_item_id' => [
                'nullable',
                'integer',
            ],
            'scope_uuid' => ['nullable', 'uuid'],
            'scope_id' => [
                'nullable',
                'integer',
            ],
            'description' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_day.required' => 'Il giorno del movimento è obbligatorio.',
            'transaction_day.integer' => 'Il giorno del movimento deve essere numerico.',
            'transaction_day.between' => 'Il giorno selezionato non è valido per il mese visualizzato.',
            'transaction_date.date' => 'La data movimento deve essere valida.',
            'type_key.required' => 'Seleziona il tipo della registrazione.',
            'type_key.in' => 'Il tipo selezionato non è valido.',
            'kind.prohibited' => 'Il tipo operativo della transazione non può essere impostato manualmente.',
            'account_uuid.required' => 'Seleziona un conto.',
            'destination_account_uuid.required' => 'Seleziona il conto di destinazione.',
            'category_uuid.required' => 'Seleziona una categoria.',
            'amount.required' => "L'importo è obbligatorio.",
            'amount.numeric' => "L'importo deve essere numerico.",
            'amount.gt' => "L'importo deve essere maggiore di zero.",
            'desired_balance.required' => 'Il saldo reale desiderato è obbligatorio.',
            'desired_balance.numeric' => 'Il saldo reale desiderato deve essere numerico.',
            'notes.max' => 'Le note sono troppo lunghe.',
            'description.max' => 'Il dettaglio è troppo lungo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $routeYear = (int) $this->route('year');
        $routeMonth = (int) $this->route('month');
        $routeTransaction = $this->resolvedRouteTransaction();
        $isMoveRequest = $this->input('type_key') === self::MOVE_TYPE_KEY;
        $targetMonth = $this->filled('target_month')
            ? (int) $this->input('target_month')
            : $routeMonth;
        $transactionDay = null;

        $parsedTransactionDate = null;

        if ($this->filled('transaction_day')) {
            $transactionDay = (int) $this->input('transaction_day');
        } elseif ($this->filled('transaction_date')) {
            try {
                $parsedTransactionDate = CarbonImmutable::parse((string) $this->input('transaction_date'));
                $transactionDay = $parsedTransactionDate->day;

                if ($isMoveRequest && ! $this->filled('target_month')) {
                    $targetMonth = $parsedTransactionDate->month;
                }
            } catch (\Throwable) {
                $transactionDay = null;
            }
        }

        $accountUuid = $this->filled('account_uuid') ? (string) $this->input('account_uuid') : null;
        $destinationAccountUuid = $this->filled('destination_account_uuid')
            ? (string) $this->input('destination_account_uuid')
            : null;
        $categoryUuid = $this->filled('category_uuid') ? (string) $this->input('category_uuid') : null;
        $trackedItemUuid = $this->filled('tracked_item_uuid') ? (string) $this->input('tracked_item_uuid') : null;
        $scopeUuid = $this->filled('scope_uuid') ? (string) $this->input('scope_uuid') : null;

        $this->merge([
            'transaction_day' => $transactionDay,
            'transaction_date' => $isMoveRequest && $parsedTransactionDate instanceof CarbonImmutable
                ? $parsedTransactionDate->toDateString()
                : ($transactionDay !== null
                    ? sprintf('%04d-%02d-%02d', $routeYear, $isMoveRequest ? $targetMonth : $routeMonth, $transactionDay)
                    : null),
            'target_month' => $targetMonth,
            'account_uuid' => $accountUuid,
            'account_id' => $this->filled('account_id')
                ? (int) $this->input('account_id')
                : ($isMoveRequest && $routeTransaction instanceof Transaction
                    ? (int) $routeTransaction->account_id
                    : ($accountUuid === null
                    ? null
                    : Account::query()->where('uuid', $accountUuid)->value('id'))),
            'destination_account_uuid' => $destinationAccountUuid,
            'destination_account_id' => $this->filled('destination_account_id')
                ? (int) $this->input('destination_account_id')
                : ($destinationAccountUuid !== null
                    ? Account::query()->where('uuid', $destinationAccountUuid)->value('id')
                    : null),
            'category_uuid' => $categoryUuid,
            'category_id' => $this->input('type_key') === CategoryGroupTypeEnum::TRANSFER->value
                || $this->input('type_key') === self::BALANCE_ADJUSTMENT_TYPE_KEY
                || $isMoveRequest
                ? null
                : ($this->filled('category_id')
                    ? (int) $this->input('category_id')
                    : ($categoryUuid !== null ? Category::query()->where('uuid', $categoryUuid)->value('id') : null)),
            'amount' => $isMoveRequest && $routeTransaction instanceof Transaction
                ? (float) $routeTransaction->amount
                : ($this->filled('amount') ? (float) $this->input('amount') : null),
            'desired_balance' => $this->filled('desired_balance')
                ? round((float) $this->input('desired_balance'), 2)
                : null,
            'tracked_item_uuid' => $trackedItemUuid,
            'tracked_item_id' => $this->input('type_key') === CategoryGroupTypeEnum::TRANSFER->value
                || $this->input('type_key') === self::BALANCE_ADJUSTMENT_TYPE_KEY
                || $isMoveRequest
                ? null
                : ($this->filled('tracked_item_id')
                    ? (int) $this->input('tracked_item_id')
                    : ($trackedItemUuid !== null ? TrackedItem::query()->where('uuid', $trackedItemUuid)->value('id') : null)),
            'scope_uuid' => $scopeUuid,
            'scope_id' => $this->input('type_key') === CategoryGroupTypeEnum::TRANSFER->value
                || $this->input('type_key') === self::BALANCE_ADJUSTMENT_TYPE_KEY
                || $isMoveRequest
                ? null
                : ($this->filled('scope_id')
                    ? (int) $this->input('scope_id')
                    : ($scopeUuid !== null ? Scope::query()->where('uuid', $scopeUuid)->value('id') : null)),
            'description' => $isMoveRequest && $routeTransaction instanceof Transaction
                ? ($routeTransaction->description ?: null)
                : ($this->filled('description') ? trim((string) $this->input('description')) : null),
            'notes' => $isMoveRequest && $routeTransaction instanceof Transaction
                ? ($routeTransaction->notes ?: null)
                : ($this->filled('notes') ? trim((string) $this->input('notes')) : null),
            'type_key' => $this->filled('type_key') ? (string) $this->input('type_key') : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $accessibleAccounts = app(AccessibleAccountsQuery::class);

            // Skip advanced validation if basic field validation already failed
            if ($validator->errors()->has(['transaction_day', 'type_key', 'amount', 'desired_balance'])) {
                return;
            }

            $date = (string) $this->input('transaction_date');
            $routeYear = (int) $this->route('year');
            $routeMonth = (int) $this->route('month');

            // Date and year validation
            if ($date && ! $validator->errors()->has('transaction_date')) {
                app(UserYearService::class)->ensureDateYearIsOpen(
                    $user,
                    $date,
                    'transaction_date'
                );

                try {
                    $parsedDate = CarbonImmutable::parse($date);

                    if (
                        $this->input('type_key') !== self::MOVE_TYPE_KEY
                        && (
                            $parsedDate->year !== $routeYear
                            || $parsedDate->month !== $routeMonth
                        )
                    ) {
                        $validator->errors()->add(
                            'transaction_date',
                            'La data movimento deve restare nel mese visualizzato.'
                        );
                    }
                } catch (\Throwable) {
                    $validator->errors()->add('transaction_date', 'La data movimento deve essere valida.');
                }
            }

            $account = null;
            if ($this->filled('account_id')) {
                $accountId = $this->integer('account_id');
                $account = $accessibleAccounts->findAccessibleAccount($user, $accountId, true);

                if (! $account instanceof Account) {
                    $validator->errors()->add(
                        $this->filled('account_uuid') ? 'account_uuid' : 'account_id',
                        $accessibleAccounts->canViewAccountId($user, $accountId)
                            ? __('transactions.validation.account_read_only')
                            : __('transactions.validation.account_unavailable')
                    );
                }
            } elseif ($this->filled('account_uuid')) {
                $validator->errors()->add('account_uuid', __('transactions.validation.account_unavailable'));
            }

            if ($this->input('type_key') === self::BALANCE_ADJUSTMENT_TYPE_KEY) {
                if ($this->isMethod('PATCH')) {
                    $validator->errors()->add('type_key', __('transactions.validation.balance_adjustment_update_blocked'));

                    return;
                }

                if (! $account instanceof Account) {
                    return;
                }

                return;
            }

            if ($this->input('type_key') === self::MOVE_TYPE_KEY) {
                if (! $this->isMethod('PATCH')) {
                    $validator->errors()->add('type_key', __('transactions.validation.move_update_only'));

                    return;
                }

                $routeTransaction = $this->resolvedRouteTransaction();

                if (! $routeTransaction instanceof Transaction) {
                    $validator->errors()->add('type_key', __('transactions.validation.move_unavailable'));

                    return;
                }

                $transactionKind = $routeTransaction->kind instanceof TransactionKindEnum
                    ? $routeTransaction->kind->value
                    : ($routeTransaction->kind !== null ? (string) $routeTransaction->kind : null);
                $transactionGroupType = $routeTransaction->category()->value('group_type');
                $transactionTypeKey = $transactionGroupType instanceof CategoryGroupTypeEnum
                    ? $transactionGroupType->value
                    : ($transactionGroupType !== null
                        ? (string) $transactionGroupType
                        : ($routeTransaction->direction === TransactionDirectionEnum::INCOME
                            ? CategoryGroupTypeEnum::INCOME->value
                            : CategoryGroupTypeEnum::EXPENSE->value));

                $isEligibleMoveTransaction = ! $routeTransaction->is_transfer
                    && ! in_array($transactionKind, [
                        TransactionKindEnum::SCHEDULED->value,
                        TransactionKindEnum::OPENING_BALANCE->value,
                        TransactionKindEnum::BALANCE_ADJUSTMENT->value,
                        TransactionKindEnum::REFUND->value,
                    ], true)
                    && $routeTransaction->recurring_entry_occurrence_id === null
                    && in_array($transactionTypeKey, self::MOVE_ELIGIBLE_TYPE_KEYS, true);

                if (! $isEligibleMoveTransaction) {
                    $validator->errors()->add('type_key', __('transactions.validation.move_unavailable'));
                }

                if (
                    ! $validator->errors()->has('transaction_date')
                    && $date !== ''
                    && $routeTransaction->transaction_date?->toDateString() === $date
                ) {
                    $validator->errors()->add('transaction_date', __('transactions.validation.move_same_date'));
                }

                return;
            }

            if ($this->input('type_key') === CategoryGroupTypeEnum::TRANSFER->value) {
                if ($this->filled('destination_account_id')) {
                    $destinationAccountId = $this->integer('destination_account_id');
                    $destinationAccount = $accessibleAccounts->findAccessibleAccount($user, $destinationAccountId, true);

                    if (! $destinationAccount instanceof Account) {
                        $validator->errors()->add(
                            $this->filled('destination_account_uuid') ? 'destination_account_uuid' : 'destination_account_id',
                            $accessibleAccounts->canViewAccountId($user, $destinationAccountId)
                                ? __('transactions.validation.account_read_only')
                                : __('transactions.validation.account_unavailable')
                        );
                    }
                } elseif ($this->filled('destination_account_uuid')) {
                    $validator->errors()->add(
                        'destination_account_uuid',
                        __('transactions.validation.account_unavailable')
                    );
                }

                if (
                    $this->filled('account_id')
                    && $this->filled('destination_account_id')
                    && $this->integer('account_id') === $this->integer('destination_account_id')
                ) {
                    $validator->errors()->add(
                        'destination_account_uuid',
                        'Il conto di destinazione deve essere diverso dal conto sorgente.'
                    );
                }

                return;
            }

            if (! $account instanceof Account) {
                return;
            }
            $categoryResolver = app(OperationalTransactionCategoryResolver::class);
            $category = $categoryResolver->findCategoryForAccount(
                $account,
                $this->integer('category_id'),
            );

            if (! $category instanceof Category) {
                if ($this->filled('category_uuid')) {
                    $validator->errors()->add('category_uuid', __('transactions.validation.category_unavailable'));
                }

                return;
            }

            if ((int) $category->id !== $this->integer('category_id')) {
                $this->merge([
                    'category_id' => (int) $category->id,
                    'category_uuid' => (string) $category->uuid,
                ]);
            }

            if ($category->group_type === CategoryGroupTypeEnum::TRANSFER) {
                $validator->errors()->add(
                    'category_uuid',
                    __('transactions.validation.transfer_category_reserved')
                );
            }

            if (! $category->is_selectable) {
                $validator->errors()->add(
                    'category_uuid',
                    __('transactions.validation.select_leaf_category')
                );
            }

            if (
                $category->group_type === null
                && $category->direction_type?->value !== null
                && $category->direction_type->value !== $this->resolvedDirectionFromTypeKey()
            ) {
                $validator->errors()->add(
                    'category_uuid',
                    __('transactions.validation.category_type_mismatch')
                );
            }

            $categoryTypeKey = $category->group_type?->value
                ?? ($category->direction_type?->value === CategoryDirectionTypeEnum::INCOME->value
                    ? CategoryGroupTypeEnum::INCOME->value
                    : CategoryGroupTypeEnum::EXPENSE->value);

            if ($categoryTypeKey !== $this->input('type_key')) {
                $validator->errors()->add(
                    'category_uuid',
                    'La categoria selezionata non appartiene al tipo scelto.'
                );
            }

            if ($this->filled('scope_id')) {
                $scope = $categoryResolver->findScopeForAccount(
                    $account,
                    $this->integer('scope_id'),
                );

                if (! $scope instanceof Scope) {
                    $validator->errors()->add(
                        $this->filled('scope_uuid') ? 'scope_uuid' : 'scope_id',
                        'Lo scope selezionato non è disponibile.'
                    );
                }
            }

            if (! $this->filled('tracked_item_id')) {
                return;
            }

            $trackedItem = $categoryResolver->findTrackedItemForAccount(
                $account,
                $this->integer('tracked_item_id'),
            );

            if (! $trackedItem instanceof TrackedItem) {
                if ($this->filled('tracked_item_uuid')) {
                    $validator->errors()->add(
                        'tracked_item_uuid',
                        "L'elemento tracciato selezionato non è disponibile."
                    );
                } elseif ($this->filled('tracked_item_id')) {
                    $validator->errors()->add(
                        'tracked_item_id',
                        "L'elemento tracciato selezionato non è disponibile."
                    );
                }

                return;
            }

            $settings = is_array($trackedItem->settings) ? $trackedItem->settings : [];
            $groupKeys = collect($settings['transaction_group_keys'] ?? [])
                ->filter(fn ($value): bool => is_string($value) && $value !== '')
                ->values()
                ->all();
            $categoryIds = $trackedItem->compatibleCategories
                ->pluck('id')
                ->map(fn ($value): int => (int) $value)
                ->values()
                ->all();

            if ($groupKeys !== [] && ! in_array((string) $this->input('type_key'), $groupKeys, true)) {
                $validator->errors()->add(
                    $this->filled('tracked_item_uuid') ? 'tracked_item_uuid' : 'tracked_item_id',
                    "L'elemento da tracciare non appartiene al tipo selezionato."
                );
            }

            if (
                $categoryIds !== []
                && count(array_intersect(
                    $categoryIds,
                    $categoryResolver->categoryContextIdsForAccount($account, (int) $category->id)
                )) === 0
            ) {
                $validator->errors()->add(
                    $this->filled('tracked_item_uuid') ? 'tracked_item_uuid' : 'tracked_item_id',
                    "L'elemento da tracciare non appartiene alla categoria selezionata."
                );
            }
        });
    }

    public function resolvedDirectionFromTypeKey(): string
    {
        return match ($this->input('type_key')) {
            CategoryGroupTypeEnum::INCOME->value => CategoryDirectionTypeEnum::INCOME->value,
            CategoryGroupTypeEnum::TRANSFER->value => CategoryDirectionTypeEnum::TRANSFER->value,
            default => CategoryDirectionTypeEnum::EXPENSE->value,
        };
    }

    protected function resolvedRouteTransaction(): ?Transaction
    {
        $routeValue = $this->route('transaction');

        if ($routeValue instanceof Transaction) {
            return $routeValue;
        }

        if (is_string($routeValue) && $routeValue !== '') {
            return Transaction::query()
                ->with('category')
                ->where('uuid', $routeValue)
                ->first();
        }

        return null;
    }
}
