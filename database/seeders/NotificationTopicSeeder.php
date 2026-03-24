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
        ];

        foreach ($topics as $topic) {
            NotificationTopic::query()->updateOrCreate(
                ['key' => $topic['key']],
                $topic
            );
        }
    }
}
