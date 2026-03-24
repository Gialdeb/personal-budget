<?php

use App\Enums\NotificationAudienceEnum;
use App\Models\NotificationTopic;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\AutomationFailedNotification;
use App\Notifications\ImportCompletedNotification;
use App\Services\Communication\CommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('dispatches user notification for import completed', function () {
    Notification::fake();

    $user = User::factory()->create();

    NotificationTopic::query()->create([
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
    ]);

    app(CommunicationService::class)->send(
        topicKey: 'import_completed',
        payload: ['import_uuid' => 'abc-123'],
        target: $user,
    );

    Notification::assertSentTo($user, ImportCompletedNotification::class);
});

it('dispatches admin notification only to admins', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $user = User::factory()->create();

    if (class_exists(Role::class) && method_exists($admin, 'assignRole')) {
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
    }

    NotificationTopic::query()->create([
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
    ]);

    app(CommunicationService::class)->send(
        topicKey: 'automation_failed',
        payload: ['pipeline' => 'recurring_pipeline', 'message' => 'Boom'],
    );

    Notification::assertSentTo($admin, AutomationFailedNotification::class);
    Notification::assertNotSentTo($user, AutomationFailedNotification::class);
});

it('does not dispatch when user disabled all supported channels', function () {
    Notification::fake();

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
        'is_active' => true,
    ]);

    UserNotificationPreference::query()->create([
        'user_id' => $user->id,
        'notification_topic_id' => $topic->id,
        'email_enabled' => false,
        'in_app_enabled' => false,
        'sms_enabled' => false,
    ]);

    app(CommunicationService::class)->send(
        topicKey: 'monthly_report_ready',
        payload: ['period' => '2026-03'],
        target: $user,
    );

    Notification::assertNothingSent();
});
