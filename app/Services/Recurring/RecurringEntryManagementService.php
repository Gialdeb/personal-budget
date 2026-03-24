<?php

namespace App\Services\Recurring;

use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\User;
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
        'total_amount',
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
        protected RecurringEntryLifecycleService $lifecycle
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(User $user, array $validated): RecurringEntry
    {
        return DB::transaction(function () use ($user, $validated): RecurringEntry {
            $normalized = $this->validator->validate($user, $validated);
            $entry = RecurringEntry::query()->create($normalized);

            if ($entry->status === RecurringEntryStatusEnum::ACTIVE && $entry->is_active) {
                $this->primeEntryLifecycle($entry);
            }

            return $entry->fresh([
                'account',
                'category',
                'trackedItem',
                'merchant',
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
            $entry->load('occurrences');
            $hasConvertedOccurrences = $entry->occurrences()
                ->whereNotNull('converted_transaction_id')
                ->exists();

            if ($hasConvertedOccurrences) {
                $changedStructuralFields = collect($this->structuralFields)
                    ->filter(fn (string $field): bool => $this->fieldChanged($entry, $field, $validated))
                    ->values()
                    ->all();

                if ($changedStructuralFields !== []) {
                    throw ValidationException::withMessages([
                        'entry' => 'Il piano ha già occorrenze convertite e non può modificare i campi strutturali: '
                            .implode(', ', $changedStructuralFields).'.',
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
                ]));
                $entry->save();

                return $entry->fresh([
                    'account',
                    'category',
                    'trackedItem',
                    'merchant',
                    'occurrences.convertedTransaction',
                ]);
            }

            $normalized = $this->validator->validate($user, [
                ...$entry->getAttributes(),
                ...$validated,
            ]);

            $entry->fill($normalized);
            $entry->save();
            $entry->occurrences()->delete();

            if ($entry->status === RecurringEntryStatusEnum::ACTIVE && $entry->is_active) {
                $this->primeEntryLifecycle($entry);
            } else {
                $entry->forceFill([
                    'next_occurrence_date' => $entry->start_date,
                ])->save();
            }

            return $entry->fresh([
                'account',
                'category',
                'trackedItem',
                'merchant',
                'occurrences.convertedTransaction',
            ]);
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
        $this->ensureOccurrenceMutable($occurrence, 'saltata');

        $occurrence->forceFill([
            'status' => RecurringOccurrenceStatusEnum::SKIPPED,
        ])->save();

        return $occurrence->fresh(['convertedTransaction']);
    }

    public function cancelOccurrence(RecurringEntryOccurrence $occurrence): RecurringEntryOccurrence
    {
        $this->ensureOccurrenceMutable($occurrence, 'annullata');

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

    protected function ensureOccurrenceMutable(RecurringEntryOccurrence $occurrence, string $targetState): void
    {
        if ($occurrence->converted_transaction_id !== null) {
            throw ValidationException::withMessages([
                'occurrence' => "L'occorrenza non può essere {$targetState} perché è già convertita.",
            ]);
        }

        if (! in_array($occurrence->status, [
            RecurringOccurrenceStatusEnum::PENDING,
            RecurringOccurrenceStatusEnum::GENERATED,
        ], true)) {
            throw ValidationException::withMessages([
                'occurrence' => "L'occorrenza non può essere {$targetState} nello stato corrente.",
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
}
