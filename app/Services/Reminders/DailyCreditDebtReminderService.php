<?php

namespace App\Services\Reminders;

use App\Enums\CreditDebtTypeEnum;
use App\Models\CreditDebtItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;

class DailyCreditDebtReminderService
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

        if (! (bool) config('reminders.enabled', true) || ! (bool) config('features.credits_debts.enabled')) {
            return $result;
        }

        $today = Carbon::now(config('app.timezone'))->startOfDay();
        $windowEnd = $today->copy()->addDays((int) config('reminders.due_soon_days', 3));

        CreditDebtItem::query()
            ->with(['user.settings', 'reference', 'payments'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $windowEnd->toDateString())
            ->orderBy('due_date')
            ->chunkById(100, function ($items) use (&$result, $today): void {
                foreach ($items as $item) {
                    $result['scanned']++;

                    if ($item->isSettled() || (float) $item->remainingAmount() <= 0.0) {
                        $result['skipped']++;

                        continue;
                    }

                    $dispatchResult = $this->dispatchItem($item, $today);

                    if ($dispatchResult['status'] === 'duplicate') {
                        $result['duplicates']++;
                    } elseif ($dispatchResult['status'] === 'notified') {
                        $result['notified']++;
                        $result['pushed'] += $dispatchResult['pushed'];
                    } else {
                        $result['skipped']++;
                    }
                }
            });

        return $result;
    }

    /**
     * @return array{status: string, pushed: int}
     */
    protected function dispatchItem(CreditDebtItem $item, Carbon $today): array
    {
        $dueDate = Carbon::parse($item->due_date->toDateString(), config('app.timezone'))->startOfDay();
        $status = $this->statusForDate($dueDate, $today);
        $reminderType = 'credit_debt_'.$status;
        $locale = $item->user->preferredLocale();
        $previousLocale = App::getLocale();

        App::setLocale($locale);

        try {
            $targetUrl = route('credits-debts.index', [
                'year' => $dueDate->year,
                'month' => 'all',
                'highlight' => $item->uuid,
            ], false);
            $remaining = $this->formatMoney($item->remainingAmount(), $item->currency_code);
            $total = $this->formatMoney((string) $item->total_amount, $item->currency_code);
            $reference = is_string($item->reference?->name) && $item->reference->name !== ''
                ? $item->reference->name
                : null;
            $baseKey = $item->type === CreditDebtTypeEnum::CREDIT ? 'credit' : 'debt';
            $translationKey = $status === 'overdue'
                ? "notifications.reminders.credits_debts.{$baseKey}.overdue"
                : "notifications.reminders.credits_debts.{$baseKey}.due";

            $title = __($translationKey.'.title');
            $body = __($translationKey.'.body', [
                'description' => $item->description,
                'reference' => $reference,
                'date' => $this->formatDate($dueDate, $locale),
                'remaining' => $remaining,
                'total' => $total,
            ]);

            if ($reference === null && $status !== 'overdue' && $baseKey === 'credit') {
                $body = __('notifications.reminders.credits_debts.credit.due.body_without_reference', [
                    'description' => $item->description,
                    'date' => $this->formatDate($dueDate, $locale),
                    'remaining' => $remaining,
                    'total' => $total,
                ]);
            }

            return $this->dispatcher->dispatch(
                $item->user,
                $item,
                'credits_debts_due_reminders',
                'reminders.credits_debts_due',
                'credits_debts_due_reminder_database',
                'credit_debt_due_reminder',
                $reminderType,
                $dueDate,
                $title,
                $body,
                $targetUrl,
                $this->severityForStatus($status),
                [
                    'kind' => 'credit_debt_due_reminder',
                    'item_uuid' => $item->uuid,
                    'item_type' => $item->type->value,
                    'status' => $status,
                    'due_date' => $dueDate->toDateString(),
                    'remaining_amount' => $item->remainingAmount(),
                    'currency_code' => $item->currency_code,
                    'target_url' => $targetUrl,
                ],
            );
        } finally {
            App::setLocale($previousLocale);
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
