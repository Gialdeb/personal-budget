<?php

namespace App\Listeners;

use App\Events\UserNotificationInboxUpdated;
use App\Http\Resources\NotificationInboxItemResource;
use App\Models\User;
use App\Services\Communication\UserNotificationInboxService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

class BroadcastUserNotificationInboxUpdate
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected UserNotificationInboxService $inboxService,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        if (! $event->notifiable instanceof User) {
            return;
        }

        if (! $event->response instanceof DatabaseNotification) {
            return;
        }

        event(new UserNotificationInboxUpdated(
            userUuid: $event->notifiable->uuid,
            unreadCount: $this->inboxService->unreadCount($event->notifiable),
            notification: NotificationInboxItemResource::make($event->response)->resolve(),
        ));
    }
}
