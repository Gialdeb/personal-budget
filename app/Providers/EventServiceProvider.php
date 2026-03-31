<?php

namespace App\Providers;

use App\Listeners\BroadcastUserNotificationInboxUpdate;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(NotificationSent::class, BroadcastUserNotificationInboxUpdate::class);
    }
}
