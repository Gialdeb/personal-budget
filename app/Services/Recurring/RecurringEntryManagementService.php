<?php

namespace App\Services\Recurring;

use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\User;
use App\Services\UserYearService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringEntryManagementService
{
    /**
     * @var array<int, string>
     */
    protected array $structuralFields = [
        'entry_type',
        'direction',
        'account_id',
        'category_id',
        'tracked_item_id',
        'merchant_id',
        'currency',
        'start_date',
        'trial_duration',
        'trial_unit',
        'installments_count',
        'recurrence_type',
        'recurrence_interval',
        'recurrence_rule',
        'end_mode',
        'end_date',
        'occurrences_limit',
    ];

    public function __construct(
        protected RecurringEntryValidatorService $validator,
        protected RecurringEntryOccurrenceGeneratorService $generator,
        protected RecurringEntryLifecycleService $lifecycle,
        protected UserYearService $userYearService,
        protected RecurringEntryAmountHistoryService $amountHistory,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(User $user, array $validated): RecurringEntry
    {
        return DB::transaction(function () use ($user, $validated): RecurringEntry {
            $normalized = $this->validator->validate($user, $validated);
            $this->ensureSelectedFutureYearsExist($user, $normalized);
            $normalized['created_by_user_id'] = $user->id;
            $normalized['updated_by_user_id'] = $user->id;
            $entry = RecurringEntry::query()->create($normalized);

            if ($entry->status === RecurringEntryStatusEnum::ACTIVE && $entry->is_active) {
                $this->primeEntryLifecycle($entry);
            }

            return $entry->fresh([
                'account',
                'category',
                'trackedItem',
                'merchant',
                'createdByUser',
                'updatedByUser',
                'occurrences.convertedTransaction',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(User $user, RecurringEntry $entry, array $validated): RecurringEntry
    {
        return DB::transaction(function () use ($user, $entry, $validated): RecurringEntry {
            $entry = RecurringEntry::query()->lockForUpdate()->findOrFail($entry->getKey());
            $entry->load('occurrences');
            $originalEntry = clone $entry;
            $trialChanged = collect(['trial_duration', 'trial_unit', 'start_date'])
                ->contains(fn (string $field): bool => array_key_exists($field, $validated) && $this->fieldChanged($entry, $field, $validated));
            $hasTrialPayment = (bool) $entry->trial_duration && $entry->occurrences()->where(function ($query): void {
                $query->whereNotNull('converted_transaction_id')->orWhereNotNull('matched_transaction_id')
                    ->orWhereHas('paymentAllocations', fn ($allocations) => $allocations->valid())
                    ->orWhereHas('sharedExpenseLedgerEntry')->orWhere('status', 'completed');
            })->exists();
            if (($entry->trial_duration || filled($validated['trial_duration'] ?? null)) && $trialChanged
                && $entry->occurrences()->where(function ($query): void {
                    $query->whereNotNull('converted_transaction_id')->orWhereNotNull('matched_transaction_id')
                        ->orWhereHas('paymentAllocations', fn ($allocations) => $allocations->valid())
                        ->orWhereHas('sharedExpenseLedgerEntry')->orWhere('status', 'completed');
                })->exists()) {
                throw ValidationException::withMessages(['trial_duration' => __('ui.the_trial_cannot_be_edited_after_a_payment_has_been_recorded_88884f6f')]);
            }
            $hasConvertedOccurrences = $entry->occurrences()
                ->whereNotNull('converted_transaction_id')
                ->exists();
            $canRealignRefundedSchedule = $entry->entry_type === RecurringEntryTypeEnum::RECURRING
                && ! $entry->trial_duration
                && $entry->occurrences()->where('status', RecurringOccurrenceStatusEnum::REFUNDED)
                    ->whereHas('convertedTransaction.refundTransaction')->exists();

            if ($hasConvertedOccurrences || $hasTrialPayment) {
                if ($entry->trial_duration) {
                    $validated = $this->validator->validate($user, [...$entry->getAttributes(), ...$validated]);
                }
                if (
                    $entry->entry_type === RecurringEntryTypeEnum::INSTALLMENT
                    && (bool) ($validated['is_amount_variable'] ?? false)
                ) {
                    throw ValidationException::withMessages([
                        'is_amount_variable' => __('transactions.validation.recurring_variable_amount_only_recurring'),
                    ]);
                }

                $changedStructuralFields = collect($this->structuralFields)
                    ->reject(fn (string $field): bool => $canRealignRefundedSchedule && $field === 'start_date')
                    ->filter(fn (string $field): bool => array_key_exists($field, $validated) && $this->fieldChanged($entry, $field, $validated))
                    ->values()
                    ->all();

                if ($changedStructuralFields !== []) {
                    throw ValidationException::withMessages([
                        'entry' => __('ui.the_plan_already_has_converted_occurrences_and_these_structural__53f5483a')
                            .implode(', ', $changedStructuralFields).'.',
                    ]);
                }

                $totalAmountChanged = $entry->entry_type === RecurringEntryTypeEnum::INSTALLMENT
                    && $this->amountChanged((float) $entry->total_amount, $validated['total_amount'] ?? null);

                if ($totalAmountChanged && ! (bool) ($validated['confirm_installment_recalculation'] ?? false)) {
                    throw ValidationException::withMessages([
                        'total_amount' => __('ui.confirm_plan_recalculation_before_updating_the_total_amount_29b9e1bf'),
                    ]);
                }

                $entry->fill(Arr::only($validated, [
                    'title',
                    'description',
                    'notes',
                    'status',
                    'is_active',
                    'auto_create_transaction',
                    'auto_generate_occurrences',
                    'is_amount_variable',
                    'reminder_days_before',
                ]));
                $entry->forceFill([
                    'updated_by_user_id' => $user->id,
                ]);
                $entry->save();

                if ($canRealignRefundedSchedule && array_key_exists('start_date', $validated)) {
                    $normalized = $this->validator->validate($user, [...$entry->getAttributes(), ...$validated]);
                    $entry->forceFill(['start_date' => $normalized['start_date']])->save();
                }

                if ($entry->entry_type === RecurringEntryTypeEnum::RECURRING && array_key_exists('expected_amount', $validated)) {
                    $entry->forceFill(['expected_amount' => $validated['expected_amount']])->save();
                }
                if ($totalAmountChanged) {
                    $entry->forceFill(['total_amount' => round((float) $validated['total_amount'], 2)])->save();
                }
                $effectiveFrom = $this->generator->synchronizeFutureOccurrences($entry, $originalEntry);
                $this->amountHistory->record($entry, $originalEntry, $effectiveFrom);
                if ($canRealignRefundedSchedule && $entry->start_date->ne($originalEntry->start_date)) {
                    $this->primeEntryLifecycle($entry);
                }

                return $entry->fresh([
                    'account',
                    'category',
                    'trackedItem',
                    'merchant',
                    'createdByUser',
                    'updatedByUser',
                    'occurrences.convertedTransaction',
                ]);
            }

            $normalized = $this->validator->validate($user, [
                ...$entry->getAttributes(),
                ...$validated,
            ]);
            $this->ensureSelectedFutureYearsExist($user, $normalized);

            $entry->fill($normalized);
            $entry->forceFill([
                'updated_by_user_id' => $user->id,
            ]);
            $entry->save();
            $effectiveFrom = $this->generator->synchronizeFutureOccurrences($entry, $originalEntry);
            $this->amountHistory->record($entry, $originalEntry, $effectiveFrom);

            if ($entry->status === RecurringEntryStatusEnum::ACTIVE && $entry->is_active) {
                $this->primeEntryLifecycle($entry);
            } else {
                $entry->forceFill([
                    'next_occurrence_date' => $entry->firstBillingDate(),
                ])->save();
            }

            return $entry->fresh([
                'account',
                'category',
                'trackedItem',
                'merchant',
                'createdByUser',
                'updatedByUser',
                'occurrences.convertedTransaction',
            ]);
        });
    }

    public function skipNextOccurrences(RecurringEntry $entry, int $periods): void
    {
        DB::transaction(function () use ($entry, $periods): void {
            $entry = RecurringEntry::query()->lockForUpdate()->findOrFail($entry->getKey());
            if ($entry->entry_type !== RecurringEntryTypeEnum::RECURRING || $entry->status !== RecurringEntryStatusEnum::ACTIVE || ! $entry->is_active || $periods < 1 || $periods > 1000) {
                throw ValidationException::withMessages(['periods' => __('ui.this_recurring_entry_cannot_be_temporarily_suspended_2a894c88')]);
            }
            $today = CarbonImmutable::today(config('app.timezone'));
            $query = $entry->occurrences()
                ->where('status', RecurringOccurrenceStatusEnum::PENDING->value)
                ->whereNull('converted_transaction_id')->whereNull('matched_transaction_id')
                ->whereDoesntHave('sharedExpenseLedgerEntry')
                ->where(function ($query) use ($today): void {
                    $query->whereDate('due_date', '>=', $today)->orWhere(function ($fallback) use ($today): void {
                        $fallback->whereNull('due_date')->whereDate('expected_date', '>=', $today);
                    });
                })
                ->orderByRaw('COALESCE(due_date, expected_date)')->orderBy('sequence_number')
                ->limit($periods)->lockForUpdate();
            $occurrences = $query->get();
            if ($occurrences->count() < $periods) {
                $pastLimit = max(1, (int) CarbonImmutable::parse($entry->start_date)->diffInDays($today, false) + 1);
                $limit = $entry->end_mode === RecurringEndModeEnum::AFTER_OCCURRENCES ? (int) $entry->occurrences_limit : null;
                $this->generator->generate($entry, $today, $limit === null ? $pastLimit : min($pastLimit, $limit), false);
                $futureLimit = (int) $entry->occurrences()->max('sequence_number') + $periods;
                $this->generator->generate($entry, null, $limit === null ? $futureLimit : min($futureLimit, $limit), false);
                $occurrences = $query->get();
            }
            if ($occurrences->count() !== $periods) {
                throw ValidationException::withMessages(['periods' => __('ui.the_plan_does_not_contain_enough_future_occurrences_to_skip_cc9767d7')]);
            }
            foreach ($occurrences as $occurrence) {
                $this->skipOccurrence($occurrence);
            }
        });
    }

    public function pause(RecurringEntry $entry): RecurringEntry
    {
        $entry->forceFill([
            'status' => RecurringEntryStatusEnum::PAUSED,
            'is_active' => false,
        ])->save();

        return $entry->fresh(['occurrences']);
    }

    public function resume(RecurringEntry $entry): RecurringEntry
    {
        $entry->forceFill([
            'status' => RecurringEntryStatusEnum::ACTIVE,
            'is_active' => true,
        ])->save();

        return $entry->fresh(['occurrences']);
    }

    public function cancel(RecurringEntry $entry): RecurringEntry
    {
        DB::transaction(function () use ($entry): void {
            $entry->forceFill([
                'status' => RecurringEntryStatusEnum::CANCELLED,
                'is_active' => false,
                'next_occurrence_date' => null,
            ])->save();

            $entry->occurrences()
                ->whereNull('converted_transaction_id')
                ->whereIn('status', [
                    RecurringOccurrenceStatusEnum::PENDING->value,
                    RecurringOccurrenceStatusEnum::GENERATED->value,
                ])
                ->update([
                    'status' => RecurringOccurrenceStatusEnum::CANCELLED->value,
                ]);
        });

        return $entry->fresh(['occurrences']);
    }

    public function skipOccurrence(RecurringEntryOccurrence $occurrence): RecurringEntryOccurrence
    {
        return DB::transaction(function () use ($occurrence): RecurringEntryOccurrence {
            $occurrence = RecurringEntryOccurrence::query()->lockForUpdate()->findOrFail($occurrence->getKey());
            $this->ensureOccurrenceMutable($occurrence, __('ui.skipped'));
            $occurrence->forceFill(['status' => RecurringOccurrenceStatusEnum::SKIPPED])->save();

            return $occurrence->fresh(['convertedTransaction']);
        });
    }

    public function restoreOccurrence(RecurringEntryOccurrence $occurrence): RecurringEntryOccurrence
    {
        return DB::transaction(function () use ($occurrence): RecurringEntryOccurrence {
            $occurrence = RecurringEntryOccurrence::query()->lockForUpdate()->findOrFail($occurrence->getKey());
            if ($occurrence->status !== RecurringOccurrenceStatusEnum::SKIPPED || $occurrence->converted_transaction_id !== null || $occurrence->matched_transaction_id !== null || $occurrence->sharedExpenseLedgerEntry()->exists()) {
                throw ValidationException::withMessages(['occurrence' => __('ui.this_occurrence_cannot_be_restored_30386567')]);
            }
            $occurrence->forceFill(['status' => RecurringOccurrenceStatusEnum::PENDING])->save();

            return $occurrence->fresh(['convertedTransaction']);
        });
    }

    public function cancelOccurrence(RecurringEntryOccurrence $occurrence): RecurringEntryOccurrence
    {
        $this->ensureOccurrenceMutable($occurrence, __('ui.canceled'));

        $occurrence->forceFill([
            'status' => RecurringOccurrenceStatusEnum::CANCELLED,
        ])->save();

        return $occurrence->fresh(['convertedTransaction']);
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function fieldChanged(RecurringEntry $entry, string $field, array $normalized): bool
    {
        $current = $entry->{$field};
        $newValue = $normalized[$field] ?? null;

        if ($current instanceof \BackedEnum) {
            $current = $current->value;
        }

        if ($current instanceof CarbonInterface) {
            $current = $current->toDateString();
        }

        if (is_array($current) || is_array($newValue)) {
            return $current !== $newValue;
        }

        return (string) ($current ?? '') !== (string) ($newValue ?? '');
    }

    protected function amountChanged(float $currentAmount, mixed $newAmount): bool
    {
        return (int) round($currentAmount * 100) !== (int) round((float) $newAmount * 100);
    }

    protected function ensureOccurrenceMutable(RecurringEntryOccurrence $occurrence, string $targetState): void
    {
        if ($occurrence->paymentAllocations()->valid()->exists()) {
            throw ValidationException::withMessages(['occurrence' => __('ui.this_occurrence_has_allocated_payments_75cb9153')]);
        }
        if ($occurrence->converted_transaction_id !== null || $occurrence->matched_transaction_id !== null || $occurrence->sharedExpenseLedgerEntry()->exists()) {
            throw ValidationException::withMessages([
                'occurrence' => __('ui.occurrence_already_converted', ['state' => $targetState]),
            ]);
        }

        if (! in_array($occurrence->status, [
            RecurringOccurrenceStatusEnum::PENDING,
            RecurringOccurrenceStatusEnum::GENERATED,
        ], true)) {
            throw ValidationException::withMessages([
                'occurrence' => __('ui.occurrence_invalid_state', ['state' => $targetState]),
            ]);
        }
    }

    protected function primeEntryLifecycle(RecurringEntry $entry): void
    {
        if ($entry->auto_create_transaction) {
            $this->lifecycle->synchronize($entry, CarbonImmutable::today(config('app.timezone')));

            $entry->refresh();

            if ($entry->entry_type?->value === 'installment' || $entry->end_mode?->value !== 'never') {
                $this->generator->generate($entry);

                return;
            }

            $this->generator->generate($entry, null, $entry->occurrences()->count() + $this->previewOccurrencesBuffer($entry));

            return;
        }

        $this->generator->generate($entry);
    }

    protected function previewOccurrencesBuffer(RecurringEntry $entry): int
    {
        return match ($entry->recurrence_type?->value) {
            'daily' => 30,
            'weekly' => 26,
            default => 12,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function ensureSelectedFutureYearsExist(User $user, array $attributes): void
    {
        $owner = User::query()->findOrFail((int) $attributes['user_id']);
        $futureDates = collect([
            'start_date' => $attributes['start_date'] ?? null,
            'trial_duration' => (new RecurringEntry($attributes))->firstBillingDate()->toDateString(),
            'end_date' => $attributes['end_date'] ?? null,
        ])
            ->filter(fn ($date): bool => filled($date) && CarbonImmutable::parse((string) $date)->isFuture())
            ->unique();

        collect([$user, $owner])
            ->unique(fn (User $user): int => $user->id)
            ->each(function (User $yearOwner) use ($futureDates): void {
                $futureDates->each(
                    fn ($date, string $errorKey) => $this->userYearService->ensureDateYearExistsAndIsOpen(
                        $yearOwner,
                        (string) $date,
                        $errorKey,
                    )
                );
            });
    }
}
