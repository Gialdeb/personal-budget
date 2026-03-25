<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\OutboundMessageStatusEnum;
use App\Models\CommunicationCategory;
use App\Models\CommunicationTemplate;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Notifications\DeliveredOutboundDatabaseNotification;
use App\Services\Communication\OutboundMessageDeliveryService;
use App\Services\Communication\UserNotificationInboxService;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

it('stores a database notification with the structured inbox payload', function () {
    $user = User::factory()->create();

    $category = CommunicationCategory::query()->where('key', 'user.welcome_after_verification')->firstOrFail();
    $template = CommunicationTemplate::query()->where('key', 'welcome_after_verification_database')->firstOrFail();

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
        'title_resolved' => 'Benvenuto',
        'body_resolved' => 'Benvenuto Mario, il tuo account è ora attivo.',
        'cta_label_resolved' => 'Apri dashboard',
        'cta_url_resolved' => '/dashboard',
        'payload_snapshot' => ['test' => true],
    ]);

    app(OutboundMessageDeliveryService::class)->deliver($message);

    $notification = $user->notifications()->latest()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['presentation']['layout'])->toBe('standard_card')
        ->and($notification->data['content']['title'])->toBe('Benvenuto')
        ->and($notification->data['content']['cta_url'])->toBe('/dashboard');
});

it('returns unread count and marks notifications as read', function () {
    $user = User::factory()->create();

    $user->notify(new DeliveredOutboundDatabaseNotification(
        OutboundMessage::query()->create([
            'communication_category_id' => CommunicationCategory::query()->where('key', 'user.welcome_after_verification')->firstOrFail()->id,
            'communication_template_id' => CommunicationTemplate::query()->where('key', 'welcome_after_verification_database')->firstOrFail()->id,
            'channel' => CommunicationChannelEnum::DATABASE,
            'status' => OutboundMessageStatusEnum::SENT,
            'recipient_type' => $user->getMorphClass(),
            'recipient_id' => $user->id,
            'context_type' => $user->getMorphClass(),
            'context_id' => $user->id,
            'subject_resolved' => null,
            'title_resolved' => 'Benvenuto',
            'body_resolved' => 'Contenuto',
            'cta_label_resolved' => 'Apri',
            'cta_url_resolved' => '/dashboard',
            'payload_snapshot' => ['test' => true],
            'sent_at' => now(),
        ])
    ));

    $service = app(UserNotificationInboxService::class);

    expect($service->unreadCount($user))->toBe(1);

    $notification = $user->notifications()->latest()->firstOrFail();

    $marked = $service->markAsRead($user, $notification->id);

    expect($marked)->toBeTrue()
        ->and($service->unreadCount($user))->toBe(0);
});

it('can mark all notifications as read', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 2; $i++) {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'manual-test',
            'data' => ['content' => ['title' => 'Test']],
        ]);
    }

    $service = app(UserNotificationInboxService::class);

    expect($service->unreadCount($user))->toBe(2);

    $count = $service->markAllAsRead($user);

    expect($count)->toBe(2)
        ->and($service->unreadCount($user))->toBe(0);
});
