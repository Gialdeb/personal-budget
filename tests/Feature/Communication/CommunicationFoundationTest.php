<?php

use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationPreferenceModeEnum;
use App\Models\NotificationTopic;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Communication\CommunicationService;
use App\Services\Communication\NotificationPreferenceResolver;
use App\Services\Communication\NotificationRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('resolves default channels from topic settings', function () {
    $user = User::factory()->create();

    $topic = NotificationTopic::query()->create([
        'key' => 'import_completed',
        'name' => 'Import completed',
        'audience' => NotificationAudienceEnum::USER,
        'supports_email' => true,
        'supports_in_app' => true,
        'supports_sms' => false,
        'default_email_enabled' => false,
        'default_in_app_enabled' => true,
        'default_sms_enabled' => false,
        'is_user_configurable' => true,
        'is_active' => true,
        'preference_mode' => NotificationPreferenceModeEnum::USER_CONFIGURABLE,
    ]);

    $channels = app(NotificationPreferenceResolver::class)->resolveChannels($user, $topic);

    expect(collect($channels)->map->value->all())->toBe(['database']);
});

it('resolves user-specific channel preferences over topic defaults', function () {
    $user = User::factory()->create();

    $topic = NotificationTopic::query()->create([
        'key' => 'monthly_report_ready',
        'name' => 'Monthly report ready',
        'audience' => NotificationAudienceEnum::USER,
        'supports_email' => true,
        'supports_in_app' => true,
        'supports_sms' => false,
        'default_email_enabled' => true,
        'default_in_app_enabled' => true,
        'default_sms_enabled' => false,
        'is_user_configurable' => true,
        'preference_mode' => NotificationPreferenceModeEnum::ADMIN_CONFIGURABLE,
        'is_active' => true,
    ]);

    UserNotificationPreference::query()->create([
        'user_id' => $user->id,
        'notification_topic_id' => $topic->id,
        'email_enabled' => false,
        'in_app_enabled' => true,
        'sms_enabled' => false,
    ]);

    $channels = app(NotificationPreferenceResolver::class)->resolveChannels($user, $topic);

    expect(collect($channels)->map->value->all())->toBe(['database']);
});

it('resolves admin recipients for admin audience topics', function () {
    $admin = User::factory()->create();

    if (class_exists(Role::class) && method_exists($admin, 'assignRole')) {
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
    }

    $normalUser = User::factory()->create();

    $topic = NotificationTopic::query()->create([
        'key' => 'automation_failed',
        'name' => 'Automation failed',
        'audience' => NotificationAudienceEnum::ADMIN,
        'supports_email' => true,
        'supports_in_app' => true,
        'supports_sms' => false,
        'default_email_enabled' => true,
        'default_in_app_enabled' => true,
        'default_sms_enabled' => false,
        'is_user_configurable' => true,
        'is_active' => true,
        'preference_mode' => NotificationPreferenceModeEnum::USER_CONFIGURABLE,
    ]);

    $recipients = app(NotificationRecipientResolver::class)->resolveRecipients($topic);

    expect($recipients->pluck('id')->contains($admin->id))->toBeTrue()
        ->and($recipients->pluck('id')->contains($normalUser->id))->toBeFalse();
});

it('prepares a communication plan for user topics', function () {
    $user = User::factory()->create();

    $topic = NotificationTopic::query()->create([
        'key' => 'import_completed',
        'name' => 'Import completed',
        'audience' => NotificationAudienceEnum::USER,
        'supports_email' => true,
        'supports_in_app' => true,
        'supports_sms' => false,
        'default_email_enabled' => false,
        'default_in_app_enabled' => true,
        'default_sms_enabled' => false,
        'is_user_configurable' => true,
        'is_active' => true,
        'preference_mode' => NotificationPreferenceModeEnum::USER_CONFIGURABLE,
    ]);

    $plan = app(CommunicationService::class)->prepare(
        topicKey: $topic->key,
        payload: ['import_uuid' => 'abc-123'],
        target: $user,
    );

    expect($plan)->toHaveCount(1)
        ->and($plan->first()['topic'])->toBe('import_completed')
        ->and($plan->first()['channels'])->toBe(['database'])
        ->and($plan->first()['payload'])->toBe(['import_uuid' => 'abc-123']);
});

it('skips recipients when no channel is enabled', function () {
    $user = User::factory()->create();

    $topic = NotificationTopic::query()->create([
        'key' => 'silent_topic',
        'name' => 'Silent topic',
        'audience' => NotificationAudienceEnum::USER,
        'supports_email' => true,
        'supports_in_app' => true,
        'supports_sms' => false,
        'default_email_enabled' => false,
        'default_in_app_enabled' => false,
        'default_sms_enabled' => false,
        'is_user_configurable' => true,
        'is_active' => true,
        'preference_mode' => NotificationPreferenceModeEnum::USER_CONFIGURABLE,
    ]);

    $plan = app(CommunicationService::class)->prepare(
        topicKey: $topic->key,
        payload: [],
        target: $user,
    );

    expect($plan)->toHaveCount(0);
});

it('resolves mandatory channels without requiring user preferences', function () {
    $user = User::factory()->create();

    $topic = NotificationTopic::query()->create([
        'key' => 'auth_verify_email',
        'name' => 'Verify email',
        'audience' => NotificationAudienceEnum::USER,
        'supports_email' => true,
        'supports_in_app' => false,
        'supports_sms' => false,
        'default_email_enabled' => true,
        'default_in_app_enabled' => false,
        'default_sms_enabled' => false,
        'preference_mode' => NotificationPreferenceModeEnum::MANDATORY,
        'is_user_configurable' => false,
        'is_active' => true,
    ]);

    $channels = app(NotificationPreferenceResolver::class)
        ->resolveChannels($user, $topic);

    expect(collect($channels)->map->value->all())->toBe(['mail']);
});
