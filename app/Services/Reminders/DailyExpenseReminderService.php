<?php

namespace App\Services\Reminders;

use App\Models\ReminderDelivery;
use App\Models\User;
use App\Services\Push\DeviceTokenService;
use App\Services\Push\PushNotificationService;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Throwable;

class DailyExpenseReminderService
{
    public function __construct(
        protected DeviceTokenService $deviceTokenService,
        protected PushNotificationService $pushNotificationService,
    ) {}

    public function run(): int
    {
        if (! config('features.push_notifications.enabled')) {
            return 0;
        }

        $sent = 0;
        $now = CarbonImmutable::now('UTC');
        $timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC);

        foreach (User::query()->with('settings')->whereHas('deviceTokens', fn ($query) => $query->active()->forPlatform('web'))->lazyById(200) as $user) {
            $timezone = data_get($user->settings?->settings, 'timezone');

            if (! $user->pushNotificationsEnabled() || ! $user->dailyExpenseReminderEnabled() || ! in_array($timezone, $timezones, true)) {
                continue;
            }

            $localNow = $now->setTimezone($timezone);

            if ($localNow->format('H:i') !== '21:00' || $this->deviceTokenService->activeBroadcastTokensForUser($user)->isEmpty()) {
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

                if ($summary['sent_count'] > 0) {
                    $delivery->forceFill(['pushed_at' => $now])->save();
                    $sent++;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $sent;
    }
}
