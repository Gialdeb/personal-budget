<?php

namespace App\Services\Exports;

use App\Enums\ExportDatasetEnum;
use App\Enums\ExportFormatEnum;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Supports\CategoryHierarchy;
use App\Supports\TrackedItemHierarchy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserDataExportManager
{
    public function normalizePeriod(ExportDatasetEnum $dataset, ExportPeriod $period): ExportPeriod
    {
        if (! $dataset->supportsPeriod()) {
            return ExportPeriod::allTime();
        }

        return $period;
    }

    public function filenameFor(
        ExportDatasetEnum $dataset,
        ExportFormatEnum $format,
        ExportPeriod $period,
    ): string {
        $referenceDate = CarbonImmutable::now(config('app.timezone'));
        $token = $dataset->supportsPeriod()
            ? $period->filenameToken($referenceDate)
            : $referenceDate->format('Y-m-d');

        return sprintf('%s_%s.%s', $dataset->filePrefix(), $token, $format->value);
    }

    /**
     * @return array<int, string>
     */
    public function csvHeadersFor(ExportDatasetEnum $dataset): array
    {
        return match ($dataset) {
            ExportDatasetEnum::TRANSACTIONS => [
                'transaction_uuid',
                'transaction_date',
                'value_date',
                'posted_at',
                'amount',
                'currency',
                'direction',
                'kind',
                'source_type',
                'status',
                'description',
                'notes',
                'account_uuid',
                'account_name',
                'account_currency',
                'scope_uuid',
                'scope_name',
                'category_uuid',
                'category_name',
                'category_path',
                'tracked_item_uuid',
                'tracked_item_name',
                'tracked_item_path',
                'merchant_uuid',
                'merchant_name',
                'counterparty_name',
                'reference_code',
                'balance_after',
                'is_transfer',
                'related_transaction_uuid',
                'import_uuid',
                'created_at',
                'updated_at',
            ],
            ExportDatasetEnum::ACCOUNTS => [
                'account_uuid',
                'name',
                'bank_name',
                'account_type',
                'scope_uuid',
                'scope_name',
                'currency_code',
                'opening_balance',
                'opening_balance_date',
                'current_balance',
                'is_manual',
                'is_active',
                'is_reported',
                'is_default',
                'iban',
                'account_number_masked',
                'notes',
                'created_at',
                'updated_at',
            ],
            ExportDatasetEnum::CATEGORIES => [
                'category_uuid',
                'account_uuid',
                'account_name',
                'parent_uuid',
                'parent_name',
                'name',
                'slug',
                'full_path',
                'icon',
                'color',
                'direction_type',
                'group_type',
                'sort_order',
                'is_active',
                'is_selectable',
                'is_system',
                'created_at',
                'updated_at',
            ],
            ExportDatasetEnum::TRACKED_ITEMS => [
                'tracked_item_uuid',
                'account_uuid',
                'account_name',
                'parent_uuid',
                'parent_name',
                'name',
                'slug',
                'type',
                'full_path',
                'is_active',
                'created_at',
                'updated_at',
            ],
            ExportDatasetEnum::RECURRING_ENTRIES => [
                'recurring_entry_uuid',
                'title',
                'description',
                'direction',
                'entry_type',
                'status',
                'expected_amount',
                'total_amount',
                'currency',
                'account_uuid',
                'account_name',
                'scope_uuid',
                'scope_name',
                'category_uuid',
                'category_name',
                'category_path',
                'tracked_item_uuid',
                'tracked_item_name',
                'tracked_item_path',
                'merchant_uuid',
                'merchant_name',
                'start_date',
                'end_date',
                'next_occurrence_date',
                'due_day',
                'recurrence_type',
                'recurrence_interval',
                'end_mode',
                'occurrences_limit',
                'installments_count',
                'auto_generate_occurrences',
                'auto_create_transaction',
                'is_active',
                'notes',
                'created_at',
                'updated_at',
            ],
            ExportDatasetEnum::BUDGETS => [
                'budget_uuid',
                'year',
                'month',
                'period',
                'amount',
                'budget_type',
                'scope_uuid',
                'scope_name',
                'category_uuid',
                'category_name',
                'category_path',
                'tracked_item_uuid',
                'tracked_item_name',
                'tracked_item_path',
                'notes',
                'created_at',
                'updated_at',
            ],
            ExportDatasetEnum::FULL_EXPORT => [],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recordsFor(User $user, ExportDatasetEnum $dataset, ExportPeriod $period): array
    {
        return match ($dataset) {
            ExportDatasetEnum::TRANSACTIONS => $this->transactionRecords($user, $period),
            ExportDatasetEnum::ACCOUNTS => $this->accountRecords($user),
            ExportDatasetEnum::CATEGORIES => $this->categoryRecords($user),
            ExportDatasetEnum::TRACKED_ITEMS => $this->trackedItemRecords($user),
            ExportDatasetEnum::RECURRING_ENTRIES => $this->recurringEntryRecords($user, $period),
            ExportDatasetEnum::BUDGETS => $this->budgetRecords($user, $period),
            ExportDatasetEnum::FULL_EXPORT => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonPayloadFor(User $user, ExportDatasetEnum $dataset, ExportPeriod $period): array
    {
        if ($dataset === ExportDatasetEnum::FULL_EXPORT) {
            return $this->fullExportPayload($user);
        }

        return [
            'metadata' => [
                'dataset' => $dataset->value,
                'format' => ExportFormatEnum::JSON->value,
                'exported_at' => CarbonImmutable::now(config('app.timezone'))->toIso8601String(),
                'app_version' => config('app.version'),
                'user_uuid' => $user->uuid,
                'period' => $period->toArray(),
            ],
            'records' => $this->recordsFor($user, $dataset, $period),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function transactionRecords(User $user, ExportPeriod $period): array
    {
        $categoryMap = $this->categoryLookup($user);
        $trackedItemMap = $this->trackedItemLookup($user);

        return Transaction::query()
            ->where('user_id', $user->id)
            ->with([
                'account:id,uuid,name,currency_code',
                'scope:id,uuid,name',
                'category:id,uuid,name',
                'trackedItem:id,uuid,name',
                'merchant:id,uuid,name',
                'import:id,uuid',
                'relatedTransaction:id,uuid',
            ])
            ->when(
                ! $period->isAllTime(),
                fn (Builder $query) => $query
                    ->whereDate('transaction_date', '>=', $period->startDate?->toDateString())
                    ->whereDate('transaction_date', '<=', $period->endDate?->toDateString())
            )
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->get()
            ->map(function (Transaction $transaction) use ($categoryMap, $trackedItemMap): array {
                $category = $categoryMap->get($transaction->category?->uuid);
                $trackedItem = $trackedItemMap->get($transaction->trackedItem?->uuid);

                return [
                    'transaction_uuid' => $transaction->uuid,
                    'transaction_date' => $this->date($transaction->transaction_date),
                    'value_date' => $this->date($transaction->value_date),
                    'posted_at' => $this->datetime($transaction->posted_at),
                    'amount' => $this->decimal($transaction->amount),
                    'currency' => $transaction->currency,
                    'direction' => $transaction->direction?->value,
                    'kind' => $transaction->kind?->value,
                    'source_type' => $transaction->source_type?->value,
                    'status' => $transaction->status?->value,
                    'description' => $transaction->description,
                    'notes' => $transaction->notes,
                    'account_uuid' => $transaction->account?->uuid,
                    'account_name' => $transaction->account?->name,
                    'account_currency' => $transaction->account?->currency_code,
                    'scope_uuid' => $transaction->scope?->uuid,
                    'scope_name' => $transaction->scope?->name,
                    'category_uuid' => $transaction->category?->uuid,
                    'category_name' => $transaction->category?->name,
                    'category_path' => $category['full_path'] ?? null,
                    'tracked_item_uuid' => $transaction->trackedItem?->uuid,
                    'tracked_item_name' => $transaction->trackedItem?->name,
                    'tracked_item_path' => $trackedItem['full_path'] ?? null,
                    'merchant_uuid' => $transaction->merchant?->uuid,
                    'merchant_name' => $transaction->merchant?->name,
                    'counterparty_name' => $transaction->counterparty_name,
                    'reference_code' => $transaction->reference_code,
                    'balance_after' => $this->decimal($transaction->balance_after),
                    'is_transfer' => (bool) $transaction->is_transfer,
                    'related_transaction_uuid' => $transaction->relatedTransaction?->uuid,
                    'import_uuid' => $transaction->import?->uuid,
                    'created_at' => $this->datetime($transaction->created_at),
                    'updated_at' => $this->datetime($transaction->updated_at),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function accountRecords(User $user): array
    {
        return Account::query()
            ->ownedBy($user->id)
            ->with([
                'bank:id,name',
                'userBank:id,name',
                'accountType:id,name,code',
                'scope:id,uuid,name',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account): array => [
                'account_uuid' => $account->uuid,
                'name' => $account->name,
                'bank_name' => $account->bank?->name ?? $account->userBank?->name ?? null,
                'account_type' => $account->accountType?->code ?? $account->accountType?->name,
                'scope_uuid' => $account->scope?->uuid,
                'scope_name' => $account->scope?->name,
                'currency_code' => $account->currency_code,
                'opening_balance' => $this->decimal($account->opening_balance),
                'opening_balance_date' => $this->date($account->opening_balance_date),
                'current_balance' => $this->decimal($account->current_balance),
                'is_manual' => (bool) $account->is_manual,
                'is_active' => (bool) $account->is_active,
                'is_reported' => (bool) $account->is_reported,
                'is_default' => (bool) $account->is_default,
                'iban' => $account->iban,
                'account_number_masked' => $account->account_number_masked,
                'notes' => $account->notes,
                'created_at' => $this->datetime($account->created_at),
                'updated_at' => $this->datetime($account->updated_at),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function categoryRecords(User $user): array
    {
        $categories = Category::query()
            ->where('user_id', $user->id)
            ->with(['account:id,uuid,name', 'parent:id,uuid,name'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categoryMap = collect(CategoryHierarchy::buildFlat($categories->loadCount('children')))
            ->keyBy('uuid');

        return $categories
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->map(function (Category $category) use ($categoryMap): array {
                $payload = $categoryMap->get($category->uuid, []);

                return [
                    'category_uuid' => $category->uuid,
                    'account_uuid' => $category->account?->uuid,
                    'account_name' => $category->account?->name,
                    'parent_uuid' => $payload['parent_uuid'] ?? $category->parent?->uuid,
                    'parent_name' => $category->parent?->name,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'full_path' => $payload['full_path'] ?? $category->name,
                    'icon' => $category->icon,
                    'color' => $category->color,
                    'direction_type' => $category->direction_type?->value,
                    'group_type' => $category->group_type?->value,
                    'sort_order' => $category->sort_order,
                    'is_active' => (bool) $category->is_active,
                    'is_selectable' => (bool) $category->is_selectable,
                    'is_system' => (bool) $category->is_system,
                    'created_at' => $this->datetime($category->created_at),
                    'updated_at' => $this->datetime($category->updated_at),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function trackedItemRecords(User $user): array
    {
        $trackedItems = TrackedItem::query()
            ->where('user_id', $user->id)
            ->with(['account:id,uuid,name', 'parent:id,uuid,name'])
            ->orderBy('name')
            ->get();

        $trackedItemMap = collect(TrackedItemHierarchy::buildFlat($trackedItems))
            ->keyBy('uuid');

        return $trackedItems
            ->map(function (TrackedItem $trackedItem) use ($trackedItemMap): array {
                $payload = $trackedItemMap->get($trackedItem->uuid, []);

                return [
                    'tracked_item_uuid' => $trackedItem->uuid,
                    'account_uuid' => $trackedItem->account?->uuid,
                    'account_name' => $trackedItem->account?->name,
                    'parent_uuid' => $payload['parent_uuid'] ?? $trackedItem->parent?->uuid,
                    'parent_name' => $payload['parent_name'] ?? $trackedItem->parent?->name,
                    'name' => $trackedItem->name,
                    'slug' => $trackedItem->slug,
                    'type' => $trackedItem->type,
                    'full_path' => $payload['full_path'] ?? $trackedItem->name,
                    'is_active' => (bool) $trackedItem->is_active,
                    'created_at' => $this->datetime($trackedItem->created_at),
                    'updated_at' => $this->datetime($trackedItem->updated_at),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recurringEntryRecords(User $user, ExportPeriod $period): array
    {
        $categoryMap = $this->categoryLookup($user);
        $trackedItemMap = $this->trackedItemLookup($user);

        return RecurringEntry::query()
            ->where('user_id', $user->id)
            ->with([
                'account:id,uuid,name',
                'scope:id,uuid,name',
                'category:id,uuid,name',
                'trackedItem:id,uuid,name',
                'merchant:id,uuid,name',
            ])
            ->when(! $period->isAllTime(), function (Builder $query) use ($period): void {
                $query
                    ->whereDate('start_date', '<=', $period->endDate?->toDateString())
                    ->where(function (Builder $nestedQuery) use ($period): void {
                        $nestedQuery
                            ->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $period->startDate?->toDateString());
                    });
            })
            ->orderBy('start_date')
            ->orderBy('title')
            ->get()
            ->map(function (RecurringEntry $entry) use ($categoryMap, $trackedItemMap): array {
                $category = $categoryMap->get($entry->category?->uuid);
                $trackedItem = $trackedItemMap->get($entry->trackedItem?->uuid);

                return [
                    'recurring_entry_uuid' => $entry->uuid,
                    'title' => $entry->title,
                    'description' => $entry->description,
                    'direction' => $entry->direction?->value,
                    'entry_type' => $entry->entry_type?->value,
                    'status' => $entry->status?->value,
                    'expected_amount' => $this->decimal($entry->expected_amount),
                    'total_amount' => $this->decimal($entry->total_amount),
                    'currency' => $entry->currency,
                    'account_uuid' => $entry->account?->uuid,
                    'account_name' => $entry->account?->name,
                    'scope_uuid' => $entry->scope?->uuid,
                    'scope_name' => $entry->scope?->name,
                    'category_uuid' => $entry->category?->uuid,
                    'category_name' => $entry->category?->name,
                    'category_path' => $category['full_path'] ?? null,
                    'tracked_item_uuid' => $entry->trackedItem?->uuid,
                    'tracked_item_name' => $entry->trackedItem?->name,
                    'tracked_item_path' => $trackedItem['full_path'] ?? null,
                    'merchant_uuid' => $entry->merchant?->uuid,
                    'merchant_name' => $entry->merchant?->name,
                    'start_date' => $this->date($entry->start_date),
                    'end_date' => $this->date($entry->end_date),
                    'next_occurrence_date' => $this->date($entry->next_occurrence_date),
                    'due_day' => $entry->due_day,
                    'recurrence_type' => $entry->recurrence_type?->value,
                    'recurrence_interval' => $entry->recurrence_interval,
                    'end_mode' => $entry->end_mode?->value,
                    'occurrences_limit' => $entry->occurrences_limit,
                    'installments_count' => $entry->installments_count,
                    'auto_generate_occurrences' => (bool) $entry->auto_generate_occurrences,
                    'auto_create_transaction' => (bool) $entry->auto_create_transaction,
                    'is_active' => (bool) $entry->is_active,
                    'notes' => $entry->notes,
                    'created_at' => $this->datetime($entry->created_at),
                    'updated_at' => $this->datetime($entry->updated_at),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function budgetRecords(User $user, ExportPeriod $period): array
    {
        $categoryMap = $this->categoryLookup($user);
        $trackedItemMap = $this->trackedItemLookup($user);

        return Budget::query()
            ->where('user_id', $user->id)
            ->with([
                'scope:id,uuid,name',
                'category:id,uuid,name',
                'trackedItem:id,uuid,name',
            ])
            ->when(! $period->isAllTime(), function (Builder $query) use ($period): void {
                $startMonth = (int) ($period->startDate?->format('Ym') ?? 0);
                $endMonth = (int) ($period->endDate?->format('Ym') ?? 0);

                $query->whereBetween(
                    DB::raw('year * 100 + month'),
                    [$startMonth, $endMonth],
                );
            })
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function (Budget $budget) use ($categoryMap, $trackedItemMap): array {
                $category = $categoryMap->get($budget->category?->uuid);
                $trackedItem = $trackedItemMap->get($budget->trackedItem?->uuid);

                return [
                    'budget_uuid' => $budget->uuid,
                    'year' => $budget->year,
                    'month' => $budget->month,
                    'period' => sprintf('%04d-%02d', $budget->year, $budget->month),
                    'amount' => $this->decimal($budget->amount),
                    'budget_type' => $budget->budget_type?->value,
                    'scope_uuid' => $budget->scope?->uuid,
                    'scope_name' => $budget->scope?->name,
                    'category_uuid' => $budget->category?->uuid,
                    'category_name' => $budget->category?->name,
                    'category_path' => $category['full_path'] ?? null,
                    'tracked_item_uuid' => $budget->trackedItem?->uuid,
                    'tracked_item_name' => $budget->trackedItem?->name,
                    'tracked_item_path' => $trackedItem['full_path'] ?? null,
                    'notes' => $budget->notes,
                    'created_at' => $this->datetime($budget->created_at),
                    'updated_at' => $this->datetime($budget->updated_at),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function fullExportPayload(User $user): array
    {
        /** @var UserSetting|null $settings */
        $settings = UserSetting::query()->where('user_id', $user->id)->first();

        return [
            'metadata' => [
                'dataset' => ExportDatasetEnum::FULL_EXPORT->value,
                'format' => ExportFormatEnum::JSON->value,
                'exported_at' => CarbonImmutable::now(config('app.timezone'))->toIso8601String(),
                'app_version' => config('app.version'),
                'user_uuid' => $user->uuid,
                'datasets_included' => [
                    'profile',
                    'preferences',
                    'accounts',
                    'categories',
                    'transactions',
                    'tracked_items',
                    'recurring_entries',
                    'budgets',
                ],
                'base_currency' => $user->base_currency_code,
                'format_locale' => $user->format_locale,
            ],
            'profile' => [
                'user_uuid' => $user->uuid,
                'name' => $user->name,
                'surname' => $user->surname,
                'email' => $user->email,
                'locale' => $user->locale,
                'format_locale' => $user->format_locale,
                'base_currency_code' => $user->base_currency_code,
                'email_verified_at' => $this->datetime($user->email_verified_at),
                'created_at' => $this->datetime($user->created_at),
                'updated_at' => $this->datetime($user->updated_at),
            ],
            'preferences' => [
                'settings_uuid' => $settings?->uuid,
                'active_year' => $settings?->active_year,
                'base_currency' => $settings?->base_currency,
                'settings' => $settings?->settings,
            ],
            'accounts' => $this->accountRecords($user),
            'categories' => $this->categoryRecords($user),
            'transactions' => $this->transactionRecords($user, ExportPeriod::allTime()),
            'tracked_items' => $this->trackedItemRecords($user),
            'recurring_entries' => $this->recurringEntryRecords($user, ExportPeriod::allTime()),
            'budgets' => $this->budgetRecords($user, ExportPeriod::allTime()),
        ];
    }

    protected function categoryLookup(User $user): Collection
    {
        $categories = Category::query()
            ->where('user_id', $user->id)
            ->with('account:id,uuid,name')
            ->withCount('children')
            ->get();

        return collect(CategoryHierarchy::buildFlat($categories))->keyBy('uuid');
    }

    protected function trackedItemLookup(User $user): Collection
    {
        $trackedItems = TrackedItem::query()
            ->where('user_id', $user->id)
            ->get();

        return collect(TrackedItemHierarchy::buildFlat($trackedItems))->keyBy('uuid');
    }

    protected function decimal(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    protected function date(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse($value)->toDateString();
    }

    protected function datetime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse($value)->toIso8601String();
    }
}
