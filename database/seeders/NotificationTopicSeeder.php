<?php

namespace Database\Seeders;

use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationPreferenceModeEnum;
use App\Models\NotificationTopic;
use Illuminate\Database\Seeder;

class NotificationTopicSeeder extends Seeder
{
    public function run(): void
    {
        $topics = [
            [
                'key' => 'automation_failed',
                'name' => 'Automation failed',
                'description' => 'Alert when a critical automation pipeline fails.',
                'audience' => NotificationAudienceEnum::ADMIN,
                'supports_email' => true,
                'supports_in_app' => true,
                'supports_sms' => false,
                'default_email_enabled' => true,
                'default_in_app_enabled' => true,
                'default_sms_enabled' => false,
                'is_user_configurable' => true,
                'is_active' => true,
                'preference_mode' => NotificationPreferenceModeEnum::ADMIN_CONFIGURABLE,
            ],
            [
                'key' => 'import_completed',
                'name' => 'Import completed',
                'description' => 'Notify when an import completes successfully.',
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
            ],
            [
                'key' => 'monthly_report_ready',
                'name' => 'Monthly report ready',
                'description' => 'Notify when a monthly report is available.',
                'audience' => NotificationAudienceEnum::USER,
                'supports_email' => true,
                'supports_in_app' => true,
                'supports_sms' => false,
                'default_email_enabled' => true,
                'default_in_app_enabled' => true,
                'default_sms_enabled' => false,
                'is_user_configurable' => true,
                'is_active' => true,
                'preference_mode' => NotificationPreferenceModeEnum::USER_CONFIGURABLE,
            ],
            [
                'key' => 'auth_verify_email',
                'name' => 'Verify email',
                'description' => 'Mandatory email verification notification.',
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
            ],
            [
                'key' => 'auth_reset_password',
                'name' => 'Reset password',
                'description' => 'Mandatory password reset notification.',
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
            ],
        ];

        foreach ($topics as $topic) {
            NotificationTopic::query()->updateOrCreate(
                ['key' => $topic['key']],
                $topic
            );
        }
    }
}
