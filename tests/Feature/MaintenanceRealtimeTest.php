<?php

use App\Events\AppMaintenanceStateUpdated;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

test('maintenance state update broadcasts on the global public channel', function (): void {
    $event = new AppMaintenanceStateUpdated(true);

    $channels = $event->broadcastOn();
    $payload = $event->broadcastWith();

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class)
        ->and($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(Channel::class)
        ->and($channels[0]->name)->toBe('app.maintenance')
        ->and($event->broadcastAs())->toBe('maintenance.state.updated')
        ->and($payload['active'])->toBeTrue()
        ->and($payload['status'])->toBe('active')
        ->and($payload['checked_at'])->toBeString();
});

test('maintenance disabled update clears the global maintenance state', function (): void {
    $payload = (new AppMaintenanceStateUpdated(false))->broadcastWith();

    expect($payload['active'])->toBeFalse()
        ->and($payload['status'])->toBe('inactive')
        ->and($payload['checked_at'])->toBeString();
});
