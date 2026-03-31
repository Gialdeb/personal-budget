<?php

use App\Events\UserNotificationInboxUpdated;
use App\Http\Resources\NotificationInboxItemResource;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('broadcasting.default', 'reverb');
});

function sendRealtimeDatabaseNotification(User $user, string $title = 'Realtime inbox'): void
{
    $user->notify(new class($title) extends Notification
    {
        public function __construct(
            protected string $title,
        ) {}

        public function via(object $notifiable): array
        {
            return ['database'];
        }

        public function toDatabase(object $notifiable): array
        {
            return [
                'category' => [
                    'key' => 'imports.completed',
                    'name' => 'Import completato',
                ],
                'presentation' => [
                    'layout' => 'standard_card',
                    'icon' => 'import',
                    'image_url' => null,
                ],
                'content' => [
                    'title' => $this->title,
                    'message' => 'Contenuto realtime',
                    'cta_label' => 'Apri',
                    'cta_url' => '/imports',
                ],
            ];
        }
    });
}

test('notification realtime channel authorizes only the matching user uuid', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    require base_path('routes/channels.php');

    /** @var PusherBroadcaster $broadcaster */
    $broadcaster = app(BroadcastingFactory::class)->connection('reverb');
    $reflection = new ReflectionProperty($broadcaster, 'channels');
    $channels = $reflection->getValue($broadcaster);
    $authorizationCallback = $channels['users.{uuid}.notifications'];

    expect($authorizationCallback($user, $user->uuid))->toBeTrue()
        ->and($authorizationCallback($user, $otherUser->uuid))->toBeFalse();
});

test('database notifications dispatch the realtime inbox update event', function () {
    $user = User::factory()->create();

    Event::fake([UserNotificationInboxUpdated::class]);

    sendRealtimeDatabaseNotification($user, 'Realtime notification');

    Event::assertDispatched(UserNotificationInboxUpdated::class, function (UserNotificationInboxUpdated $event) use ($user): bool {
        return $event->userUuid === $user->uuid
            && $event->unreadCount === 1
            && $event->notification['content']['title'] === 'Realtime notification'
            && $event->notification['is_unread'] === true;
    });
});

test('user notification inbox update event broadcasts the expected payload', function () {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) str()->uuid(),
        'type' => 'manual-test',
        'data' => [
            'category' => [
                'key' => 'imports.completed',
                'name' => 'Import completato',
            ],
            'presentation' => [
                'layout' => 'standard_card',
                'icon' => 'import',
                'image_url' => null,
            ],
            'content' => [
                'title' => 'Inbox realtime payload',
                'message' => 'Contenuto realtime',
                'cta_label' => 'Apri',
                'cta_url' => '/imports',
            ],
        ],
        'read_at' => null,
    ]);

    $event = new UserNotificationInboxUpdated(
        $user->uuid,
        1,
        NotificationInboxItemResource::make($notification)->resolve(),
    );

    $channels = $event->broadcastOn();
    $payload = $event->broadcastWith();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-users.{$user->uuid}.notifications")
        ->and($event->broadcastAs())->toBe('notification.inbox.updated')
        ->and($payload['unread_count'])->toBe(1)
        ->and($payload['notification']['uuid'])->toBe($notification->id)
        ->and($payload['notification']['content']['title'])->toBe('Inbox realtime payload');
});
