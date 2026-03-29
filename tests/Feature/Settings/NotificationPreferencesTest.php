<?php

use App\Models\CommunicationCategory;
use App\Models\NotificationTopic;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

test('profile page shows only user configurable notification topics', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('notification_preferences.categories', fn ($categories) => collect($categories)->pluck('key')->all() === [
                'credit_cards.autopay_completed',
                'imports.completed',
                'reports.weekly_ready',
            ])
            ->where('notification_preferences.categories', fn ($categories) => collect($categories)->every(
                fn (array $category) => is_string($category['uuid'])
                    && ! array_key_exists('id', $category)
                    && ! in_array($category['key'], [
                        'auth.verify_email',
                        'auth.reset_password',
                        'user.welcome_after_verification',
                    ], true)
            ))
        );
});

test('profile page falls back to topic defaults when no notification preferences exist yet', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('notification_preferences.categories', fn ($categories) => collect($categories)->contains(
                fn (array $category) => $category['key'] === 'credit_cards.autopay_completed'
                    && $category['preferences']['email_enabled'] === true
                    && $category['preferences']['in_app_enabled'] === true
            ))
            ->where('notification_preferences.categories', fn ($categories) => collect($categories)->contains(
                fn (array $category) => $category['key'] === 'imports.completed'
                    && $category['preferences']['email_enabled'] === false
                    && $category['preferences']['in_app_enabled'] === true
            ))
            ->where('notification_preferences.categories', fn ($categories) => collect($categories)->contains(
                fn (array $category) => $category['key'] === 'reports.weekly_ready'
                    && $category['preferences']['email_enabled'] === true
                    && $category['preferences']['in_app_enabled'] === false
            ))
        );
});

test('user can update notification preferences from profile settings', function () {
    $user = User::factory()->create();
    $creditCardAutopayCompleted = CommunicationCategory::query()->where('key', 'credit_cards.autopay_completed')->firstOrFail();
    $importsCompleted = CommunicationCategory::query()->where('key', 'imports.completed')->firstOrFail();
    $reportsWeeklyReady = CommunicationCategory::query()->where('key', 'reports.weekly_ready')->firstOrFail();
    $creditCardAutopayTopic = NotificationTopic::query()->where('key', 'credit_card_autopay_completed')->firstOrFail();
    $importCompleted = NotificationTopic::query()->where('key', 'import_completed')->firstOrFail();
    $monthlyReportReady = NotificationTopic::query()->where('key', 'monthly_report_ready')->firstOrFail();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('settings.profile.notification-preferences.update'), [
            'categories' => [
                [
                    'uuid' => $creditCardAutopayCompleted->uuid,
                    'email_enabled' => false,
                    'in_app_enabled' => true,
                ],
                [
                    'uuid' => $importsCompleted->uuid,
                    'email_enabled' => true,
                    'in_app_enabled' => false,
                ],
                [
                    'uuid' => $reportsWeeklyReady->uuid,
                    'email_enabled' => false,
                    'in_app_enabled' => true,
                ],
            ],
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success');

    $creditCardPreference = UserNotificationPreference::query()
        ->where('user_id', $user->id)
        ->where('notification_topic_id', $creditCardAutopayTopic->id)
        ->firstOrFail();
    $importPreference = UserNotificationPreference::query()
        ->where('user_id', $user->id)
        ->where('notification_topic_id', $importCompleted->id)
        ->firstOrFail();
    $monthlyPreference = UserNotificationPreference::query()
        ->where('user_id', $user->id)
        ->where('notification_topic_id', $monthlyReportReady->id)
        ->firstOrFail();

    expect($creditCardPreference->email_enabled)->toBeFalse()
        ->and($creditCardPreference->in_app_enabled)->toBeTrue()
        ->and($importPreference->email_enabled)->toBeTrue()
        ->and($importPreference->in_app_enabled)->toBeFalse()
        ->and($monthlyPreference->email_enabled)->toBeFalse()
        ->and($monthlyPreference->in_app_enabled)->toBeFalse();
});

test('profile page can render an empty configurable notifications state', function () {
    $user = User::factory()->create();

    NotificationTopic::query()
        ->whereIn('key', ['import_completed', 'monthly_report_ready'])
        ->update([
            'is_active' => false,
        ]);

    CommunicationCategory::query()
        ->whereIn('key', ['credit_cards.autopay_completed', 'imports.completed', 'reports.weekly_ready'])
        ->update([
            'is_active' => false,
        ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('notification_preferences.categories', [])
        );
});
