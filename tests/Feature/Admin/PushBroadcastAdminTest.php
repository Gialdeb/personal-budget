<?php

use App\Jobs\SendPushNotificationJob;
use App\Jobs\SendTargetedPushBroadcastJob;
use App\Models\DeviceToken;
use App\Models\PushBroadcast;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('features.push_notifications.enabled', true);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createPushBroadcastAdminUser(): User
{
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $admin->assignRole('admin');

    return $admin;
}

function createActivePushToken(User $user, array $attributes = []): DeviceToken
{
    return DeviceToken::factory()->for($user)->create(array_merge([
        'platform' => 'web',
        'is_active' => true,
        'device_identifier' => 'browser-'.fake()->uuid(),
        'last_seen_at' => now(),
    ], $attributes));
}

it('renders the admin push broadcasts page with history and recipient sections', function () {
    $admin = createPushBroadcastAdminUser();

    $eligibleUser = User::factory()->create([
        'name' => 'Lucia',
        'surname' => 'Rossi',
        'email' => 'lucia@example.com',
    ]);
    createActivePushToken($eligibleUser);

    $disabledUser = User::factory()->create([
        'name' => 'Marco',
        'surname' => 'Bianchi',
        'email' => 'marco@example.com',
    ]);
    $disabledUser->settings()->create([
        'active_year' => null,
        'base_currency' => $disabledUser->base_currency_code,
        'settings' => ['notifications' => ['push' => ['enabled' => false]]],
    ]);
    createActivePushToken($disabledUser, ['last_seen_at' => now()->subHour()]);

    $inactiveUser = User::factory()->create([
        'name' => 'Giulia',
        'surname' => 'Verdi',
        'email' => 'giulia@example.com',
    ]);

    PushBroadcast::factory()->for($admin, 'creator')->create([
        'title' => 'Report pronto',
        'body' => 'Apri il riepilogo settimanale.',
        'status' => 'completed',
        'eligible_users_count' => 2,
        'target_tokens_count' => 2,
        'sent_count' => 2,
        'payload_snapshot' => [
            'target' => ['mode' => 'all'],
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.push-broadcasts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/PushBroadcasts')
            ->where('audience.eligible_users_count', 1)
            ->where('audience.users_with_active_tokens_count', 2)
            ->where('audience.users_without_active_push_count', 3)
            ->where('broadcasts.data.0.title', 'Report pronto')
            ->where('activePushUsers.data.0.email', 'lucia@example.com')
            ->where('inactivePushUsers.data', fn ($users) => collect($users)->contains(
                fn (array $user) => $user['email'] === 'giulia@example.com'
            )));
});

it('paginates and filters the push broadcast history', function () {
    $admin = createPushBroadcastAdminUser();

    foreach (range(1, 15) as $index) {
        PushBroadcast::factory()->for($admin, 'creator')->create([
            'title' => "Broadcast {$index}",
            'status' => 'completed',
            'payload_snapshot' => ['target' => ['mode' => 'all']],
        ]);
    }

    PushBroadcast::factory()->for($admin, 'creator')->create([
        'title' => 'Single target audit',
        'body' => 'Only one recipient.',
        'status' => 'failed',
        'payload_snapshot' => [
            'target' => [
                'mode' => 'single',
                'user_uuids' => ['uuid-test'],
                'users' => [[
                    'uuid' => 'uuid-test',
                    'label' => 'Mario Rossi',
                    'email' => 'mario@example.com',
                ]],
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.push-broadcasts.index', [
            'history_search' => 'Single target',
            'history_type' => 'single_user',
            'history_status' => 'failed',
            'history_date' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/PushBroadcasts')
            ->where('filters.history_search', 'Single target')
            ->where('filters.history_type', 'single_user')
            ->where('filters.history_status', 'failed')
            ->where('broadcasts.meta.total', 1)
            ->where('broadcasts.data.0.target_mode', 'single_user')
            ->where('broadcasts.data.0.status', 'failed'));

    $this->actingAs($admin)
        ->get(route('admin.push-broadcasts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('broadcasts.meta.last_page', 2));
});

it('queues a broadcast for all eligible users without changing the existing delivery path', function () {
    Queue::fake();

    $admin = createPushBroadcastAdminUser();
    $recipient = User::factory()->create();
    createActivePushToken($recipient);

    $this->actingAs($admin)
        ->post(route('admin.push-broadcasts.store'), [
            'title' => 'Saldo aggiornato',
            'body' => 'Controlla i movimenti del mese.',
            'url' => 'https://example.com/transactions',
            'target_mode' => 'all',
        ])
        ->assertRedirect();

    $broadcast = PushBroadcast::query()->latest('id')->firstOrFail();

    expect(data_get($broadcast->payload_snapshot, 'target.mode'))->toBe('all')
        ->and($broadcast->eligible_users_count)->toBe(1)
        ->and($broadcast->target_tokens_count)->toBe(1);

    Queue::assertPushed(SendPushNotificationJob::class);
    Queue::assertNotPushed(SendTargetedPushBroadcastJob::class);
});

it('queues a targeted single-user push send through the admin-only job', function () {
    Queue::fake();

    $admin = createPushBroadcastAdminUser();
    $recipient = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
    ]);
    createActivePushToken($recipient, ['device_identifier' => 'browser-a']);
    createActivePushToken($recipient, ['device_identifier' => 'browser-b']);

    $this->actingAs($admin)
        ->post(route('admin.push-broadcasts.store'), [
            'title' => 'Alert personale',
            'body' => 'Messaggio solo per un utente.',
            'url' => '',
            'target_mode' => 'single',
            'target_user_uuid' => $recipient->uuid,
        ])
        ->assertRedirect();

    $broadcast = PushBroadcast::query()->latest('id')->firstOrFail();

    expect(data_get($broadcast->payload_snapshot, 'target.mode'))->toBe('single')
        ->and(data_get($broadcast->payload_snapshot, 'target.user_uuids.0'))->toBe($recipient->uuid)
        ->and($broadcast->eligible_users_count)->toBe(1)
        ->and($broadcast->target_tokens_count)->toBe(2);

    Queue::assertPushed(SendTargetedPushBroadcastJob::class);
    Queue::assertNotPushed(SendPushNotificationJob::class);
});

it('sends an explicit in-app reminder to a user without active push', function () {
    $admin = createPushBroadcastAdminUser();
    $recipient = User::factory()->create([
        'name' => 'Giulia',
        'surname' => 'Verdi',
        'email' => 'giulia@example.com',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.push-broadcasts.reminders.store'), [
            'user_uuid' => $recipient->uuid,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $notification = $recipient->notifications()->latest('id')->first();

    expect($notification)->not->toBeNull()
        ->and($notification?->data['content']['title'])->toBe('Attiva le notifiche push')
        ->and($notification?->data['content']['cta_url'])->toBe('/settings/profile');
});
