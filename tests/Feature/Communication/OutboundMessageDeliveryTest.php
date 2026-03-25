<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\OutboundMessageStatusEnum;
use App\Jobs\DeliverOutboundMessageJob;
use App\Models\CommunicationCategory;
use App\Models\CommunicationTemplate;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Notifications\DeliveredOutboundDatabaseNotification;
use App\Notifications\DeliveredOutboundMailNotification;
use App\Services\Communication\CommunicationDispatchService;
use App\Services\Communication\OutboundMessageDeliveryService;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

it('queues a delivery job when a queued outbound message is created through dispatch', function () {
    Queue::fake();

    $user = User::factory()->create([
        'name' => 'Giuseppe',
        'surname' => 'De Blasio',
        'email' => 'giuseppe@example.com',
    ]);

    app(CommunicationDispatchService::class)->dispatchForUserCategory(
        'user.welcome_after_verification',
        $user,
        $user,
    );

    Queue::assertPushed(DeliverOutboundMessageJob::class);
});

it('delivers a mail outbound message and marks it as sent', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'giuseppe@example.com',
    ]);

    $category = CommunicationCategory::query()->where('key', 'user.welcome_after_verification')->firstOrFail();
    $template = CommunicationTemplate::query()->where('key', 'welcome_after_verification_mail')->firstOrFail();

    $message = OutboundMessage::query()->create([
        'communication_category_id' => $category->id,
        'communication_template_id' => $template->id,
        'channel' => CommunicationChannelEnum::MAIL,
        'status' => OutboundMessageStatusEnum::QUEUED,
        'recipient_type' => $user->getMorphClass(),
        'recipient_id' => $user->id,
        'context_type' => $user->getMorphClass(),
        'context_id' => $user->id,
        'subject_resolved' => 'Welcome',
        'title_resolved' => 'Welcome Giuseppe',
        'body_resolved' => 'Your account is active.',
        'cta_label_resolved' => 'Open dashboard',
        'cta_url_resolved' => '/dashboard',
        'payload_snapshot' => ['test' => true],
    ]);

    $delivered = app(OutboundMessageDeliveryService::class)->deliver($message);

    Notification::assertSentTo($user, DeliveredOutboundMailNotification::class);

    expect($delivered->status)->toBe(OutboundMessageStatusEnum::SENT)
        ->and($delivered->sent_at)->not->toBeNull();
});

it('delivers a database outbound message and marks it as sent', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'giuseppe@example.com',
    ]);

    $category = CommunicationCategory::query()->where('key', 'imports.completed')->firstOrFail();
    $template = CommunicationTemplate::query()->where('key', 'import_completed_mail')->firstOrFail();

    $message = OutboundMessage::query()->create([
        'communication_category_id' => $category->id,
        'communication_template_id' => $template->id,
        'channel' => CommunicationChannelEnum::DATABASE,
        'status' => OutboundMessageStatusEnum::QUEUED,
        'recipient_type' => $user->getMorphClass(),
        'recipient_id' => $user->id,
        'context_type' => $user->getMorphClass(),
        'context_id' => $user->id,
        'subject_resolved' => null,
        'title_resolved' => 'Import completed',
        'body_resolved' => 'Your import has completed successfully.',
        'cta_label_resolved' => 'Open import',
        'cta_url_resolved' => '/imports/123',
        'payload_snapshot' => ['test' => true],
    ]);

    $delivered = app(OutboundMessageDeliveryService::class)->deliver($message);

    Notification::assertSentTo($user, DeliveredOutboundDatabaseNotification::class);

    expect($delivered->status)->toBe(OutboundMessageStatusEnum::SENT)
        ->and($delivered->sent_at)->not->toBeNull();
});

it('marks the outbound message as failed when delivery throws', function () {
    $user = User::factory()->create();

    $category = CommunicationCategory::query()->where('key', 'user.welcome_after_verification')->firstOrFail();
    $template = CommunicationTemplate::query()->where('key', 'welcome_after_verification_mail')->firstOrFail();

    $message = OutboundMessage::query()->create([
        'communication_category_id' => $category->id,
        'communication_template_id' => $template->id,
        'channel' => CommunicationChannelEnum::SMS,
        'status' => OutboundMessageStatusEnum::QUEUED,
        'recipient_type' => $user->getMorphClass(),
        'recipient_id' => $user->id,
        'context_type' => $user->getMorphClass(),
        'context_id' => $user->id,
        'subject_resolved' => null,
        'title_resolved' => null,
        'body_resolved' => 'Test',
        'cta_label_resolved' => null,
        'cta_url_resolved' => null,
        'payload_snapshot' => ['test' => true],
    ]);

    try {
        app(OutboundMessageDeliveryService::class)->deliver($message);
    } catch (Throwable) {
    }

    $message->refresh();

    expect($message->status)->toBe(OutboundMessageStatusEnum::FAILED)
        ->and($message->failed_at)->not->toBeNull()
        ->and($message->error_message)->not->toBeNull();
});
