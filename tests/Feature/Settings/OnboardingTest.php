<?php

use App\Models\User;
use App\Models\UserSetting;
use App\Services\OnboardingService;
use App\Services\UserSettingsService;
use Inertia\Testing\AssertableInertia as Assert;

it('creates settings in the users currency and keeps independent branches across stale readers', function () {
    $user = User::factory()->create(['base_currency_code' => 'USD']);
    $user->load('settings');
    $staleReader = User::findOrFail($user->id);
    $staleReader->load('settings');
    $service = app(UserSettingsService::class);

    $service->updateBranches($user, ['dashboard.visible_boxes.balance' => false, 'custom' => ['nested' => ['value' => 42]]]);
    app(OnboardingService::class)->update($staleReader, 'complete');
    $service->updateBranches($user, ['notifications.push.enabled' => false, 'timezone' => 'Europe/Rome']);
    $service->updateBranches($staleReader, ['notifications.push.daily_expense_reminder_enabled' => true]);

    $settings = $user->settings()->sole();
    expect($settings->base_currency)->toBe('USD')
        ->and($settings->settings)->toBe([
            'dashboard' => ['visible_boxes' => ['balance' => false]],
            'custom' => ['nested' => ['value' => 42]],
            'onboarding' => [
                'enabled' => true, 'tours' => ['dashboard' => 'completed'],
                'quick_start_dismissed' => false, 'legacy_imported' => false, 'revision' => 1,
            ],
            'notifications' => ['push' => ['enabled' => false, 'daily_expense_reminder_enabled' => true]],
            'timezone' => 'Europe/Rome',
        ]);
});

it('rolls back an entire branch update including creation when a callback fails', function () {
    $user = User::factory()->create();
    expect(fn () => app(UserSettingsService::class)->updateBranches($user, [
        'timezone' => 'Europe/Rome',
        'onboarding' => fn () => throw new RuntimeException('Abort update'),
    ]))->toThrow(RuntimeException::class);
    expect($user->settings()->exists())->toBeFalse();
});

it('preserves settings metadata and unrelated nested preferences when updating onboarding', function () {
    $user = User::factory()->create();
    $settings = UserSetting::create([
        'user_id' => $user->id, 'active_year' => 2025, 'base_currency' => 'USD',
        'settings' => ['dashboard' => ['savings_mode' => 'custom'], 'onboarding' => ['future_option' => ['keep' => true]]],
    ]);
    $this->actingAs($user)->postJson(route('onboarding.update'), ['action' => 'skip'])
        ->assertOk()->assertJsonPath('tours.dashboard', 'skipped');
    $settings->refresh();
    expect($settings->active_year)->toBe(2025)
        ->and($settings->base_currency)->toBe('USD')
        ->and($settings->settings['dashboard'])->toBe(['savings_mode' => 'custom'])
        ->and($settings->settings['onboarding']['future_option'])->toBe(['keep' => true]);
});

it('persists completion disabling and restart for the authenticated user only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user)->postJson(route('onboarding.update'), ['action' => 'complete', 'user_id' => $other->id])
        ->assertOk()->assertJsonPath('tours.dashboard', 'completed');
    $this->postJson(route('onboarding.update'), ['action' => 'disable'])->assertOk()->assertJsonPath('enabled', false);
    $this->postJson(route('onboarding.update'), ['action' => 'reset'])->assertOk()
        ->assertJsonPath('enabled', true)->assertJsonPath('tours', []);
    expect($other->settings()->exists())->toBeFalse()
        ->and($user->settings()->sole()->settings['onboarding']['revision'])->toBe(3);
});

it('imports the legacy quick start only once without completing tours or reenabling onboarding', function () {
    $user = User::factory()->create();
    app(OnboardingService::class)->update($user, 'disable');
    $this->actingAs($user)->postJson(route('onboarding.update'), ['action' => 'migrate', 'quick_start_dismissed' => true])
        ->assertOk()->assertJsonPath('enabled', false)->assertJsonPath('legacy_imported', true)
        ->assertJsonPath('quick_start_dismissed', true)->assertJsonPath('tours', []);
    $this->postJson(route('onboarding.update'), ['action' => 'migrate', 'quick_start_dismissed' => false])
        ->assertOk()->assertJsonPath('quick_start_dismissed', true);
    $this->postJson(route('onboarding.update'), ['action' => 'reset'])
        ->assertOk()->assertJsonPath('quick_start_dismissed', true);
});

it('does not replace an existing migrated preference with the local value', function () {
    $user = User::factory()->create();
    app(OnboardingService::class)->update($user, 'migrate', false);
    $this->actingAs($user)->postJson(route('onboarding.update'), ['action' => 'migrate', 'quick_start_dismissed' => true])
        ->assertOk()->assertJsonPath('quick_start_dismissed', false);
});

it('rejects invalid onboarding changes', function (array $payload, string $error) {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('onboarding.update'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($error);
    expect($user->settings()->exists())->toBeFalse();
})->with([
    [['action' => 'unknown'], 'action'],
    [['action' => 'migrate', 'quick_start_dismissed' => 'invalid'], 'quick_start_dismissed'],
    [[], 'action'],
]);

it('rejects unauthenticated writes', function () {
    $this->postJson(route('onboarding.update'), ['action' => 'complete'])->assertUnauthorized();
});

it('shares only onboarding state on app pages and preserves contextual help fallback', function () {
    $user = User::factory()->create();
    app(UserSettingsService::class)->updateBranches($user, ['private_preference' => 'secret']);
    app(OnboardingService::class)->update($user, 'complete');
    $this->actingAs($user)->get(route('support.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/Support')
            ->where('onboarding.tours.dashboard', 'completed')
            ->missing('onboarding.private_preference')
            ->has('contextualHelp'));
});

it('keeps onboarding when push preferences change and keeps push settings when the tour changes', function () {
    config()->set('features.push_notifications.enabled', true);
    $user = User::factory()->create();
    app(UserSettingsService::class)->updateBranches($user, [
        'timezone' => 'Europe/Rome', 'notifications.push.daily_expense_reminder_enabled' => false,
        'notifications.extra' => ['keep' => true], 'dashboard.visible_charts.balance' => false,
    ]);
    $this->actingAs($user)->postJson(route('onboarding.update'), ['action' => 'complete'])->assertOk();
    $this->patch(route('settings.profile.notification-preferences.update'), [
        'categories' => [], 'push' => ['enabled' => false],
    ])->assertSessionHasNoErrors()->assertRedirect();
    $settings = $user->settings()->sole()->settings;
    expect($settings['onboarding']['tours']['dashboard'])->toBe('completed')
        ->and($settings['notifications']['push'])->toBe(['daily_expense_reminder_enabled' => false, 'enabled' => false])
        ->and($settings['notifications']['extra'])->toBe(['keep' => true])
        ->and($settings['timezone'])->toBe('Europe/Rome');
    $this->postJson(route('onboarding.update'), ['action' => 'skip'])->assertOk();
    $settings = $user->settings()->sole()->settings;
    expect($settings['notifications']['push']['enabled'])->toBeFalse()
        ->and($settings['dashboard']['visible_charts']['balance'])->toBeFalse();
});

it('stores each section independently and preserves previous dashboard state', function (string $section) {
    $user = User::factory()->create();
    app(UserSettingsService::class)->updateBranches($user, [
        'dashboard' => ['visible_boxes' => ['balance' => false]],
        'onboarding' => [
            'enabled' => false,
            'tours' => ['dashboard' => 'completed', 'future-section' => 'skipped'],
            'quick_start_dismissed' => true, 'legacy_imported' => true,
            'custom' => ['keep' => true], 'revision' => 7,
        ],
    ]);
    $this->actingAs($user)->postJson(route('onboarding.update'), ['action' => 'complete', 'section' => $section])
        ->assertOk()->assertJsonPath("tours.{$section}", 'completed')->assertJsonPath('tours.dashboard', 'completed')
        ->assertJsonPath('enabled', false);
    $this->postJson(route('onboarding.update'), ['action' => 'skip', 'section' => $section])
        ->assertOk()->assertJsonPath("tours.{$section}", 'skipped')->assertJsonPath('tours.dashboard', 'completed');
    $this->postJson(route('onboarding.update'), ['action' => 'reset', 'section' => $section])
        ->assertOk()->assertJsonMissingPath("tours.{$section}")
        ->assertJsonPath('tours.dashboard', 'completed')->assertJsonPath('tours.future-section', 'skipped')
        ->assertJsonPath('quick_start_dismissed', true)->assertJsonPath('legacy_imported', true);
    $settings = $user->settings()->sole()->settings;
    expect($settings['onboarding']['custom'])->toBe(['keep' => true])
        ->and($settings['dashboard'])->toBe(['visible_boxes' => ['balance' => false]]);
})->with(['transactions', 'recurring', 'shared-expenses']);

it('rejects unsupported sections without changing stored onboarding', function (mixed $section) {
    $user = User::factory()->create();
    app(OnboardingService::class)->update($user, 'complete');
    $before = $user->settings()->sole()->settings;
    $this->actingAs($user)->postJson(route('onboarding.update'), ['action' => 'complete', 'section' => $section])
        ->assertUnprocessable()->assertJsonValidationErrors('section');
    expect($user->settings()->sole()->settings)->toBe($before);
})->with([['other'], [''], [null], [['dashboard']]]);

it('defaults legacy reset requests to dashboard without clearing other sections', function () {
    $user = User::factory()->create();
    app(OnboardingService::class)->update($user, 'complete');
    app(OnboardingService::class)->update($user, 'skip', section: 'transactions');
    $this->actingAs($user)->postJson(route('onboarding.update'), ['action' => 'reset'])
        ->assertOk()->assertJsonMissingPath('tours.dashboard')->assertJsonPath('tours.transactions', 'skipped');
});
