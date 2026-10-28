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
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;

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

it('uses each user local time including DST', function (string $timezone, string $utc, string $localDate) {
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
    'Rome incident date' => ['Europe/Rome', '2026-10-27 20:00:00', '2026-10-27'],
    'Rome before autumn change' => ['Europe/Rome', '2026-10-24 19:00:00', '2026-10-24'],
    'Rome before spring change' => ['Europe/Rome', '2026-03-28 20:00:00', '2026-03-28'],
    'New York after autumn change' => ['America/New_York', '2026-11-02 02:00:00', '2026-11-01'],
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

it('checks every minute for local 21 time with overlap protection', function () {
    $event = collect(app(Schedule::class)->events())->firstWhere('description', 'daily-expense-reminders');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->timezone)->toBe('Europe/Rome')
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

it('does not require a stored timezone or an open PWA', function (array $settings) {
    $user = dailyExpenseReminderUser($settings);
    DeviceToken::factory()->for($user)->create(['last_seen_at' => now()->subMonths(2)]);
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')->once()->andReturn(['sent_count' => 1]);
    $this->instance(PushNotificationService::class, $push);

    expect(app(DailyExpenseReminderService::class)->run())->toBe(1);
})->with([[[]], [['timezone' => 'invalid']]]);

it('never sends outside the user local 21 time', function (string $utc) {
    $user = dailyExpenseReminderUser(['timezone' => 'Europe/Rome']);
    DeviceToken::factory()->for($user)->create();
    $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldNotReceive('sendToUser');
    $this->instance(PushNotificationService::class, $push);

    expect(app(DailyExpenseReminderService::class)->run())->toBe(0)
        ->and(ReminderDelivery::query()->count())->toBe(0);
})->with(['2026-10-17 01:00:00', '2026-10-16 18:59:00', '2026-10-25 19:00:00', '2026-10-27 19:00:00', '2026-10-27 20:01:00']);

it('logs failed Firebase results and reserves the day against duplicates', function () {
    Log::spy();
    $user = dailyExpenseReminderUser([]);
    DeviceToken::factory()->for($user)->create();
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')->once()->andReturn(['sent_count' => 0, 'failed_count' => 1, 'invalidated_count' => 1]);
    $this->instance(PushNotificationService::class, $push);

    expect(app(DailyExpenseReminderService::class)->run())->toBe(0)
        ->and(app(DailyExpenseReminderService::class)->run())->toBe(0);
    Log::shouldHaveReceived('warning')->once()->with('Daily expense reminder delivery failed or incomplete.', Mockery::on(
        fn (array $context): bool => $context['user_id'] === $user->getKey() && $context['failed_count'] === 1,
    ));
});

it('reaches Firebase with each eligible device once and logs invalid tokens without exposing them', function () {
    Log::spy();
    $user = dailyExpenseReminderUser([]);
    DeviceToken::factory()->for($user)->create(['token' => 'valid-reminder-token', 'device_identifier' => 'phone']);
    $invalid = DeviceToken::factory()->for($user)->create(['token' => 'invalid-reminder-token', 'device_identifier' => 'tablet']);
    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')->once()->withArgs(function ($message, array $tokens): bool {
        sort($tokens);

        return $tokens === ['invalid-reminder-token', 'valid-reminder-token']
            && $message->jsonSerialize()['data']['notification_type'] === 'daily_expense_reminder';
    })->andReturn(MulticastSendReport::withItems([
        SendReport::success(
            MessageTarget::with('token', 'valid-reminder-token'), ['name' => 'mock-message'],
        ),
        SendReport::failure(
            MessageTarget::with('token', 'invalid-reminder-token'),
            NotFound::becauseTokenNotFound('invalid-reminder-token'),
        ),
    ]));
    $this->instance(Messaging::class, $messaging);

    expect(app(DailyExpenseReminderService::class)->run())->toBe(1)
        ->and(app(DailyExpenseReminderService::class)->run())->toBe(0)
        ->and($invalid->fresh()->is_active)->toBeFalse();
    Log::shouldHaveReceived('warning')->with('Direct push rejected by Firebase.', Mockery::on(
        fn (array $context): bool => $context['user_id'] === $user->getKey()
            && $context['error_type'] === NotFound::class
            && ! str_contains(json_encode($context), 'invalid-reminder-token'),
    ))->once();
});

it('reserves each user before dispatch even when another run starts during delivery', function () {
    $first = dailyExpenseReminderUser([]);
    $second = dailyExpenseReminderUser(['timezone' => 'Europe/Rome']);
    DeviceToken::factory()->for($first)->create();
    DeviceToken::factory()->for($second)->create();
    $push = Mockery::mock(PushNotificationService::class);
    $entered = false;
    $recipients = [];
    $push->shouldReceive('sendToUser')->twice()->andReturnUsing(function (User $user) use (&$entered, &$recipients): array {
        $recipients[] = $user->getKey();
        if (! $entered) {
            $entered = true;
            expect(app(DailyExpenseReminderService::class)->run())->toBe(1);
        }

        return ['sent_count' => 1];
    });
    $this->instance(PushNotificationService::class, $push);

    expect(app(DailyExpenseReminderService::class)->run())->toBe(1)
        ->and(app(DailyExpenseReminderService::class)->run())->toBe(0)
        ->and($recipients)->toBe([$first->getKey(), $second->getKey()])
        ->and(ReminderDelivery::query()->count())->toBe(2);
});

it('selects only users whose local clock is 21 and reserves their own local date', function () {
    $rome = dailyExpenseReminderUser(['timezone' => 'Europe/Rome']);
    $newYork = dailyExpenseReminderUser(['timezone' => 'America/New_York']);
    DeviceToken::factory()->for($rome)->create();
    DeviceToken::factory()->for($newYork)->create();
    $push = Mockery::mock(PushNotificationService::class);
    $push->shouldReceive('sendToUser')->once()->withArgs(fn (User $user): bool => $user->is($rome))->andReturn(['sent_count' => 1]);
    $push->shouldReceive('sendToUser')->once()->withArgs(fn (User $user): bool => $user->is($newYork))->andReturn(['sent_count' => 1]);
    $this->instance(PushNotificationService::class, $push);
    $service = app(DailyExpenseReminderService::class);

    $this->travelTo(CarbonImmutable::parse('2026-10-27 20:00:00', 'UTC'));
    expect($service->run())->toBe(1)->and($service->run())->toBe(0);
    $this->travelTo(CarbonImmutable::parse('2026-10-28 01:00:00', 'UTC'));
    expect($service->run())->toBe(1)->and($service->run())->toBe(0)
        ->and(ReminderDelivery::query()->count())->toBe(2)
        ->and(ReminderDelivery::query()->get()->every(fn (ReminderDelivery $delivery): bool => $delivery->delivery_date->toDateString() === '2026-10-27'))->toBeTrue();
});
