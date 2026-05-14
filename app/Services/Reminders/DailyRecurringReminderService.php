<?php

namespace App\Services\Reminders;

use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\ScheduledEntryStatusEnum;
use App\Models\RecurringEntryOccurrence;
use App\Models\ScheduledEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;

class DailyRecurringReminderService
{
    public function __construct(
        protected ReminderNotificationDispatcher $dispatcher,
    ) {}

    /**
     * @return array{scanned: int, skipped: int, notified: int, pushed: int, duplicates: int}
     */
    public function run(): array
    {
        $result = $this->emptyResult();

        if (! (bool) config('reminders.enabled', true)) {
            return $result;
        }

        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $windowEnd = $today->copy()->addDays((int) config('reminders.due_soon_days', 3));

        RecurringEntryOccurrence::query()
            ->with(['recurringEntry.user.settings', 'recurringEntry.trackedItem'])
            ->where('status', RecurringOccurrenceStatusEnum::PENDING->value)
            ->whereNull('matched_transaction_id')
            ->whereNull('converted_transaction_id')
            ->whereHas('recurringEntry', function ($query): void {
                $query
                    ->where('is_active', true)
                    ->where('status', RecurringEntryStatusEnum::ACTIVE->value);
            })
            ->where(function ($query) use ($windowEnd): void {
                $query
                    ->whereDate('due_date', '<=', $windowEnd->toDateString())
                    ->orWhere(function ($query) use ($windowEnd): void {
                        $query
                            ->whereNull('due_date')
                            ->whereDate('expected_date', '<=', $windowEnd->toDateString());
                    });
            })
            ->orderByRaw('COALESCE(due_date, expected_date)')
            ->chunkById(100, function ($occurrences) use (&$result, $today): void {
                foreach ($occurrences as $occurrence) {
                    $result['scanned']++;
                    $dispatchResult = $this->dispatchOccurrence($occurrence, $today);
                    $this->applyDispatchResult($result, $dispatchResult);
                }
            });

        ScheduledEntry::query()
            ->with(['user.settings', 'trackedItem'])
            ->whereIn('status', [
                ScheduledEntryStatusEnum::PLANNED->value,
                ScheduledEntryStatusEnum::DUE->value,
            ])
            ->whereNull('matched_transaction_id')
            ->whereDate('scheduled_date', '<=', $windowEnd->toDateString())
            ->orderBy('scheduled_date')
            ->chunkById(100, function ($scheduledEntries) use (&$result, $today): void {
                foreach ($scheduledEntries as $scheduledEntry) {
                    $result['scanned']++;
                    $dispatchResult = $this->dispatchScheduledEntry($scheduledEntry, $today);
                    $this->applyDispatchResult($result, $dispatchResult);
                }
            });

        return $result;
    }

    /**
     * @return array{status: string, pushed: int}
     */
    protected function dispatchOccurrence(RecurringEntryOccurrence $occurrence, Carbon $today): array
    {
        $entry = $occurrence->recurringEntry;
        $dueDate = Carbon::parse(
            ($occurrence->due_date ?? $occurrence->expected_date)->toDateString(),
            config('app.timezone'),
        )->startOfDay();
        $status = $this->statusForDate($dueDate, $today);
        $planType = $entry->auto_create_transaction ? 'automatic' : 'manual';
        $locale = $entry->user->preferredLocale();
        $previousLocale = App::getLocale();

        App::setLocale($locale);

        try {
            $targetUrl = route('recurring-entries.show', $entry->uuid, false);
            $amount = $this->formatMoney((string) ($occurrence->expected_amount ?? $entry->expected_amount), $entry->currency);
            $description = $this->descriptionFor($entry);
            $translationBase = $status === 'overdue'
                ? 'notifications.reminders.recurring.overdue'
                : ($planType === 'automatic'
                    ? 'notifications.reminders.recurring.automatic'
                    : ($status === 'upcoming'
                        ? 'notifications.reminders.recurring.manual_upcoming'
                        : 'notifications.reminders.recurring.manual'));

            return $this->dispatcher->dispatch(
                $entry->user,
                $occurrence,
                'recurring_due_reminders',
                'reminders.recurring_due',
                'recurring_due_reminder_database',
                'recurring_due_reminder',
                'recurring_'.$status,
                $dueDate,
                __($translationBase.'.title'),
                __($translationBase.'.body', [
                    'description' => $description,
                    'date' => $this->formatDate($dueDate, $locale),
                    'amount' => $amount,
                ]),
                $targetUrl,
                $this->severityForStatus($status),
                [
                    'kind' => 'recurring_due_reminder',
                    'recurring_entry_uuid' => $entry->uuid,
                    'occurrence_uuid' => $occurrence->uuid,
                    'occurrence_date' => $dueDate->toDateString(),
                    'plan_type' => $planType,
                    'status' => $status,
                    'amount' => (string) ($occurrence->expected_amount ?? $entry->expected_amount),
                    'currency_code' => $entry->currency,
                    'target_url' => $targetUrl,
                ],
            );
        } finally {
            App::setLocale($previousLocale);
        }
    }

    /**
     * @return array{status: string, pushed: int}
     */
    protected function dispatchScheduledEntry(ScheduledEntry $scheduledEntry, Carbon $today): array
    {
        $dueDate = Carbon::parse($scheduledEntry->scheduled_date->toDateString(), config('app.timezone'))->startOfDay();
        $status = $this->statusForDate($dueDate, $today);
        $locale = $scheduledEntry->user->preferredLocale();
        $previousLocale = App::getLocale();

        App::setLocale($locale);

        try {
            $targetUrl = route('recurring-entries.index', [
                'year' => $dueDate->year,
                'month' => $dueDate->month,
                'highlight' => $scheduledEntry->uuid,
            ], false);
            $translationBase = $status === 'overdue'
                ? 'notifications.reminders.recurring.overdue'
                : ($status === 'upcoming'
                    ? 'notifications.reminders.recurring.manual_upcoming'
                    : 'notifications.reminders.recurring.manual');

            return $this->dispatcher->dispatch(
                $scheduledEntry->user,
                $scheduledEntry,
                'recurring_due_reminders',
                'reminders.recurring_due',
                'recurring_due_reminder_database',
                'recurring_due_reminder',
                'recurring_'.$status,
                $dueDate,
                __($translationBase.'.title'),
                __($translationBase.'.body', [
                    'description' => $this->descriptionFor($scheduledEntry),
                    'date' => $this->formatDate($dueDate, $locale),
                    'amount' => $this->formatMoney((string) $scheduledEntry->expected_amount, $scheduledEntry->currency),
                ]),
                $targetUrl,
                $this->severityForStatus($status),
                [
                    'kind' => 'recurring_due_reminder',
                    'scheduled_entry_uuid' => $scheduledEntry->uuid,
                    'occurrence_date' => $dueDate->toDateString(),
                    'plan_type' => 'manual',
                    'status' => $status,
                    'amount' => (string) $scheduledEntry->expected_amount,
                    'currency_code' => $scheduledEntry->currency,
                    'target_url' => $targetUrl,
                ],
            );
        } finally {
            App::setLocale($previousLocale);
        }
    }

    /**
     * @param  array{scanned: int, skipped: int, notified: int, pushed: int, duplicates: int}  $result
     * @param  array{status: string, pushed: int}  $dispatchResult
     */
    protected function applyDispatchResult(array &$result, array $dispatchResult): void
    {
        if ($dispatchResult['status'] === 'duplicate') {
            $result['duplicates']++;
        } elseif ($dispatchResult['status'] === 'notified') {
            $result['notified']++;
            $result['pushed'] += $dispatchResult['pushed'];
        } else {
            $result['skipped']++;
        }
    }

    protected function statusForDate(Carbon $dueDate, Carbon $today): string
    {
        if ($dueDate->lt($today)) {
            return 'overdue';
        }

        if ($dueDate->equalTo($today)) {
            return 'due_today';
        }

        return 'upcoming';
    }

    protected function severityForStatus(string $status): string
    {
        return match ($status) {
            'overdue' => 'danger',
            'due_today' => 'warning',
            default => 'info',
        };
    }

    protected function descriptionFor(Model $model): string
    {
        foreach (['title', 'description'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        $trackedItem = $model->getRelationValue('trackedItem');
        $trackedItemName = $trackedItem?->name;

        return is_string($trackedItemName) && trim($trackedItemName) !== ''
            ? $trackedItemName
            : __('notifications.reminders.recurring.fallback_description');
    }

    protected function formatMoney(string $amount, string $currencyCode): string
    {
        return number_format((float) $amount, 2, ',', '.').' '.$currencyCode;
    }

    protected function formatDate(Carbon $date, string $locale): string
    {
        return $locale === 'en'
            ? $date->format('M j, Y')
            : $date->format('d/m/Y');
    }

    /**
     * @return array{scanned: int, skipped: int, notified: int, pushed: int, duplicates: int}
     */
    protected function emptyResult(): array
    {
        return [
            'scanned' => 0,
            'skipped' => 0,
            'notified' => 0,
            'pushed' => 0,
            'duplicates' => 0,
        ];
    }
}
