<?php

use App\Enums\AutomationRunStatusEnum;
use App\Enums\AutomationTriggerTypeEnum;
use App\Events\Admin\AutomationRunUpdated;
use App\Models\AutomationRun;
use App\Models\User;
use App\Services\Automation\AutomationRunRecorder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
    config()->set('broadcasting.default', 'reverb');
});

test('reverb foundation uses the expected broadcasting configuration', function () {
    expect(config('broadcasting.connections.reverb.driver'))->toBe('reverb')
        ->and(config('broadcasting.connections.reverb.options.host'))->toBe('reverb')
        ->and((string) config('broadcasting.connections.reverb.options.port'))->toBe('8080')
        ->and(config('broadcasting.connections.reverb.options.scheme'))->toBe('http')
        ->and(config('reverb.servers.reverb.host'))->toBe('0.0.0.0')
        ->and((string) config('reverb.servers.reverb.port'))->toBe('8080')
        ->and(config('reverb.servers.reverb.hostname'))->toBe('soamco.lo')
        ->and(config('reverb.apps.apps.0.options.host'))->toBe('soamco.lo')
        ->and((string) config('reverb.apps.apps.0.options.port'))->toBe('443')
        ->and(config('reverb.apps.apps.0.options.scheme'))->toBe('https')
        ->and(config('reverb.apps.apps.0.allowed_origins'))->toBe([
            'soamco.lo',
            'localhost',
            '127.0.0.1',
        ]);
});

test('admin automation realtime channel authorizes only admins', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['user', 'admin']);

    $user = User::factory()->create();
    $user->assignRole('user');

    require base_path('routes/channels.php');

    /** @var PusherBroadcaster $broadcaster */
    $broadcaster = app(BroadcastingFactory::class)->connection('reverb');
    $reflection = new ReflectionProperty($broadcaster, 'channels');
    $channels = $reflection->getValue($broadcaster);
    $authorizationCallback = $channels['admin.automation.runs'];

    expect($authorizationCallback($admin))->toBeTrue()
        ->and($authorizationCallback($user))->toBeFalse();
});

test('automation run recorder dispatches the admin realtime event when a run changes state', function () {
    $run = AutomationRun::query()->create([
        'automation_key' => 'credit_card_autopay',
        'pipeline' => 'credit_card_autopay',
        'job_class' => 'Tests\\Fixtures\\AutomationJob',
        'status' => AutomationRunStatusEnum::PENDING,
        'trigger_type' => AutomationTriggerTypeEnum::MANUAL,
        'attempt' => 1,
        'host' => 'soamco.test',
        'context' => [],
    ]);

    Event::fake([AutomationRunUpdated::class]);

    $updatedRun = app(AutomationRunRecorder::class)->markRunning($run);

    Event::assertDispatched(AutomationRunUpdated::class, function (AutomationRunUpdated $event) use ($updatedRun): bool {
        $channels = $event->broadcastOn();
        $payload = $event->broadcastWith();

        return count($channels) === 1
            && $channels[0] instanceof PrivateChannel
            && $channels[0]->name === 'private-admin.automation.runs'
            && $event->broadcastAs() === 'automation.run.updated'
            && $payload['run']['uuid'] === $updatedRun->uuid
            && $payload['run']['status'] === AutomationRunStatusEnum::RUNNING->value;
    });
});
