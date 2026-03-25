<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\ImportSourceTypeEnum;
use App\Enums\ImportStatusEnum;
use App\Jobs\DeliverOutboundMessageJob;
use App\Models\Import;
use App\Models\NotificationTopic;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Services\Communication\CommunicationDispatchService;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

it('creates a queued outbound message for a mandatory category', function () {
    Queue::fake();

    $user = User::factory()->create([
        'name' => 'Giuseppe',
        'surname' => 'De Blasio',
        'email' => 'giuseppe@example.com',
    ]);

    $messages = app(CommunicationDispatchService::class)->dispatchForUserCategory(
        'user.welcome_after_verification',
        $user,
        $user,
    );

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toBeInstanceOf(OutboundMessage::class)
        ->and($messages[1])->toBeInstanceOf(OutboundMessage::class)
        ->and(collect($messages)->every(fn ($message) => $message->status->value === 'queued'))->toBeTrue()
        ->and(collect($messages)->pluck('channel')->map(fn ($channel) => $channel->value)->sort()->values()->all())
        ->toBe(collect([CommunicationChannelEnum::MAIL->value, CommunicationChannelEnum::DATABASE->value])->sort()->values()->all())
        ->and($messages[0]->body_resolved)->toContain('Giuseppe De Blasio')
        ->and($messages[1]->body_resolved)->toContain('Giuseppe De Blasio');

    Queue::assertPushed(DeliverOutboundMessageJob::class);
});

it('creates a queued outbound message for a configurable category when email is enabled', function () {
    Queue::fake();

    $user = User::factory()->create([
        'email' => 'owner@example.com',
    ]);

    $topic = NotificationTopic::query()->where('key', 'import_completed')->firstOrFail();

    $user->notificationPreferences()->create([
        'notification_topic_id' => $topic->id,
        'email_enabled' => true,
        'in_app_enabled' => false,
        'sms_enabled' => false,
    ]);

    $import = Import::query()->forceCreate([
        'user_id' => $user->id,
        'original_filename' => 'movements.csv',
        'source_type' => ImportSourceTypeEnum::CSV->value,
        'rows_count' => 20,
        'imported_rows_count' => 18,
        'review_rows_count' => 0,
        'invalid_rows_count' => 0,
        'duplicate_rows_count' => 0,
        'status' => ImportStatusEnum::COMPLETED,
    ]);

    $messages = app(CommunicationDispatchService::class)->dispatchForUserCategory(
        'imports.completed',
        $user,
        $import,
    );

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->status->value)->toBe('queued')
        ->and($messages[0]->body_resolved)->not->toBeEmpty();

    Queue::assertPushed(DeliverOutboundMessageJob::class);
});

it('creates a skipped outbound message when no configurable channel is enabled', function () {
    Queue::fake();

    $user = User::factory()->create([
        'email' => 'owner@example.com',
    ]);

    $topic = NotificationTopic::query()->where('key', 'import_completed')->firstOrFail();

    $user->notificationPreferences()->create([
        'notification_topic_id' => $topic->id,
        'email_enabled' => false,
        'in_app_enabled' => false,
        'sms_enabled' => false,
    ]);

    $import = Import::query()->forceCreate([
        'user_id' => $user->id,
        'original_filename' => 'movements.csv',
        'source_type' => ImportSourceTypeEnum::CSV->value,
        'rows_count' => 20,
        'imported_rows_count' => 18,
        'review_rows_count' => 0,
        'invalid_rows_count' => 0,
        'duplicate_rows_count' => 0,
        'status' => ImportStatusEnum::COMPLETED,
    ]);

    $messages = app(CommunicationDispatchService::class)->dispatchForUserCategory(
        'imports.completed',
        $user,
        $import,
    );

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->status->value)->toBe('skipped');

});

it('stores payload snapshot and polymorphic recipient/context references', function () {
    $user = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
    ]);

    $messages = app(CommunicationDispatchService::class)->dispatchForUserCategory(
        'user.welcome_after_verification',
        $user,
        $user,
    );

    $message = $messages[0];

    expect($message->payload_snapshot)->toBeArray()
        ->and($message->recipient_type)->toBe($user->getMorphClass())
        ->and((int) $message->recipient_id)->toBe($user->id)
        ->and($message->context_type)->toBe($user->getMorphClass())
        ->and((int) $message->context_id)->toBe($user->id);
});
