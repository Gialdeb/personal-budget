<?php

use App\Enums\NotificationAudienceEnum;
use App\Models\NotificationTopic;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates notification topics table with expected columns', function () {
    expect(Schema::hasTable('notification_topics'))->toBeTrue();

    foreach ([
        'id',
        'uuid',
        'key',
        'name',
        'description',
        'audience',
        'supports_email',
        'supports_in_app',
        'supports_sms',
        'default_email_enabled',
        'default_in_app_enabled',
        'default_sms_enabled',
        'is_user_configurable',
        'is_active',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('notification_topics', $column))->toBeTrue();
    }
});

it('creates user notification preferences table with expected columns', function () {
    expect(Schema::hasTable('user_notification_preferences'))->toBeTrue();

    foreach ([
        'id',
        'uuid',
        'user_id',
        'notification_topic_id',
        'email_enabled',
        'in_app_enabled',
        'sms_enabled',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('user_notification_preferences', $column))->toBeTrue();
    }
});

it('casts notification topic audience correctly', function () {
    $topic = NotificationTopic::query()->create([
        'key' => 'test_topic',
        'name' => 'Test topic',
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

    expect($topic->audience)->toBe(NotificationAudienceEnum::USER)
        ->and($topic->supports_email)->toBeTrue()
        ->and($topic->supports_in_app)->toBeTrue()
        ->and($topic->supports_sms)->toBeFalse();
});

it('links user preferences to topics', function () {
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
    ]);

    $preference = UserNotificationPreference::query()->create([
        'user_id' => $user->id,
        'notification_topic_id' => $topic->id,
        'email_enabled' => true,
        'in_app_enabled' => false,
        'sms_enabled' => false,
    ]);

    expect($preference->user->is($user))->toBeTrue()
        ->and($preference->topic->is($topic))->toBeTrue();
});

it('seeds default notification topics', function () {
    $this->seed(NotificationTopicSeeder::class);

    expect(NotificationTopic::query()->where('key', 'automation_failed')->exists())->toBeTrue()
        ->and(NotificationTopic::query()->where('key', 'import_completed')->exists())->toBeTrue()
        ->and(NotificationTopic::query()->where('key', 'monthly_report_ready')->exists())->toBeTrue();
});
