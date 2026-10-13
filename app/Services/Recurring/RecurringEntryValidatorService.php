<?php

namespace App\Services\Recurring;

use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\RecurringEntry;
use App\Models\Scope;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\SharedExpenses\SharedExpenseAllocation;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Services\UserYearService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class RecurringEntryValidatorService
{
    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected OperationalTransactionCategoryResolver $operationalTransactionCategoryResolver,
        protected UserYearService $userYearService,
        protected SharedExpenseAllocation $sharedExpenseAllocation,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function validate(User $user, array $attributes): array
    {
        $normalized = $attributes;
        $normalized['title'] = trim((string) ($normalized['title'] ?? ''));
        $normalized['currency'] = strtoupper(trim((string) ($normalized['currency'] ?? '')));
        $normalized['direction'] = (string) ($normalized['direction'] ?? '');
        $normalized['entry_type'] = (string) ($normalized['entry_type'] ?? RecurringEntryTypeEnum::RECURRING->value);
        $normalized['end_mode'] = (string) ($normalized['end_mode'] ?? RecurringEndModeEnum::NEVER->value);
        $normalized['recurrence_type'] = (string) ($normalized['recurrence_type'] ?? '');
        $normalized['recurrence_interval'] = (int) ($normalized['recurrence_interval'] ?? 1);
        $normalized['start_date'] = isset($normalized['start_date'])
            ? CarbonImmutable::parse((string) $normalized['start_date'])->toDateString()
            : null;
        $normalized['end_date'] = filled($normalized['end_date'] ?? null)
            ? CarbonImmutable::parse((string) $normalized['end_date'])->toDateString()
            : null;
        $normalized['occurrences_limit'] = filled($normalized['occurrences_limit'] ?? null)
            ? (int) $normalized['occurrences_limit']
            : null;
        $normalized['installments_count'] = filled($normalized['installments_count'] ?? null)
            ? (int) $normalized['installments_count']
            : null;
        $normalized['expected_amount'] = filled($normalized['expected_amount'] ?? null)
            ? round((float) $normalized['expected_amount'], 2)
            : null;
        $normalized['is_amount_variable'] = (bool) ($normalized['is_amount_variable'] ?? false);
        $normalized['total_amount'] = filled($normalized['total_amount'] ?? null)
            ? round((float) $normalized['total_amount'], 2)
            : null;
        $normalized['recurrence_rule'] = $this->normalizeRecurrenceRule(
            $normalized['recurrence_rule'] ?? null
        );
        $normalized['reminder_days_before'] = collect($normalized['reminder_days_before'] ?? [])
            ->map(fn ($days): int => (int) $days)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $ownerUserId = $this->validateCommon($user, $normalized);
        $normalized['user_id'] = $ownerUserId;
        $this->validateSharedExpenseCounterparty($user, $normalized);
        $this->normalizeSharedExpenseAllocation($normalized);
        $this->validateEntryType($normalized);
        $this->validateEndMode($normalized);
        $this->validateRecurrence($normalized);

        $normalized['trial_duration'] = filled($normalized['trial_duration'] ?? null) ? (int) $normalized['trial_duration'] : null;
        $normalized['trial_unit'] = $normalized['trial_duration'] ? ($normalized['trial_unit'] ?? null) : null;
        if ($normalized['trial_duration'] !== null) {
            if ($normalized['entry_type'] !== 'recurring' || $normalized['trial_duration'] < 1 || $normalized['trial_duration'] > 3650 || ! in_array($normalized['trial_unit'], ['days', 'weeks', 'months'], true)) {
                throw ValidationException::withMessages(['trial_duration' => __('ui.trial_periods_are_only_available_for_recurring_entries_9c554a0f')]);
            }
            $billing = (new RecurringEntry($normalized))->firstBillingDate();
            $normalized['recurrence_rule'] = match ($normalized['recurrence_type']) {
                'monthly', 'quarterly' => ['mode' => 'day_of_month', 'day' => $billing->day],
                'yearly' => ['mode' => 'month_day', 'month' => $billing->month, 'day' => $billing->day],
                'weekly' => ['weekdays' => [$this->weekdayCode($billing)]],
                default => $normalized['recurrence_rule'],
            };
            if ($normalized['end_date'] !== null && $normalized['end_date'] < $billing->toDateString()) {
                throw ValidationException::withMessages(['end_date' => __('ui.the_plan_must_end_after_the_trial_period_5c782458')]);
            }
        }

        if ($normalized['entry_type'] === RecurringEntryTypeEnum::INSTALLMENT->value) {
            $normalized['expected_amount'] = null;
            $normalized['end_mode'] = RecurringEndModeEnum::AFTER_OCCURRENCES->value;
            $normalized['occurrences_limit'] = $normalized['installments_count'];
        } else {
            $normalized['total_amount'] = null;
            $normalized['installments_count'] = null;
        }

        if ($normalized['end_mode'] !== RecurringEndModeEnum::UNTIL_DATE->value) {
            $normalized['end_date'] = null;
        }

        if ($normalized['end_mode'] !== RecurringEndModeEnum::AFTER_OCCURRENCES->value) {
            $normalized['occurrences_limit'] = $normalized['entry_type'] === RecurringEntryTypeEnum::INSTALLMENT->value
                ? $normalized['installments_count']
                : null;
        }

        $normalized['currency'] = $normalized['currency'] !== ''
            ? $normalized['currency']
            : Account::query()->find($normalized['account_id'])?->currency;
        $normalized['next_occurrence_date'] = (new RecurringEntry($normalized))->firstBillingDate()->toDateString();

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function normalizeSharedExpenseAllocation(array &$attributes): void
    {
        if (! filled($attributes['shared_expense_counterparty_id'] ?? null)) {
            $attributes['shared_expense_paid_by'] = null;
            $attributes['shared_expense_my_share_cents'] = null;
            $attributes['shared_expense_counterparty_share_cents'] = null;

            return;
        }

        $amount = $attributes['entry_type'] === RecurringEntryTypeEnum::INSTALLMENT->value
            ? (string) ($attributes['total_amount'] ?? '')
            : (string) ($attributes['expected_amount'] ?? '');
        if (! array_key_exists('shared_expense_my_share', $attributes) && ! array_key_exists('shared_expense_counterparty_share', $attributes)
            && isset($attributes['shared_expense_my_share_cents'], $attributes['shared_expense_counterparty_share_cents'])) {
            $oldTotal = (int) $attributes['shared_expense_my_share_cents'] + (int) $attributes['shared_expense_counterparty_share_cents'];
            $total = $this->sharedExpenseAllocation->toCents($amount);
            $myShare = $oldTotal > 0 ? intdiv($total * (int) $attributes['shared_expense_my_share_cents'], $oldTotal) : intdiv($total, 2);
            $attributes['shared_expense_my_share'] = number_format($myShare / 100, 2, '.', '');
            $attributes['shared_expense_counterparty_share'] = number_format(($total - $myShare) / 100, 2, '.', '');
        }
        $allocation = $this->sharedExpenseAllocation->normalize(
            $amount,
            $attributes['shared_expense_paid_by'] ?? null,
            $attributes['shared_expense_my_share'] ?? null,
            $attributes['shared_expense_counterparty_share'] ?? null,
        );
        $attributes['shared_expense_paid_by'] = $allocation['paid_by'];
        $attributes['shared_expense_my_share_cents'] = $allocation['my_share_cents'];
        $attributes['shared_expense_counterparty_share_cents'] = $allocation['counterparty_share_cents'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function validateCommon(User $user, array $attributes): int
    {
        if (($attributes['title'] ?? '') === '') {
            throw ValidationException::withMessages([
                'title' => __('ui.the_plan_title_is_required_fb54f7b4'),
            ]);
        }

        if (! in_array($attributes['direction'], [
            TransactionDirectionEnum::INCOME->value,
            TransactionDirectionEnum::EXPENSE->value,
        ], true)) {
            throw ValidationException::withMessages([
                'direction' => __('ui.the_plan_direction_must_be_income_or_expense_cce5a66c'),
            ]);
        }

        if ($attributes['start_date'] === null) {
            throw ValidationException::withMessages([
                'start_date' => __('ui.the_plan_start_date_is_required_05b311cf'),
            ]);
        }

        $today = CarbonImmutable::today(config('app.timezone'));

        if (CarbonImmutable::parse($attributes['start_date'])->lte($today)) {
            $this->userYearService->ensureDateYearIsOpen($user, $attributes['start_date'], 'start_date');
        }

        if (
            ($attributes['end_date'] ?? null) !== null
            && CarbonImmutable::parse($attributes['end_date'])->lte($today)
        ) {
            $this->userYearService->ensureDateYearIsOpen($user, $attributes['end_date'], 'end_date');
        }

        $account = $this->accessibleAccountsQuery->findAccessibleAccount(
            $user,
            (int) ($attributes['account_id'] ?? 0),
            true
        );

        if (! $account instanceof Account) {
            throw ValidationException::withMessages([
                'account_id' => $this->accessibleAccountsQuery->canViewAccountId(
                    $user,
                    (int) ($attributes['account_id'] ?? 0)
                )
                    ? __('transactions.validation.account_read_only')
                    : __('transactions.validation.account_unavailable'),
            ]);
        }

        $ownerUserId = (int) $account->user_id;
        if (($attributes['currency'] ?? '') === '') {
            $attributes['currency'] = $account->currency;
        }

        if (($attributes['currency'] ?? '') === '') {
            throw ValidationException::withMessages([
                'currency' => __('ui.the_plan_currency_is_required_9b62e4a5'),
            ]);
        }

        $category = $this->operationalTransactionCategoryResolver->findCategoryForAccount(
            $account,
            (int) ($attributes['category_id'] ?? 0),
        );
        if (! $category instanceof Category) {
            throw ValidationException::withMessages([
                'category_id' => __('ui.the_selected_category_is_unavailable_for_this_account_1b68614c'),
            ]);
        }

        if ($category->direction_type?->value !== $attributes['direction']) {
            throw ValidationException::withMessages([
                'direction' => __('ui.the_plan_direction_must_match_the_selected_category_ac9d9e1c'),
            ]);
        }

        if (filled($attributes['scope_id'] ?? null)) {
            $scope = $this->operationalTransactionCategoryResolver->findScopeForAccount(
                $account,
                (int) $attributes['scope_id'],
            );

            if (! $scope instanceof Scope) {
                throw ValidationException::withMessages([
                    'scope_id' => __('ui.the_selected_scope_is_unavailable_for_this_account_f812eca7'),
                ]);
            }
        }

        if (filled($attributes['tracked_item_id'] ?? null)) {
            $trackedItem = $this->operationalTransactionCategoryResolver->findTrackedItemForAccount(
                $account,
                (int) $attributes['tracked_item_id'],
            );

            if (! $trackedItem instanceof TrackedItem) {
                throw ValidationException::withMessages([
                    'tracked_item_id' => __('ui.the_selected_reference_is_unavailable_for_this_account_e058a0be'),
                ]);
            }

            $compatibleCategoryIds = $trackedItem->compatibleCategories
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            if ($compatibleCategoryIds === []) {
                $compatibleCategoryIds = Category::query()
                    ->whereIn(
                        'uuid',
                        collect($trackedItem->settings['transaction_category_uuids'] ?? [])
                            ->filter(fn ($uuid): bool => is_string($uuid) && $uuid !== '')
                            ->values()
                            ->all()
                    )
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all();
            }

            if ($compatibleCategoryIds !== []) {
                $categoryContextIds = $this->operationalTransactionCategoryResolver
                    ->categoryContextIdsForAccount($account, (int) $category->id);

                if (count(array_intersect($compatibleCategoryIds, $categoryContextIds)) === 0) {
                    throw ValidationException::withMessages([
                        'tracked_item_id' => __('ui.the_selected_reference_is_incompatible_with_the_plan_category_c6bc1675'),
                    ]);
                }
            }
        }

        if (filled($attributes['merchant_id'] ?? null)) {
            $merchant = Merchant::query()
                ->where('user_id', $ownerUserId)
                ->find($attributes['merchant_id']);

            if (! $merchant instanceof Merchant) {
                throw ValidationException::withMessages([
                    'merchant_id' => __('ui.the_selected_merchant_does_not_belong_to_this_user_99363c2e'),
                ]);
            }
        }

        return $ownerUserId;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function validateSharedExpenseCounterparty(User $user, array $attributes): void
    {
        if (($attributes['shared_expense_counterparty_id'] ?? null) === null) {
            return;
        }

        $exists = TrackedItem::query()
            ->whereKey($attributes['shared_expense_counterparty_id'])
            ->where('user_id', $user->id)
            ->where('is_shared_expense_counterpart', true)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'shared_expense_counterparty_uuid' => __('ui.the_selected_counterparty_is_unavailable_b98af01b'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function validateEntryType(array $attributes): void
    {
        if (! in_array($attributes['entry_type'], RecurringEntryTypeEnum::values(), true)) {
            throw ValidationException::withMessages([
                'entry_type' => __('ui.the_plan_type_is_unsupported_6dfcafcc'),
            ]);
        }

        if ($attributes['entry_type'] === RecurringEntryTypeEnum::RECURRING->value) {
            if (($attributes['expected_amount'] ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'expected_amount' => 'I piani ricorrenti richiedono un importo atteso positivo.',
                ]);
            }

            if ($attributes['total_amount'] !== null || $attributes['installments_count'] !== null) {
                throw ValidationException::withMessages([
                    'entry_type' => __('ui.recurring_plans_do_not_accept_total_amount_or_installments_count_5e312b8a'),
                ]);
            }

            return;
        }

        if ((bool) ($attributes['is_amount_variable'] ?? false)) {
            throw ValidationException::withMessages([
                'is_amount_variable' => __('transactions.validation.recurring_variable_amount_only_recurring'),
            ]);
        }

        if (($attributes['total_amount'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'total_amount' => 'I piani rateali richiedono un totale positivo.',
            ]);
        }

        if (($attributes['installments_count'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'installments_count' => 'I piani rateali richiedono un numero di rate positivo.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function validateEndMode(array $attributes): void
    {
        if (! in_array($attributes['end_mode'], RecurringEndModeEnum::values(), true)) {
            throw ValidationException::withMessages([
                'end_mode' => __('ui.the_plan_end_mode_is_unsupported_4bbb6e8c'),
            ]);
        }

        if ($attributes['end_mode'] === RecurringEndModeEnum::AFTER_OCCURRENCES->value
            && ($attributes['occurrences_limit'] ?? 0) <= 0
            && $attributes['entry_type'] !== RecurringEntryTypeEnum::INSTALLMENT->value) {
            throw ValidationException::withMessages([
                'occurrences_limit' => __('ui.the_after_occurrences_mode_requires_a_positive_limit_1433e463'),
            ]);
        }

        if ($attributes['end_mode'] === RecurringEndModeEnum::UNTIL_DATE->value
            && $attributes['end_date'] === null) {
            throw ValidationException::withMessages([
                'end_date' => __('ui.the_until_date_mode_requires_an_end_date_b57d98ad'),
            ]);
        }

        if ($attributes['end_date'] !== null && $attributes['end_date'] < $attributes['start_date']) {
            throw ValidationException::withMessages([
                'end_date' => __('ui.the_end_date_cannot_precede_the_start_date_4a111194'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function validateRecurrence(array $attributes): void
    {
        if (! in_array($attributes['recurrence_type'], RecurringEntryRecurrenceTypeEnum::values(), true)) {
            throw ValidationException::withMessages([
                'recurrence_type' => __('ui.the_recurrence_frequency_is_unsupported_e0a5776c'),
            ]);
        }

        if (($attributes['recurrence_interval'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'recurrence_interval' => __('ui.the_recurrence_interval_must_be_positive_4e8b723e'),
            ]);
        }

        $rule = $attributes['recurrence_rule'] ?? [];

        match ($attributes['recurrence_type']) {
            RecurringEntryRecurrenceTypeEnum::DAILY->value => null,
            RecurringEntryRecurrenceTypeEnum::WEEKLY->value => $this->validateWeeklyRule($rule, $attributes),
            RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
            RecurringEntryRecurrenceTypeEnum::QUARTERLY->value => $this->validateMonthlyRule($rule, $attributes),
            RecurringEntryRecurrenceTypeEnum::YEARLY->value => $this->validateYearlyRule($rule, $attributes),
            default => throw ValidationException::withMessages([
                'recurrence_type' => __('ui.the_requested_recurrence_frequency_is_unsupported_in_v1_8d640c87'),
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeRecurrenceRule(mixed $rule): array
    {
        if ($rule === null || $rule === '') {
            return [];
        }

        if (is_string($rule)) {
            $decoded = json_decode($rule, true);

            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'recurrence_rule' => __('ui.the_recurrence_rule_must_be_valid_json_5f8016e8'),
                ]);
            }

            return $decoded;
        }

        if (! is_array($rule)) {
            throw ValidationException::withMessages([
                'recurrence_rule' => __('ui.the_recurrence_rule_must_be_a_valid_array_be838b29'),
            ]);
        }

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $attributes
     */
    protected function validateWeeklyRule(array $rule, array $attributes): void
    {
        $weekdays = Arr::wrap($rule['weekdays'] ?? [$this->weekdayCode(CarbonImmutable::parse($attributes['start_date']))]);
        $allowedWeekdays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        if ($weekdays === [] || array_diff($weekdays, $allowedWeekdays) !== []) {
            throw ValidationException::withMessages([
                'recurrence_rule' => __('ui.a_weekly_recurrence_requires_valid_weekdays_6181df84'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $attributes
     */
    protected function validateMonthlyRule(array $rule, array $attributes): void
    {
        $mode = (string) ($rule['mode'] ?? 'day_of_month');

        if ($mode === 'day_of_month') {
            $day = (int) ($rule['day'] ?? $attributes['due_day'] ?? CarbonImmutable::parse($attributes['start_date'])->day);

            if ($day < 1 || $day > 31) {
                throw ValidationException::withMessages([
                    'recurrence_rule' => __('ui.a_monthly_day_of_month_recurrence_requires_a_valid_day_6f0de6e7'),
                ]);
            }

            return;
        }

        if ($mode !== 'ordinal_weekday') {
            throw ValidationException::withMessages([
                'recurrence_rule' => __('ui.a_monthly_recurrence_must_use_day_of_month_or_ordinal_weekday_36cb1c4b'),
            ]);
        }

        $ordinal = (string) ($rule['ordinal'] ?? '');
        $weekday = (string) ($rule['weekday'] ?? '');

        if (! in_array($ordinal, ['first', 'second', 'third', 'fourth', 'last'], true)
            || ! in_array($weekday, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true)) {
            throw ValidationException::withMessages([
                'recurrence_rule' => __('ui.a_monthly_ordinal_weekday_recurrence_requires_valid_ordinal_and__20f4df8f'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $attributes
     */
    protected function validateYearlyRule(array $rule, array $attributes): void
    {
        $mode = (string) ($rule['mode'] ?? 'month_day');
        $month = (int) ($rule['month'] ?? CarbonImmutable::parse($attributes['start_date'])->month);

        if ($month < 1 || $month > 12) {
            throw ValidationException::withMessages([
                'recurrence_rule' => __('ui.a_yearly_recurrence_requires_a_valid_month_028d3672'),
            ]);
        }

        if ($mode === 'month_day') {
            $day = (int) ($rule['day'] ?? CarbonImmutable::parse($attributes['start_date'])->day);

            if ($day < 1 || $day > 31) {
                throw ValidationException::withMessages([
                    'recurrence_rule' => __('ui.a_yearly_month_day_recurrence_requires_a_valid_day_9e120f21'),
                ]);
            }

            return;
        }

        if ($mode !== 'ordinal_weekday') {
            throw ValidationException::withMessages([
                'recurrence_rule' => __('ui.a_yearly_recurrence_must_use_month_day_or_ordinal_weekday_dc0a0d3d'),
            ]);
        }

        $ordinal = (string) ($rule['ordinal'] ?? '');
        $weekday = (string) ($rule['weekday'] ?? '');

        if (! in_array($ordinal, ['first', 'second', 'third', 'fourth', 'last'], true)
            || ! in_array($weekday, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true)) {
            throw ValidationException::withMessages([
                'recurrence_rule' => __('ui.a_yearly_ordinal_weekday_recurrence_requires_valid_ordinal_and_w_bc65919c'),
            ]);
        }
    }

    protected function weekdayCode(CarbonImmutable $date): string
    {
        return match ($date->dayOfWeekIso) {
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            default => 'sun',
        };
    }
}
