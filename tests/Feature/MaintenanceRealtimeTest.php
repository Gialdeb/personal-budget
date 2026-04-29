<?php

use App\Events\AppMaintenanceStateUpdated;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\MaintenanceModeDisabled;
use Illuminate\Foundation\Events\MaintenanceModeEnabled;
use Illuminate\Support\Facades\Event;

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

test('laravel maintenance events broadcast active and inactive states immediately', function (): void {
    Event::fake([AppMaintenanceStateUpdated::class]);

    Event::dispatch(new MaintenanceModeEnabled);
    Event::assertDispatched(
        AppMaintenanceStateUpdated::class,
        fn (AppMaintenanceStateUpdated $event): bool => $event->active === true,
    );

    Event::dispatch(new MaintenanceModeDisabled);
    Event::assertDispatched(
        AppMaintenanceStateUpdated::class,
        fn (AppMaintenanceStateUpdated $event): bool => $event->active === false,
    );
});

test('maintenance status endpoint exposes the backend source of truth', function (): void {
    $this->getJson(route('maintenance.status'))
        ->assertSuccessful()
        ->assertJson([
            'active' => false,
            'status' => 'inactive',
        ])
        ->assertJsonStructure([
            'active',
            'status',
            'checked_at',
        ]);
});
