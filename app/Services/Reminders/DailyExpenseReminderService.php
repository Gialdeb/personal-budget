<?php

namespace App\Services\Reminders;

use App\Models\ReminderDelivery;
use App\Models\User;
use App\Services\Push\DeviceTokenService;
use App\Services\Push\PushNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DailyExpenseReminderService
{
    public function __construct(
        protected DeviceTokenService $deviceTokenService,
        protected PushNotificationService $pushNotificationService,
    ) {}

    public function run(): int
    {
        $sent = 0;
        $now = CarbonImmutable::now('UTC');
        $localNow = $now->setTimezone('Europe/Rome');

        if ($localNow->format('H:i') !== '21:00') {
            return 0;
        }

        if (! config('features.push_notifications.enabled')) {
            Log::warning('Daily expense reminder skipped: push notifications feature disabled.');

            return 0;
        }

        Log::info('Daily expense reminder run started.', ['date' => $localNow->toDateString(), 'timezone' => 'Europe/Rome']);

        foreach (User::query()->with('settings')->whereHas('deviceTokens', fn ($query) => $query->active()->forPlatform('web'))->lazyById(200) as $user) {
            if (! $user->pushNotificationsEnabled() || ! $user->dailyExpenseReminderEnabled()) {
                continue;
            }

            if ($this->deviceTokenService->activeBroadcastTokensForUser($user)->isEmpty()) {
                continue;
            }

            $localDate = $localNow->toDateString();
            $delivery = ReminderDelivery::query()->firstOrCreate([
                'user_id' => $user->getKey(),
                'remindable_type' => $user->getMorphClass(),
                'remindable_id' => $user->getKey(),
                'reminder_type' => 'daily_expense',
                'due_date' => $localNow->startOfDay(),
                'delivery_date' => $localNow->startOfDay(),
            ], ['notification_kind' => 'daily_expense_reminder']);

            if (! $delivery->wasRecentlyCreated) {
                continue;
            }

            /**
             * Reserve before contacting Firebase: an uncertain delivery must never
             * be retried, since it may already have reached the user's devices.
             */
            try {
                $summary = $this->pushNotificationService->sendToUser(
                    $user,
                    'Soamco',
                    __('notifications.reminders.daily_expense.body', [], $user->preferredLocale()),
                    route('transactions.index', absolute: false),
                    ['notification_type' => 'daily_expense_reminder', 'tag' => 'daily-expense:'.$user->getKey().':'.$localDate],
                );

                $context = ['user_id' => $user->getKey(), 'delivery_id' => $delivery->getKey(), 'date' => $localDate, ...$summary];
                if (($summary['failed_count'] ?? 0) > 0 || $summary['sent_count'] === 0) {
                    Log::warning('Daily expense reminder delivery failed or incomplete.', $context);
                } else {
                    Log::info('Daily expense reminder delivery completed.', $context);
                }

                if ($summary['sent_count'] > 0) {
                    $delivery->forceFill(['pushed_at' => $now])->save();
                    $sent++;
                }
            } catch (Throwable $exception) {
                Log::error('Daily expense reminder delivery uncertain; automatic retry suppressed.', [
                    'user_id' => $user->getKey(),
                    'delivery_id' => $delivery->getKey(),
                    'date' => $localDate,
                ]);
                report($exception);
            }
        }

        Log::info('Daily expense reminder run completed.', ['date' => $localNow->toDateString(), 'sent_users' => $sent]);

        return $sent;
    }
}
