<?php

namespace Database\Seeders;

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationDeliveryModeEnum;
use App\Enums\NotificationAudienceEnum;
use App\Enums\NotificationPreferenceModeEnum;
use App\Models\CommunicationCategory;
use App\Models\CommunicationCategoryChannelTemplate;
use App\Models\CommunicationTemplate;
use Illuminate\Database\Seeder;

class CommunicationCategorySeeder extends Seeder
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function defaultChannelTemplateMap(): array
    {
        return [
            'credit_cards.autopay_completed' => [
                CommunicationChannelEnum::MAIL->value => 'credit_card_autopay_completed_mail',
                CommunicationChannelEnum::DATABASE->value => 'credit_card_autopay_completed_database',
            ],
            'auth.verify_email' => [
                CommunicationChannelEnum::MAIL->value => 'auth_verify_email_mail',
            ],
            'auth.reset_password' => [
                CommunicationChannelEnum::MAIL->value => 'auth_reset_password_mail',
            ],
            'reports.weekly_ready' => [
                CommunicationChannelEnum::MAIL->value => 'monthly_report_ready_mail',
            ],
            'user.welcome_after_verification' => [
                CommunicationChannelEnum::MAIL->value => 'welcome_after_verification_mail',
                CommunicationChannelEnum::DATABASE->value => 'welcome_after_verification_database',
            ],
            'imports.completed' => [
                CommunicationChannelEnum::MAIL->value => 'import_completed_mail',
                CommunicationChannelEnum::DATABASE->value => 'import_completed_database',
            ],
            'sharing.account_invitation' => [
                CommunicationChannelEnum::MAIL->value => 'account_invitation_mail',
            ],
        ];
    }

    public function run(): void
    {
        $categories = [
            [
                'key' => 'credit_cards.autopay_completed',
                'name' => 'Addebito carta eseguito',
                'description' => 'Comunicazione inviata quando il ciclo della carta viene addebitato automaticamente.',
                'audience' => NotificationAudienceEnum::USER,
                'delivery_mode' => CommunicationDeliveryModeEnum::TRANSACTIONAL,
                'preference_mode' => NotificationPreferenceModeEnum::USER_CONFIGURABLE,
                'context_type' => 'user',
                'is_active' => true,
            ],
            [
                'key' => 'auth.verify_email',
                'name' => 'Verify email',
                'description' => 'Email verification communication.',
                'audience' => NotificationAudienceEnum::USER,
                'delivery_mode' => CommunicationDeliveryModeEnum::SYSTEM,
                'preference_mode' => NotificationPreferenceModeEnum::MANDATORY,
                'context_type' => 'user',
                'is_active' => true,
            ],
            [
                'key' => 'auth.reset_password',
                'name' => 'Reset password',
                'description' => 'Password reset communication.',
                'audience' => NotificationAudienceEnum::USER,
                'delivery_mode' => CommunicationDeliveryModeEnum::SYSTEM,
                'preference_mode' => NotificationPreferenceModeEnum::MANDATORY,
                'context_type' => 'user',
                'is_active' => true,
            ],
            [
                'key' => 'user.welcome_after_verification',
                'name' => 'Welcome after verification',
                'description' => 'Welcome communication sent after email verification.',
                'audience' => NotificationAudienceEnum::USER,
                'delivery_mode' => CommunicationDeliveryModeEnum::TRANSACTIONAL,
                'preference_mode' => NotificationPreferenceModeEnum::MANDATORY,
                'context_type' => 'user',
                'is_active' => true,
            ],
            [
                'key' => 'imports.completed',
                'name' => 'Import completed',
                'description' => 'Communication sent after a completed import.',
                'audience' => NotificationAudienceEnum::USER,
                'delivery_mode' => CommunicationDeliveryModeEnum::TRANSACTIONAL,
                'preference_mode' => NotificationPreferenceModeEnum::USER_CONFIGURABLE,
                'context_type' => 'import',
                'is_active' => true,
            ],
            [
                'key' => 'reports.weekly_ready',
                'name' => 'Weekly report ready',
                'description' => 'Communication sent when the weekly report is available.',
                'audience' => NotificationAudienceEnum::USER,
                'delivery_mode' => CommunicationDeliveryModeEnum::TRANSACTIONAL,
                'preference_mode' => NotificationPreferenceModeEnum::USER_CONFIGURABLE,
                'context_type' => 'user',
                'is_active' => true,
            ],
            [
                'key' => 'sharing.account_invitation',
                'name' => 'Invito condivisione conto',
                'description' => 'Email inviata per invitare un utente a condividere un conto.',
                'audience' => NotificationAudienceEnum::USER,
                'delivery_mode' => CommunicationDeliveryModeEnum::TRANSACTIONAL,
                'preference_mode' => NotificationPreferenceModeEnum::MANDATORY,
                'context_type' => 'account_invitation',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $data) {
            CommunicationCategory::query()->updateOrCreate(
                ['key' => $data['key']],
                $data
            );
        }

        $this->seedDefaultChannelTemplates();
    }

    protected function seedDefaultChannelTemplates(): void
    {
        foreach (self::defaultChannelTemplateMap() as $categoryKey => $channels) {
            $category = CommunicationCategory::query()->where('key', $categoryKey)->first();

            if (! $category) {
                continue;
            }

            foreach ($channels as $channel => $templateKey) {
                $template = CommunicationTemplate::query()->where('key', $templateKey)->first();

                if (! $template) {
                    continue;
                }

                CommunicationCategoryChannelTemplate::query()->updateOrCreate(
                    [
                        'communication_category_id' => $category->id,
                        'communication_template_id' => $template->id,
                        'channel' => $channel,
                    ],
                    [
                        'is_default' => true,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
