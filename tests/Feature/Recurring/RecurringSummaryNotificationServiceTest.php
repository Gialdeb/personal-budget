<?php

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Jobs\DeliverOutboundMessageJob;
use App\Models\Category;
use App\Models\NotificationTopic;
use App\Models\OutboundMessage;
use App\Models\RecurringEntry;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Recurring\RecurringSummaryNotificationService;
use Carbon\CarbonImmutable;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('features.imports.enabled', false);

    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

it('sends the weekly recurring summary only to users who enabled the preference and avoids duplicate sends', function () {
    Queue::fake();

    $optedInUser = User::factory()->create([
        'locale' => 'it',
        'email' => 'weekly@example.com',
    ]);
    $optedOutUser = User::factory()->create([
        'locale' => 'it',
        'email' => 'no-weekly@example.com',
    ]);

    $topic = NotificationTopic::query()->where('key', 'recurring_weekly_due_summary')->firstOrFail();

    UserNotificationPreference::query()->create([
        'user_id' => $optedInUser->id,
        'notification_topic_id' => $topic->id,
        'email_enabled' => true,
        'in_app_enabled' => false,
        'sms_enabled' => false,
    ]);

    seedRecurringSummaryEntry($optedInUser, [
        'title' => 'Affitto casa',
        'expected_amount' => '750.00',
        'start_date' => '2026-04-13',
        'next_occurrence_date' => '2026-04-13',
        'recurrence_type' => 'weekly',
        'recurrence_rule' => ['weekdays' => ['mon']],
    ]);

    seedRecurringSummaryEntry($optedOutUser, [
        'title' => 'Quota palestra',
        'expected_amount' => '35.00',
        'start_date' => '2026-04-13',
        'next_occurrence_date' => '2026-04-13',
        'recurrence_type' => 'weekly',
        'recurrence_rule' => ['weekdays' => ['mon']],
    ]);

    $service = app(RecurringSummaryNotificationService::class);

    $firstRun = $service->sendWeeklySummary(CarbonImmutable::parse('2026-04-13'));
    $secondRun = $service->sendWeeklySummary(CarbonImmutable::parse('2026-04-13'));

    expect($firstRun['processed_count'])->toBe(1)
        ->and($firstRun['success_count'])->toBe(1)
        ->and($firstRun['warning_count'])->toBe(0)
        ->and($firstRun['delivered_count'])->toBe(1)
        ->and($secondRun['warning_count'])->toBe(1)
        ->and(OutboundMessage::query()->count())->toBe(1);

    $message = OutboundMessage::query()->firstOrFail();

    expect($message->subject_resolved)->toBe('Scadenze ricorrenti della settimana')
        ->and($message->body_resolved)->toContain('Riepilogo ricorrenze')
        ->and($message->body_resolved)->toContain('Affitto casa')
        ->and($message->body_resolved)->toContain('EUR 750.00')
        ->and(data_get($message->payload_snapshot, 'summary_key'))->toBe('weekly')
        ->and(data_get($message->payload_snapshot, 'window_start'))->toBe('2026-04-13')
        ->and(data_get($message->payload_snapshot, 'window_end'))->toBe('2026-04-19');

    Queue::assertPushed(DeliverOutboundMessageJob::class, 1);
});

it('sends the weekly recurring summary by email and in app using the user locale', function (string $locale, string $expectedSubject, string $expectedBody) {
    Queue::fake();

    $user = User::factory()->create([
        'locale' => $locale,
        'email' => "weekly-{$locale}@example.com",
    ]);

    $topic = NotificationTopic::query()->where('key', 'recurring_weekly_due_summary')->firstOrFail();

    UserNotificationPreference::query()->create([
        'user_id' => $user->id,
        'notification_topic_id' => $topic->id,
        'email_enabled' => true,
        'in_app_enabled' => true,
        'sms_enabled' => false,
    ]);

    seedRecurringSummaryEntry($user, [
        'title' => $locale === 'it' ? 'Affitto casa' : 'Rent',
        'expected_amount' => '750.00',
        'start_date' => '2026-04-13',
        'next_occurrence_date' => '2026-04-13',
        'recurrence_type' => 'weekly',
        'recurrence_rule' => ['weekdays' => ['mon']],
    ]);

    $result = app(RecurringSummaryNotificationService::class)
        ->sendWeeklySummary(CarbonImmutable::parse('2026-04-13'));

    expect($result['processed_count'])->toBe(1)
        ->and($result['success_count'])->toBe(1)
        ->and($result['delivered_count'])->toBe(2)
        ->and(OutboundMessage::query()->count())->toBe(2);

    $messages = OutboundMessage::query()
        ->orderBy('channel')
        ->get()
        ->keyBy(fn (OutboundMessage $message): string => $message->channel->value);

    expect($messages->keys()->all())->toBe(['database', 'mail'])
        ->and($messages['mail']->subject_resolved)->toBe($expectedSubject)
        ->and($messages['mail']->body_resolved)->toContain($expectedBody)
        ->and($messages['database']->title_resolved)->toBe($expectedSubject)
        ->and($messages['database']->body_resolved)->toContain($expectedBody)
        ->and(data_get($messages['database']->payload_snapshot, 'summary_key'))->toBe('weekly')
        ->and(data_get($messages['database']->payload_snapshot, 'window_start'))->toBe('2026-04-13')
        ->and(data_get($messages['database']->payload_snapshot, 'window_end'))->toBe('2026-04-19');

    Queue::assertPushed(DeliverOutboundMessageJob::class, 2);
})->with([
    'italian' => ['it', 'Scadenze ricorrenti della settimana', 'Riepilogo ricorrenze'],
    'english' => ['en', 'Recurring items due this week', 'Recurring summary'],
]);

it('sends the monthly recurring summary in english for the start-of-month window', function () {
    Queue::fake();

    $user = User::factory()->create([
        'locale' => 'en',
        'email' => 'monthly@example.com',
    ]);

    $topic = NotificationTopic::query()->where('key', 'recurring_monthly_due_summary')->firstOrFail();

    UserNotificationPreference::query()->create([
        'user_id' => $user->id,
        'notification_topic_id' => $topic->id,
        'email_enabled' => true,
        'in_app_enabled' => false,
        'sms_enabled' => false,
    ]);

    seedRecurringSummaryEntry($user, [
        'title' => 'Insurance',
        'expected_amount' => '129.50',
        'start_date' => '2026-05-02',
        'next_occurrence_date' => '2026-05-02',
        'recurrence_type' => 'monthly',
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 2],
    ]);

    $result = app(RecurringSummaryNotificationService::class)
        ->sendMonthlySummary(CarbonImmutable::parse('2026-05-01'));

    expect($result['processed_count'])->toBe(1)
        ->and($result['success_count'])->toBe(1)
        ->and(OutboundMessage::query()->count())->toBe(1);

    $message = OutboundMessage::query()->firstOrFail();

    expect($message->subject_resolved)->toBe('Recurring items due at the start of the month')
        ->and($message->body_resolved)->toContain('Recurring summary')
        ->and($message->body_resolved)->toContain('Insurance')
        ->and($message->body_resolved)->toContain('EUR 129.50')
        ->and(data_get($message->payload_snapshot, 'summary_key'))->toBe('monthly')
        ->and(data_get($message->payload_snapshot, 'window_start'))->toBe('2026-05-01')
        ->and(data_get($message->payload_snapshot, 'window_end'))->toBe('2026-05-10');
});

it('registers the recurring summary jobs in the scheduler', function () {
    $descriptions = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->description)
        ->filter();

    expect($descriptions->contains('recurring-weekly-summary'))->toBeTrue()
        ->and($descriptions->contains('recurring-monthly-summary'))->toBeTrue();
});

function seedRecurringSummaryEntry(User $user, array $attributes = []): RecurringEntry
{
    $account = createTestAccount($user);
    $category = Category::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'name' => 'Recurring category',
        'slug' => 'recurring-category-'.$user->id,
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => false,
    ]);

    return RecurringEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'title' => 'Recurring summary item',
        'direction' => 'expense',
        'expected_amount' => '19.90',
        'currency' => 'EUR',
        'entry_type' => 'recurring',
        'status' => 'active',
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 1],
        'start_date' => '2026-04-01',
        'next_occurrence_date' => '2026-04-01',
        'end_mode' => 'never',
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
        ...$attributes,
    ]);
}
