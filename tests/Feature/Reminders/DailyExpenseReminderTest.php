<?php

use App\Models\DeviceToken;
use App\Models\OutboundMessage;
use App\Models\ReminderDelivery;
use App\Models\User;
use App\Services\Push\PushNotificationService;
use App\Services\Reminders\DailyExpenseReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('features.push_notifications.enabled', true);
    $this->travelTo(CarbonImmutable::parse('2026-10-16 19:00:00', 'UTC'));
});

it('sends one localized push per local day without any internal notification', function (string $locale, string $body) {
    $user = dailyExpenseReminderUser(['timezone' => 'Europe/Rome'], $locale);
    DeviceToken::factory()->for($user)->create();

    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')->twice()->withArgs(function (User $recipient, string $title, string $message, string $url, array $data) use ($user, $body): bool {
        return $recipient->is($user) && $title === 'Soamco' && $message === $body
            && $url === '/transactions' && $data['notification_type'] === 'daily_expense_reminder';
    })->andReturn(['sent_count' => 1]);
    $this->instance(PushNotificationService::class, $push);

    $service = app(DailyExpenseReminderService::class);
    expect($service->run())->toBe(1)
        ->and($service->run())->toBe(0);

    $this->travelTo(CarbonImmutable::parse('2026-10-17 19:00:00', 'UTC'));
    expect($service->run())->toBe(1)
        ->and(ReminderDelivery::query()->count())->toBe(2)
        ->and(ReminderDelivery::query()->whereNull('pushed_at')->count())->toBe(0)
        ->and(OutboundMessage::query()->count())->toBe(0)
        ->and(DB::table('notifications')->count())->toBe(0);
})->with([
    ['it', 'Un piccolo promemoria 💜 Ricordati di registrare le spese di oggi!'],
    ['en', "A little reminder 💜 Don't forget to log today's expenses!"],
]);

it('honors local timezone including DST and fractional offsets', function (string $timezone, string $utc, string $localDate) {
    $user = dailyExpenseReminderUser(['timezone' => $timezone]);
    DeviceToken::factory()->for($user)->create();
    $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));

    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')->once()->andReturn(['sent_count' => 1]);
    $this->instance(PushNotificationService::class, $push);

    expect(app(DailyExpenseReminderService::class)->run())->toBe(1)
        ->and(app(DailyExpenseReminderService::class)->run())->toBe(0)
        ->and(ReminderDelivery::query()->sole()->delivery_date->toDateString())->toBe($localDate);
})->with([
    'Rome spring DST' => ['Europe/Rome', '2026-03-29 19:00:00', '2026-03-29'],
    'Rome autumn DST' => ['Europe/Rome', '2026-10-25 20:00:00', '2026-10-25'],
    'New York previous UTC day' => ['America/New_York', '2026-10-17 01:00:00', '2026-10-16'],
    'Kathmandu fractional offset' => ['Asia/Kathmandu', '2026-10-16 15:15:00', '2026-10-16'],
]);

it('skips users without consent preference timezone or the correct local time', function (array $settings, ?bool $activeToken, bool $featureEnabled) {
    config()->set('features.push_notifications.enabled', $featureEnabled);
    $user = dailyExpenseReminderUser($settings);
    if ($activeToken !== null) {
        DeviceToken::factory()->for($user)->create(['is_active' => $activeToken]);
    }
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldNotReceive('sendToUser');
    $this->instance(PushNotificationService::class, $push);

    expect(app(DailyExpenseReminderService::class)->run())->toBe(0)
        ->and(ReminderDelivery::query()->count())->toBe(0);
})->with([
    'push disabled' => [['timezone' => 'Europe/Rome', 'notifications' => ['push' => ['enabled' => false]]], true, true],
    'reminder disabled' => [['timezone' => 'Europe/Rome', 'notifications' => ['push' => ['daily_expense_reminder_enabled' => false]]], true, true],
    'inactive token' => [['timezone' => 'Europe/Rome'], false, true],
    'missing token' => [['timezone' => 'Europe/Rome'], null, true],
    'unknown timezone' => [[], true, true],
    'invalid timezone' => [['timezone' => 'invalid'], true, true],
    'not yet 21 local' => [['timezone' => 'America/New_York'], true, true],
    'feature disabled' => [['timezone' => 'Europe/Rome'], true, false],
]);

it('does not retry a delivery whose Firebase result is uncertain', function () {
    $user = dailyExpenseReminderUser(['timezone' => 'Europe/Rome']);
    DeviceToken::factory()->for($user)->create();
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')->once()->andThrow(new RuntimeException('Firebase timeout'));
    $this->instance(PushNotificationService::class, $push);

    expect(app(DailyExpenseReminderService::class)->run())->toBe(0)
        ->and(app(DailyExpenseReminderService::class)->run())->toBe(0)
        ->and(ReminderDelivery::query()->count())->toBe(1)
        ->and(ReminderDelivery::query()->sole()->pushed_at)->toBeNull();
});

it('registers the reminder on the existing scheduler every minute with overlap protection', function () {
    $event = collect(app(Schedule::class)->events())->firstWhere('description', 'daily-expense-reminders');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();

    $this->artisan('reminders:daily-expenses')->expectsOutput('Daily expense reminders sent: 0')->assertSuccessful();
});

function dailyExpenseReminderUser(array $settings, string $locale = 'it'): User
{
    $user = User::factory()->create(['locale' => $locale]);
    $user->settings()->create(['settings' => $settings]);

    return $user;
}
