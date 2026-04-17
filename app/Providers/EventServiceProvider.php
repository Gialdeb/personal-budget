<?php

namespace App\Providers;

use App\Events\AppMaintenanceStateUpdated;
use App\Listeners\BroadcastUserNotificationInboxUpdate;
use Illuminate\Foundation\Events\MaintenanceModeDisabled;
use Illuminate\Foundation\Events\MaintenanceModeEnabled;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(NotificationSent::class, BroadcastUserNotificationInboxUpdate::class);
        Event::listen(MaintenanceModeEnabled::class, function (): void {
            event(new AppMaintenanceStateUpdated(true));
        });
        Event::listen(MaintenanceModeDisabled::class, function (): void {
            event(new AppMaintenanceStateUpdated(false));
        });
    }
}
