<?php

use App\Jobs\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\PushBroadcast;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Audit\AuditLogService;
use App\Services\Push\DeviceTokenService;
use App\Services\Push\PushNotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('features.push_notifications.enabled', true);
    config()->set('features.push_notifications.profile_enabled', true);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('creates the device tokens table with the expected columns', function () {
    expect(Schema::hasTable('device_tokens'))->toBeTrue()
        ->and(Schema::hasColumns('device_tokens', [
            'uuid',
            'user_id',
            'token',
            'platform',
            'locale',
            'is_active',
            'last_seen_at',
        ]))->toBeTrue();
});

it('registers updates and invalidates device tokens through the service', function () {
    $user = User::factory()->create();
    $service = app(DeviceTokenService::class);

    $registered = $service->registerOrUpdate(
        $user,
        'token-1',
        'web',
        'it',
        'browser-a',
        'asset-version-1',
    );

    expect($registered->user->is($user))->toBeTrue()
        ->and($registered->platform)->toBe('web')
        ->and($registered->device_identifier)->toBe('browser-a')
        ->and($registered->locale)->toBe('it')
        ->and($registered->service_worker_version)->toBe('asset-version-1')
        ->and($registered->is_active)->toBeTrue()
        ->and($registered->last_registered_at)->not->toBeNull();

    $updated = $service->registerOrUpdate(
        $user,
        'token-1',
        'web',
        'en',
        'browser-a',
        'asset-version-2',
    );

    expect($updated->id)->toBe($registered->id)
        ->and($updated->fresh()->locale)->toBe('en')
        ->and($updated->fresh()->service_worker_version)->toBe('asset-version-2');

    $service->cleanupInvalidTokens(['token-1']);

    expect($updated->fresh()->is_active)->toBeFalse()
        ->and($updated->fresh()->invalidation_reason)->toBe('firebase_invalid');
});

it('reactivates an existing inactive device token when the current browser registers again', function () {
    $user = User::factory()->create();

    $inactiveToken = DeviceToken::factory()->for($user)->create([
        'token' => 'browser-token-1',
        'platform' => 'web',
        'locale' => 'it',
        'is_active' => false,
    ]);

    $this->actingAs($user)
        ->postJson(route('settings.profile.push-tokens.store'), [
            'token' => 'browser-token-1',
            'platform' => 'web',
            'locale' => 'en',
            'device_identifier' => 'browser-a',
        ])
        ->assertOk()
        ->assertJson([
            'push' => [
                'enabled' => true,
                'current_device_enabled' => true,
                'active_tokens_count' => 1,
            ],
        ]);

    expect($inactiveToken->fresh()->is_active)->toBeTrue()
        ->and($inactiveToken->fresh()->locale)->toBe('en')
        ->and($inactiveToken->fresh()->device_identifier)->toBe('browser-a');
});

it('rotates the token for the same browser device without creating a new active web device', function () {
    $user = User::factory()->create();
    $service = app(DeviceTokenService::class);

    $firstToken = $service->registerOrUpdate(
        $user,
        'browser-token-1',
        'web',
        'it',
        'browser-a',
        'asset-version-1',
    );

    $rotatedToken = $service->registerOrUpdate(
        $user,
        'browser-token-2',
        'web',
        'it',
        'browser-a',
        'asset-version-2',
    );

    expect($rotatedToken->id)->toBe($firstToken->id)
        ->and($rotatedToken->fresh()->token)->toBe('browser-token-2')
        ->and($rotatedToken->fresh()->service_worker_version)->toBe('asset-version-2')
        ->and(DeviceToken::query()->forUser($user)->active()->count())->toBe(1)
        ->and(DeviceToken::query()->where('token', 'browser-token-1')->doesntExist())->toBeTrue();
});

it('recovers an invalidated browser token automatically when the same device registers a fresh token', function () {
    $user = User::factory()->create();
    $service = app(DeviceTokenService::class);

    $deviceToken = $service->registerOrUpdate(
        $user,
        'stale-token',
        'web',
        'it',
        'browser-a',
        'asset-version-1',
    );

    $service->invalidateTokens(['stale-token'], 'firebase_unknown_token');

    $this->actingAs($user)
        ->postJson(route('settings.profile.push-tokens.store'), [
            'token' => 'fresh-token',
            'platform' => 'web',
            'locale' => 'it',
            'device_identifier' => 'browser-a',
            'service_worker_version' => 'asset-version-2',
        ])
        ->assertOk()
        ->assertJson([
            'push' => [
                'device_lifecycle' => 'rotated',
                'recovered_from_invalidation' => true,
            ],
        ]);

    expect($deviceToken->fresh()->id)->toBe($deviceToken->id)
        ->and($deviceToken->fresh()->token)->toBe('fresh-token')
        ->and($deviceToken->fresh()->is_active)->toBeTrue()
        ->and($deviceToken->fresh()->invalidated_at)->toBeNull()
        ->and($deviceToken->fresh()->invalidation_reason)->toBeNull();
});

it('shows and saves the push enabled preference when the feature is on', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('notification_preferences.push.visible', true)
            ->where('notification_preferences.push.enabled', true)
            ->where('notification_preferences.push.active_tokens_count', 0));

    $this->actingAs($user)
        ->patch(route('settings.profile.notification-preferences.update'), [
            'push' => ['enabled' => false],
            'categories' => [],
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $settings = UserSetting::query()->where('user_id', $user->id)->first();

    expect($settings)->not->toBeNull()
        ->and(data_get($settings?->settings, 'notifications.push.enabled'))->toBeFalse();
});

it('shows the current active web token count on the profile page', function () {
    $user = User::factory()->create();
    DeviceToken::factory()->for($user)->create([
        'platform' => 'web',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('notification_preferences.push.active_tokens_count', 1));
});

it('registers the current browser push token and returns the updated active token count', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('settings.profile.push-tokens.store'), [
            'token' => 'browser-token-1',
            'platform' => 'web',
            'locale' => 'it',
            'device_identifier' => 'browser-a',
        ])
        ->assertOk()
        ->assertJson([
            'push' => [
                'enabled' => true,
                'current_device_enabled' => true,
                'active_tokens_count' => 1,
            ],
        ]);

    $token = DeviceToken::query()->where('token', 'browser-token-1')->firstOrFail();
    $settings = UserSetting::query()->where('user_id', $user->id)->first();

    expect($token->user->is($user))->toBeTrue()
        ->and($token->is_active)->toBeTrue()
        ->and(data_get($settings?->settings, 'notifications.push.enabled'))->toBeTrue();
});

it('returns the current browser push device status without assuming the device is enabled', function () {
    $user = User::factory()->create();
    $user->settings()->create([
        'active_year' => null,
        'base_currency' => $user->base_currency_code,
        'settings' => ['notifications' => ['push' => ['enabled' => true]]],
    ]);

    DeviceToken::factory()->for($user)->create([
        'token' => 'browser-token-1',
        'platform' => 'web',
        'device_identifier' => 'browser-a',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson(route('settings.profile.push-tokens.status'), [
            'token' => 'browser-token-1',
            'platform' => 'web',
            'device_identifier' => 'browser-a',
        ])
        ->assertOk()
        ->assertJson([
            'push' => [
                'global_enabled' => true,
                'current_device_enabled' => true,
                'active_tokens_count' => 1,
            ],
        ]);

    $this->actingAs($user)
        ->postJson(route('settings.profile.push-tokens.status'), [
            'token' => 'missing-device-token',
            'platform' => 'web',
            'device_identifier' => 'browser-missing',
        ])
        ->assertOk()
        ->assertJson([
            'push' => [
                'global_enabled' => true,
                'current_device_enabled' => false,
                'active_tokens_count' => 1,
            ],
        ]);
});

it('disables only the current browser token and keeps the account-level preference unchanged', function () {
    $user = User::factory()->create();
    $user->settings()->create([
        'active_year' => null,
        'base_currency' => $user->base_currency_code,
        'settings' => ['notifications' => ['push' => ['enabled' => true]]],
    ]);

    DeviceToken::factory()->for($user)->create([
        'token' => 'browser-token-1',
        'platform' => 'web',
        'device_identifier' => 'browser-a',
        'is_active' => true,
    ]);
    DeviceToken::factory()->for($user)->create([
        'token' => 'browser-token-2',
        'platform' => 'web',
        'device_identifier' => 'browser-b',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('settings.profile.push-tokens.destroy'), [
            'token' => 'browser-token-1',
            'platform' => 'web',
            'device_identifier' => 'browser-a',
        ])
        ->assertOk()
        ->assertJson([
            'push' => [
                'enabled' => true,
                'active_tokens_count' => 1,
            ],
        ]);

    expect(
        DeviceToken::query()
            ->forUser($user)
            ->where('token', 'browser-token-1')
            ->firstOrFail()
            ->is_active
    )->toBeFalse()
        ->and(
            DeviceToken::query()
                ->forUser($user)
                ->where('token', 'browser-token-2')
                ->firstOrFail()
                ->is_active
        )->toBeTrue()
        ->and(data_get($user->fresh()->settings?->settings, 'notifications.push.enabled'))->toBeTrue();
});

it('disables the current browser token using only the device identifier when the token is no longer available', function () {
    $user = User::factory()->create();

    DeviceToken::factory()->for($user)->create([
        'token' => 'browser-token-1',
        'platform' => 'web',
        'device_identifier' => 'browser-a',
        'is_active' => true,
    ]);

    DeviceToken::factory()->for($user)->create([
        'token' => 'browser-token-2',
        'platform' => 'web',
        'device_identifier' => 'browser-b',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('settings.profile.push-tokens.destroy'), [
            'platform' => 'web',
            'device_identifier' => 'browser-a',
        ])
        ->assertOk()
        ->assertJson([
            'push' => [
                'active_tokens_count' => 1,
            ],
        ]);

    expect(
        DeviceToken::query()
            ->forUser($user)
            ->where('device_identifier', 'browser-a')
            ->firstOrFail()
            ->is_active
    )->toBeFalse()
        ->and(
            DeviceToken::query()
                ->forUser($user)
                ->where('device_identifier', 'browser-a')
                ->firstOrFail()
                ->invalidation_reason
        )->toBe('user_disabled')
        ->and(
            DeviceToken::query()
                ->forUser($user)
                ->where('device_identifier', 'browser-b')
                ->firstOrFail()
                ->is_active
        )->toBeTrue();
});

it('does not deactivate another platform token with the same token value when disabling the current browser token', function () {
    $user = User::factory()->create();
    $service = app(DeviceTokenService::class);

    $webToken = DeviceToken::factory()->for($user)->create([
        'token' => 'shared-token',
        'platform' => 'web',
        'is_active' => true,
    ]);

    $nativeToken = DeviceToken::factory()->for($user)->create([
        'token' => 'native-token',
        'platform' => 'web',
        'is_active' => true,
    ]);

    $service->markInactiveForUserAndPlatform($user, 'shared-token', 'web');

    expect($webToken->fresh()->is_active)->toBeFalse()
        ->and($nativeToken->fresh()->is_active)->toBeTrue();
});

it('checking the current browser push device status does not deactivate the current token', function () {
    $user = User::factory()->create();

    $token = DeviceToken::factory()->for($user)->create([
        'token' => 'browser-token-1',
        'platform' => 'web',
        'device_identifier' => 'browser-a',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson(route('settings.profile.push-tokens.status'), [
            'token' => 'browser-token-1',
            'platform' => 'web',
            'device_identifier' => 'browser-a',
        ])
        ->assertOk()
        ->assertJson([
            'push' => [
                'current_device_enabled' => true,
                'active_tokens_count' => 1,
            ],
        ]);

    expect($token->fresh()->is_active)->toBeTrue();
});

it('does not expose or save the push preference when the feature is off', function () {
    config()->set('features.push_notifications.enabled', false);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('notification_preferences.push.visible', false));

    $this->actingAs($user)
        ->patch(route('settings.profile.notification-preferences.update'), [
            'push' => ['enabled' => false],
            'categories' => [],
        ])
        ->assertSessionHasErrors('push');

    $this->actingAs($user)
        ->postJson(route('settings.profile.push-tokens.store'), [
            'token' => 'browser-token-1',
            'platform' => 'web',
        ])
        ->assertNotFound();
});

it('hides the profile push ui when the user-facing push flag is off', function () {
    config()->set('features.push_notifications.profile_enabled', false);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('notification_preferences.push.visible', false));

    $this->actingAs($user)
        ->postJson(route('settings.profile.push-tokens.status'), [
            'device_identifier' => 'browser-a',
            'platform' => 'web',
        ])
        ->assertOk()
        ->assertJson([
            'push' => [
                'current_device_enabled' => false,
            ],
        ]);
});

it('filters eligible users and invalidates tokens reported as invalid by firebase', function () {
    $eligibleUser = User::factory()->create();
    $disabledUser = User::factory()->create();
    $disabledUser->settings()->create([
        'active_year' => null,
        'base_currency' => $disabledUser->base_currency_code,
        'settings' => ['notifications' => ['push' => ['enabled' => false]]],
    ]);

    DeviceToken::factory()->for($eligibleUser)->create([
        'token' => 'valid-token',
        'is_active' => true,
    ]);
    DeviceToken::factory()->for($eligibleUser)->create([
        'token' => 'stale-token',
        'is_active' => true,
    ]);
    DeviceToken::factory()->for($disabledUser)->create([
        'token' => 'disabled-token',
        'is_active' => true,
    ]);

    $messaging = Mockery::mock(Messaging::class);
    $messaging
        ->shouldReceive('sendMulticast')
        ->once()
        ->andReturn(MulticastSendReport::withItems([
            SendReport::success(
                MessageTarget::with(MessageTarget::TOKEN, 'valid-token'),
                ['name' => 'message-1'],
            ),
            SendReport::failure(
                MessageTarget::with(MessageTarget::TOKEN, 'stale-token'),
                NotFound::becauseTokenNotFound('stale-token'),
            ),
        ]));

    app()->instance(Messaging::class, $messaging);

    $broadcast = PushBroadcast::factory()->create();
    $summary = app(PushNotificationService::class)->sendBroadcast($broadcast);

    expect($summary)->toMatchArray([
        'eligible_users_count' => 1,
        'target_tokens_count' => 2,
        'sent_count' => 1,
        'failed_count' => 1,
        'invalidated_count' => 1,
    ])
        ->and(DeviceToken::query()->where('token', 'stale-token')->firstOrFail()->is_active)->toBeFalse()
        ->and(DeviceToken::query()->where('token', 'stale-token')->firstOrFail()->invalidation_reason)->toBe('firebase_unknown_token')
        ->and(DeviceToken::query()->where('token', 'disabled-token')->firstOrFail()->is_active)->toBeTrue();
});

it('logs the firebase invalidation context when firebase rejects tokens', function () {
    config()->set('firebase.default', 'app');
    config()->set(
        'firebase.projects.app.credentials',
        storage_path('app/private/firebase/n8n-gialdeb-firebase-adminsdk-fbsvc-b30050a7ed.json'),
    );

    Log::shouldReceive('warning')
        ->once()
        ->withAnyArgs();
    Log::shouldReceive('info')
        ->once()
        ->withAnyArgs();

    $eligibleUser = User::factory()->create();

    DeviceToken::factory()->for($eligibleUser)->create([
        'token' => 'unknown-token',
        'is_active' => true,
    ]);
    DeviceToken::factory()->for($eligibleUser)->create([
        'token' => 'invalid-token',
        'is_active' => true,
    ]);

    $messaging = Mockery::mock(Messaging::class);
    $messaging
        ->shouldReceive('sendMulticast')
        ->once()
        ->andReturn(MulticastSendReport::withItems([
            SendReport::failure(
                MessageTarget::with(MessageTarget::TOKEN, 'unknown-token'),
                NotFound::becauseTokenNotFound('unknown-token'),
            ),
            SendReport::failure(
                MessageTarget::with(MessageTarget::TOKEN, 'invalid-token'),
                NotFound::becauseTokenNotFound('invalid-token'),
            ),
        ]));

    app()->instance(Messaging::class, $messaging);

    $broadcast = PushBroadcast::factory()->create();

    app(PushNotificationService::class)->sendBroadcast($broadcast);

    expect($broadcast->fresh())->not->toBeNull();
});

it('builds a browser-oriented webpush payload for broadcasts', function () {
    config()->set('push-notifications.webpush.notification.require_interaction', true);
    config()->set('push-notifications.webpush.headers', [
        'Urgency' => 'high',
        'TTL' => '300',
    ]);

    $user = User::factory()->create();
    DeviceToken::factory()->for($user)->create([
        'token' => 'valid-token',
        'is_active' => true,
    ]);

    $messaging = Mockery::mock(Messaging::class);
    $messaging
        ->shouldReceive('sendMulticast')
        ->once()
        ->with(
            Mockery::on(function ($message): bool {
                if (! $message instanceof CloudMessage) {
                    return false;
                }

                $payload = $message->jsonSerialize();

                return data_get($payload, 'notification') === null
                    && data_get($payload, 'webpush.notification') === null
                    && data_get($payload, 'data.title') === 'Saldo aggiornato'
                    && data_get($payload, 'data.body') === 'Controlla i movimenti del mese.'
                    && data_get($payload, 'data.icon') === URL::asset('pwa/icons/icon-192.png')
                    && data_get($payload, 'data.badge') === URL::asset('pwa/icons/icon-maskable-192.png')
                    && data_get($payload, 'data.require_interaction') === 'true'
                    && data_get($payload, 'data.url') === 'https://example.com/transactions'
                    && data_get($payload, 'webpush.headers.Urgency') === 'high'
                    && data_get($payload, 'webpush.headers.TTL') === '300'
                    && data_get($payload, 'webpush.data.title') === 'Saldo aggiornato'
                    && data_get($payload, 'webpush.data.body') === 'Controlla i movimenti del mese.'
                    && data_get($payload, 'webpush.data.icon') === URL::asset('pwa/icons/icon-192.png')
                    && data_get($payload, 'webpush.data.badge') === URL::asset('pwa/icons/icon-maskable-192.png')
                    && data_get($payload, 'webpush.data.require_interaction') === 'true'
                    && data_get($payload, 'webpush.data.url') === 'https://example.com/transactions'
                    && data_get($payload, 'webpush.fcm_options.link') === 'https://example.com/transactions';
            }),
            ['valid-token'],
        )
        ->andReturn(MulticastSendReport::withItems([
            SendReport::success(
                MessageTarget::with(MessageTarget::TOKEN, 'valid-token'),
                ['name' => 'message-1'],
            ),
        ]));

    app()->instance(Messaging::class, $messaging);

    $broadcast = PushBroadcast::factory()->create([
        'title' => 'Saldo aggiornato',
        'body' => 'Controlla i movimenti del mese.',
        'url' => 'https://example.com/transactions',
    ]);

    $summary = app(PushNotificationService::class)->sendBroadcast($broadcast);

    expect($summary)->toMatchArray([
        'eligible_users_count' => 1,
        'target_tokens_count' => 1,
        'sent_count' => 1,
        'failed_count' => 0,
        'invalidated_count' => 0,
    ]);
});

it('the send push notification job delegates to the service and updates the broadcast summary', function () {
    $admin = User::factory()->create();
    $broadcast = PushBroadcast::factory()->for($admin, 'creator')->create([
        'status' => 'queued',
    ]);

    $service = Mockery::mock(PushNotificationService::class);
    $service->shouldReceive('sendBroadcast')
        ->once()
        ->with(Mockery::on(fn ($value) => $value instanceof PushBroadcast && $value->is($broadcast)))
        ->andReturn([
            'eligible_users_count' => 2,
            'target_tokens_count' => 3,
            'sent_count' => 3,
            'failed_count' => 0,
            'invalidated_count' => 0,
        ]);

    app()->instance(PushNotificationService::class, $service);

    app(SendPushNotificationJob::class, ['pushBroadcastId' => $broadcast->id])->handle(
        app(PushNotificationService::class),
        app(AuditLogService::class),
    );

    expect($broadcast->fresh()->status)->toBe('completed')
        ->and($broadcast->fresh()->sent_count)->toBe(3);
});

it('exposes the admin push broadcast page only when the feature is on and enqueues broadcasts', function () {
    Queue::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    DeviceToken::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.push-broadcasts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/PushBroadcasts')
            ->where('auth.user.is_admin', true));

    $this->actingAs($admin)
        ->post(route('admin.push-broadcasts.store'), [
            'title' => 'Saldo aggiornato',
            'body' => 'Controlla i movimenti del mese.',
            'url' => 'https://example.com/transactions',
            'target_mode' => 'all',
        ])
        ->assertRedirect();

    Queue::assertPushed(SendPushNotificationJob::class);

    config()->set('features.push_notifications.enabled', false);

    $this->actingAs($admin)
        ->get(route('admin.push-broadcasts.index'))
        ->assertNotFound();
});

it('counts users as eligible in the admin audience summary when at least one active token exists and push is enabled', function () {
    $eligibleUser = User::factory()->create();
    $disabledUser = User::factory()->create();

    $disabledUser->settings()->create([
        'active_year' => null,
        'base_currency' => $disabledUser->base_currency_code,
        'settings' => ['notifications' => ['push' => ['enabled' => false]]],
    ]);

    DeviceToken::factory()->for($eligibleUser)->create([
        'token' => 'eligible-token',
        'platform' => 'web',
        'is_active' => true,
    ]);

    DeviceToken::factory()->for($disabledUser)->create([
        'token' => 'disabled-token',
        'platform' => 'web',
        'is_active' => true,
    ]);

    expect(app(PushNotificationService::class)->eligibleAudienceSummary())->toMatchArray([
        'eligible_users_count' => 1,
        'target_tokens_count' => 1,
    ]);
});

it('drops the admin audience summary when one of two browser devices is removed', function () {
    $user = User::factory()->create();
    $service = app(DeviceTokenService::class);

    $service->registerOrUpdate($user, 'browser-token-1', 'web', 'it', 'browser-a');
    $service->registerOrUpdate($user, 'browser-token-2', 'web', 'it', 'browser-b');

    expect(app(PushNotificationService::class)->eligibleAudienceSummary())->toMatchArray([
        'eligible_users_count' => 1,
        'target_tokens_count' => 2,
    ]);

    $service->markInactiveCurrentDevice($user, 'browser-b', null, 'web', 'permission_revoked');

    expect(app(PushNotificationService::class)->eligibleAudienceSummary())->toMatchArray([
        'eligible_users_count' => 1,
        'target_tokens_count' => 1,
    ]);
});

it('deduplicates active broadcast targets per browser device and prunes legacy duplicates', function () {
    $user = User::factory()->create();

    DeviceToken::factory()->for($user)->create([
        'token' => 'browser-token-old',
        'platform' => 'web',
        'device_identifier' => 'browser-a',
        'is_active' => true,
        'last_registered_at' => now()->subMinute(),
    ]);

    $latestToken = DeviceToken::factory()->for($user)->create([
        'token' => 'browser-token-new',
        'platform' => 'web',
        'device_identifier' => 'browser-a',
        'is_active' => true,
        'last_registered_at' => now(),
    ]);

    $service = app(DeviceTokenService::class);
    $broadcastTokens = $service->activeBroadcastTokensForUser($user);

    expect($broadcastTokens)->toHaveCount(1)
        ->and($broadcastTokens->first()?->is($latestToken))->toBeTrue()
        ->and(
            DeviceToken::query()->where('token', 'browser-token-old')->firstOrFail()->fresh()->is_active,
        )->toBeFalse()
        ->and(
            DeviceToken::query()->where('token', 'browser-token-old')->firstOrFail()->fresh()->invalidation_reason,
        )->toBe('superseded_duplicate_active_token');
});

it('sends push broadcasts only to the latest active token for each browser device', function () {
    $eligibleUser = User::factory()->create();

    DeviceToken::factory()->for($eligibleUser)->create([
        'token' => 'stale-device-token',
        'platform' => 'web',
        'device_identifier' => 'browser-a',
        'is_active' => true,
        'last_registered_at' => now()->subMinute(),
    ]);

    DeviceToken::factory()->for($eligibleUser)->create([
        'token' => 'current-device-token',
        'platform' => 'web',
        'device_identifier' => 'browser-a',
        'is_active' => true,
        'last_registered_at' => now(),
    ]);

    $messaging = Mockery::mock(Messaging::class);
    $messaging
        ->shouldReceive('sendMulticast')
        ->once()
        ->with(Mockery::type(CloudMessage::class), ['current-device-token'])
        ->andReturn(MulticastSendReport::withItems([
            SendReport::success(
                MessageTarget::with(MessageTarget::TOKEN, 'current-device-token'),
                ['name' => 'message-1'],
            ),
        ]));

    app()->instance(Messaging::class, $messaging);

    $summary = app(PushNotificationService::class)->sendBroadcast(
        PushBroadcast::factory()->create(),
    );

    expect($summary)->toMatchArray([
        'eligible_users_count' => 1,
        'target_tokens_count' => 1,
        'sent_count' => 1,
        'failed_count' => 0,
        'invalidated_count' => 0,
    ])
        ->and(DeviceToken::query()->where('token', 'stale-device-token')->firstOrFail()->fresh()->is_active)->toBeFalse();
});
