<?php

namespace App\Services\Recurring;

use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Models\Account;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Scope;
use App\Models\TrackedItem;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class RecurringEntryValidatorService
{
    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected OperationalTransactionCategoryResolver $operationalTransactionCategoryResolver
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
        $normalized['total_amount'] = filled($normalized['total_amount'] ?? null)
            ? round((float) $normalized['total_amount'], 2)
            : null;
        $normalized['recurrence_rule'] = $this->normalizeRecurrenceRule(
            $normalized['recurrence_rule'] ?? null
        );

        $ownerUserId = $this->validateCommon($user, $normalized);
        $normalized['user_id'] = $ownerUserId;
        $this->validateEntryType($normalized);
        $this->validateEndMode($normalized);
        $this->validateRecurrence($normalized);

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
        $normalized['next_occurrence_date'] = $normalized['start_date'];

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function validateCommon(User $user, array $attributes): int
    {
        if (($attributes['title'] ?? '') === '') {
            throw ValidationException::withMessages([
                'title' => 'Il titolo del piano è obbligatorio.',
            ]);
        }

        if (! in_array($attributes['direction'], [
            TransactionDirectionEnum::INCOME->value,
            TransactionDirectionEnum::EXPENSE->value,
        ], true)) {
            throw ValidationException::withMessages([
                'direction' => 'La direzione del piano deve essere income o expense.',
            ]);
        }

        if ($attributes['start_date'] === null) {
            throw ValidationException::withMessages([
                'start_date' => 'La data iniziale del piano è obbligatoria.',
            ]);
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
                'currency' => 'La valuta del piano è obbligatoria.',
            ]);
        }

        $category = $this->operationalTransactionCategoryResolver->findCategoryForAccount(
            $account,
            (int) ($attributes['category_id'] ?? 0),
        );
        if (! $category instanceof Category) {
            throw ValidationException::withMessages([
                'category_id' => 'La categoria selezionata non è disponibile per il conto scelto.',
            ]);
        }

        if ($category->direction_type?->value !== $attributes['direction']) {
            throw ValidationException::withMessages([
                'direction' => 'La direzione del piano deve essere coerente con la categoria selezionata.',
            ]);
        }

        if (filled($attributes['scope_id'] ?? null)) {
            $scope = $this->operationalTransactionCategoryResolver->findScopeForAccount(
                $account,
                (int) $attributes['scope_id'],
            );

            if (! $scope instanceof Scope) {
                throw ValidationException::withMessages([
                    'scope_id' => 'Lo scope selezionato non è disponibile per il conto scelto.',
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
                    'tracked_item_id' => 'L’elemento tracciato selezionato non è disponibile per il conto scelto.',
                ]);
            }
        }

        if (filled($attributes['merchant_id'] ?? null)) {
            $merchant = Merchant::query()
                ->where('user_id', $ownerUserId)
                ->find($attributes['merchant_id']);

            if (! $merchant instanceof Merchant) {
                throw ValidationException::withMessages([
                    'merchant_id' => 'L’esercente selezionato non appartiene all’utente.',
                ]);
            }
        }

        return $ownerUserId;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function validateEntryType(array $attributes): void
    {
        if (! in_array($attributes['entry_type'], RecurringEntryTypeEnum::values(), true)) {
            throw ValidationException::withMessages([
                'entry_type' => 'Il tipo di piano non è supportato.',
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
                    'entry_type' => 'I piani ricorrenti non accettano total_amount o installments_count.',
                ]);
            }

            return;
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
                'end_mode' => 'La modalità di fine del piano non è supportata.',
            ]);
        }

        if ($attributes['end_mode'] === RecurringEndModeEnum::AFTER_OCCURRENCES->value
            && ($attributes['occurrences_limit'] ?? 0) <= 0
            && $attributes['entry_type'] !== RecurringEntryTypeEnum::INSTALLMENT->value) {
            throw ValidationException::withMessages([
                'occurrences_limit' => 'La modalità after_occurrences richiede un limite positivo.',
            ]);
        }

        if ($attributes['end_mode'] === RecurringEndModeEnum::UNTIL_DATE->value
            && $attributes['end_date'] === null) {
            throw ValidationException::withMessages([
                'end_date' => 'La modalità until_date richiede una data finale.',
            ]);
        }

        if ($attributes['end_date'] !== null && $attributes['end_date'] < $attributes['start_date']) {
            throw ValidationException::withMessages([
                'end_date' => 'La data finale non può essere precedente alla data iniziale.',
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
                'recurrence_type' => 'La frequenza di ricorrenza non è supportata.',
            ]);
        }

        if (($attributes['recurrence_interval'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'recurrence_interval' => 'L’intervallo di ricorrenza deve essere positivo.',
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
                'recurrence_type' => 'La frequenza di ricorrenza richiesta non è supportata in v1.',
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
                    'recurrence_rule' => 'La recurrence_rule deve essere un JSON valido.',
                ]);
            }

            return $decoded;
        }

        if (! is_array($rule)) {
            throw ValidationException::withMessages([
                'recurrence_rule' => 'La recurrence_rule deve essere un array valido.',
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
                'recurrence_rule' => 'La ricorrenza weekly richiede weekdays validi.',
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
                    'recurrence_rule' => 'La ricorrenza monthly day_of_month richiede un giorno valido.',
                ]);
            }

            return;
        }

        if ($mode !== 'ordinal_weekday') {
            throw ValidationException::withMessages([
                'recurrence_rule' => 'La ricorrenza monthly deve usare day_of_month oppure ordinal_weekday.',
            ]);
        }

        $ordinal = (string) ($rule['ordinal'] ?? '');
        $weekday = (string) ($rule['weekday'] ?? '');

        if (! in_array($ordinal, ['first', 'second', 'third', 'fourth', 'last'], true)
            || ! in_array($weekday, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true)) {
            throw ValidationException::withMessages([
                'recurrence_rule' => 'La ricorrenza monthly ordinal_weekday richiede ordinal e weekday validi.',
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
                'recurrence_rule' => 'La ricorrenza yearly richiede un mese valido.',
            ]);
        }

        if ($mode === 'month_day') {
            $day = (int) ($rule['day'] ?? CarbonImmutable::parse($attributes['start_date'])->day);

            if ($day < 1 || $day > 31) {
                throw ValidationException::withMessages([
                    'recurrence_rule' => 'La ricorrenza yearly month_day richiede un giorno valido.',
                ]);
            }

            return;
        }

        if ($mode !== 'ordinal_weekday') {
            throw ValidationException::withMessages([
                'recurrence_rule' => 'La ricorrenza yearly deve usare month_day oppure ordinal_weekday.',
            ]);
        }

        $ordinal = (string) ($rule['ordinal'] ?? '');
        $weekday = (string) ($rule['weekday'] ?? '');

        if (! in_array($ordinal, ['first', 'second', 'third', 'fourth', 'last'], true)
            || ! in_array($weekday, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true)) {
            throw ValidationException::withMessages([
                'recurrence_rule' => 'La ricorrenza yearly ordinal_weekday richiede ordinal e weekday validi.',
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
