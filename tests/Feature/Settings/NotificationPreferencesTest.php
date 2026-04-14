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
    config()->set('features.imports.enabled', false);
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
                'recurring.monthly_due_summary',
                'recurring.weekly_due_summary',
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
                fn (array $category) => $category['key'] === 'recurring.weekly_due_summary'
                    && $category['preferences']['email_enabled'] === false
                    && $category['preferences']['in_app_enabled'] === false
            ))
            ->where('notification_preferences.categories', fn ($categories) => collect($categories)->contains(
                fn (array $category) => $category['key'] === 'recurring.monthly_due_summary'
                    && $category['preferences']['email_enabled'] === false
                    && $category['preferences']['in_app_enabled'] === false
            ))
        );
});

test('user can update notification preferences from profile settings', function () {
    $user = User::factory()->create();
    $creditCardAutopayCompleted = CommunicationCategory::query()->where('key', 'credit_cards.autopay_completed')->firstOrFail();
    $weeklySummary = CommunicationCategory::query()->where('key', 'recurring.weekly_due_summary')->firstOrFail();
    $monthlySummary = CommunicationCategory::query()->where('key', 'recurring.monthly_due_summary')->firstOrFail();
    $creditCardAutopayTopic = NotificationTopic::query()->where('key', 'credit_card_autopay_completed')->firstOrFail();
    $weeklySummaryTopic = NotificationTopic::query()->where('key', 'recurring_weekly_due_summary')->firstOrFail();
    $monthlySummaryTopic = NotificationTopic::query()->where('key', 'recurring_monthly_due_summary')->firstOrFail();

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
                    'uuid' => $weeklySummary->uuid,
                    'email_enabled' => true,
                    'in_app_enabled' => false,
                ],
                [
                    'uuid' => $monthlySummary->uuid,
                    'email_enabled' => true,
                    'in_app_enabled' => false,
                ],
            ],
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success');

    $creditCardPreference = UserNotificationPreference::query()
        ->where('user_id', $user->id)
        ->where('notification_topic_id', $creditCardAutopayTopic->id)
        ->firstOrFail();
    $weeklyPreference = UserNotificationPreference::query()
        ->where('user_id', $user->id)
        ->where('notification_topic_id', $weeklySummaryTopic->id)
        ->firstOrFail();
    $monthlyPreference = UserNotificationPreference::query()
        ->where('user_id', $user->id)
        ->where('notification_topic_id', $monthlySummaryTopic->id)
        ->firstOrFail();

    expect($creditCardPreference->email_enabled)->toBeFalse()
        ->and($creditCardPreference->in_app_enabled)->toBeTrue()
        ->and($weeklyPreference->email_enabled)->toBeTrue()
        ->and($weeklyPreference->in_app_enabled)->toBeFalse()
        ->and($monthlyPreference->email_enabled)->toBeTrue()
        ->and($monthlyPreference->in_app_enabled)->toBeFalse();
});

test('import completed preference stays hidden and cannot be saved when imports are disabled', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notification_preferences.categories', fn ($categories) => collect($categories)
                ->doesntContain(fn (array $category) => $category['key'] === 'imports.completed'))
        );

    $importCategory = CommunicationCategory::query()->where('key', 'imports.completed')->firstOrFail();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('settings.profile.notification-preferences.update'), [
            'categories' => [
                [
                    'uuid' => $importCategory->uuid,
                    'email_enabled' => true,
                    'in_app_enabled' => true,
                ],
            ],
        ])
        ->assertSessionHasErrors('categories.0.uuid');

    expect(UserNotificationPreference::query()->count())->toBe(0);
});

test('report available preference is not exposed or saved', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notification_preferences.categories', fn ($categories) => collect($categories)
                ->doesntContain(fn (array $category) => $category['key'] === 'reports.weekly_ready'))
        );

    $reportCategory = CommunicationCategory::query()->where('key', 'reports.weekly_ready')->firstOrFail();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('settings.profile.notification-preferences.update'), [
            'categories' => [
                [
                    'uuid' => $reportCategory->uuid,
                    'email_enabled' => true,
                    'in_app_enabled' => true,
                ],
            ],
        ])
        ->assertSessionHasErrors('categories.0.uuid');

    expect(UserNotificationPreference::query()->count())->toBe(0);
});

test('profile page can render an empty configurable notifications state', function () {
    $user = User::factory()->create();

    NotificationTopic::query()
        ->whereIn('key', ['credit_card_autopay_completed', 'recurring_weekly_due_summary', 'recurring_monthly_due_summary'])
        ->update([
            'is_active' => false,
        ]);

    CommunicationCategory::query()
        ->whereIn('key', ['credit_cards.autopay_completed', 'recurring.weekly_due_summary', 'recurring.monthly_due_summary'])
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
