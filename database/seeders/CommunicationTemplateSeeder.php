<?php

namespace Database\Seeders;

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationTemplateModeEnum;
use App\Models\CommunicationCategory;
use App\Models\CommunicationCategoryChannelTemplate;
use App\Models\CommunicationTemplate;
use App\Models\NotificationTopic;
use Illuminate\Database\Seeder;

class CommunicationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $automationFailed = NotificationTopic::query()->where('key', 'automation_failed')->first();
        $creditCardAutopayCompleted = NotificationTopic::query()->where('key', 'credit_card_autopay_completed')->first();
        $importCompleted = NotificationTopic::query()->where('key', 'import_completed')->first();
        $monthlyReportReady = NotificationTopic::query()->where('key', 'monthly_report_ready')->first();
        $authVerifyEmail = NotificationTopic::query()->where('key', 'auth_verify_email')->first();
        $authResetPassword = NotificationTopic::query()->where('key', 'auth_reset_password')->first();

        $templates = [
            [
                'key' => 'credit_card_autopay_completed_mail',
                'notification_topic_id' => $creditCardAutopayCompleted?->id,
                'channel' => CommunicationChannelEnum::MAIL,
                'template_mode' => CommunicationTemplateModeEnum::SYSTEM,
                'name' => 'Credit card autopay completed email',
                'description' => 'System email template for completed credit card autopay charges.',
                'subject_template' => 'notifications.topics.credit_card_autopay_completed.subject',
                'title_template' => 'notifications.topics.credit_card_autopay_completed.title',
                'body_template' => 'notifications.topics.credit_card_autopay_completed.message',
                'cta_label_template' => 'notifications.topics.credit_card_autopay_completed.cta',
                'cta_url_template' => null,
                'is_system_locked' => true,
                'is_active' => true,
            ],
            [
                'key' => 'automation_failed_mail',
                'notification_topic_id' => $automationFailed?->id,
                'channel' => CommunicationChannelEnum::MAIL,
                'template_mode' => CommunicationTemplateModeEnum::SYSTEM,
                'name' => 'Automation failed email',
                'description' => 'System email template for automation failure alerts.',
                'subject_template' => 'notifications.topics.automation_failed.subject',
                'title_template' => 'notifications.topics.automation_failed.title',
                'body_template' => 'notifications.topics.automation_failed.message',
                'cta_label_template' => 'notifications.topics.automation_failed.cta',
                'cta_url_template' => null,
                'is_system_locked' => true,
                'is_active' => true,
            ],
            [
                'key' => 'import_completed_mail',
                'notification_topic_id' => $importCompleted?->id,
                'channel' => CommunicationChannelEnum::MAIL,
                'template_mode' => CommunicationTemplateModeEnum::CUSTOMIZABLE,
                'name' => 'Import completed email',
                'description' => 'Customizable email template for import completed.',
                'subject_template' => 'notifications.topics.import_completed.subject',
                'title_template' => 'notifications.topics.import_completed.title',
                'body_template' => 'notifications.topics.import_completed.message',
                'cta_label_template' => 'notifications.topics.import_completed.cta',
                'cta_url_template' => null,
                'is_system_locked' => false,
                'is_active' => true,
            ],
            [
                'key' => 'monthly_report_ready_mail',
                'notification_topic_id' => $monthlyReportReady?->id,
                'channel' => CommunicationChannelEnum::MAIL,
                'template_mode' => CommunicationTemplateModeEnum::CUSTOMIZABLE,
                'name' => 'Monthly report ready email',
                'description' => 'Customizable email template for monthly report availability.',
                'subject_template' => 'notifications.topics.monthly_report_ready.subject',
                'title_template' => 'notifications.topics.monthly_report_ready.title',
                'body_template' => 'notifications.topics.monthly_report_ready.message',
                'cta_label_template' => 'notifications.topics.monthly_report_ready.cta',
                'cta_url_template' => null,
                'is_system_locked' => false,
                'is_active' => true,
            ],
            [
                'key' => 'auth_verify_email_mail',
                'notification_topic_id' => $authVerifyEmail?->id,
                'channel' => CommunicationChannelEnum::MAIL,
                'template_mode' => CommunicationTemplateModeEnum::SYSTEM,
                'name' => 'Verify email',
                'description' => 'Mandatory system template for email verification.',
                'subject_template' => 'notifications.topics.auth_verify_email.subject',
                'title_template' => 'notifications.topics.auth_verify_email.title',
                'body_template' => 'notifications.topics.auth_verify_email.message',
                'cta_label_template' => 'notifications.topics.auth_verify_email.cta',
                'cta_url_template' => null,
                'is_system_locked' => true,
                'is_active' => true,
            ],
            [
                'key' => 'auth_reset_password_mail',
                'notification_topic_id' => $authResetPassword?->id,
                'channel' => CommunicationChannelEnum::MAIL,
                'template_mode' => CommunicationTemplateModeEnum::SYSTEM,
                'name' => 'Reset password',
                'description' => 'Mandatory system template for password reset.',
                'subject_template' => 'notifications.topics.auth_reset_password.subject',
                'title_template' => 'notifications.topics.auth_reset_password.title',
                'body_template' => 'notifications.topics.auth_reset_password.message',
                'cta_label_template' => 'notifications.topics.auth_reset_password.cta',
                'cta_url_template' => null,
                'is_system_locked' => true,
                'is_active' => true,
            ],
            [
                'key' => 'admin_freeform_mail',
                'notification_topic_id' => null,
                'channel' => CommunicationChannelEnum::MAIL,
                'template_mode' => CommunicationTemplateModeEnum::FREEFORM,
                'name' => 'Admin freeform email',
                'description' => 'Template base for future admin custom emails.',
                'subject_template' => null,
                'title_template' => null,
                'body_template' => '',
                'cta_label_template' => null,
                'cta_url_template' => null,
                'is_system_locked' => false,
                'is_active' => true,
            ],
            [
                'key' => 'welcome_after_verification_mail',
                'notification_topic_id' => null,
                'channel' => CommunicationChannelEnum::MAIL,
                'template_mode' => CommunicationTemplateModeEnum::SYSTEM,
                'name' => 'Welcome after verification email',
                'description' => 'System email template sent after email verification.',
                'subject_template' => 'notifications.topics.welcome_after_verification.subject',
                'title_template' => 'notifications.topics.welcome_after_verification.title',
                'body_template' => 'notifications.topics.welcome_after_verification.message',
                'cta_label_template' => 'notifications.topics.welcome_after_verification.cta',
                'cta_url_template' => '/dashboard',
                'is_system_locked' => true,
                'is_active' => true,
            ],
            [
                'key' => 'import_completed_database',
                'notification_topic_id' => null,
                'channel' => CommunicationChannelEnum::DATABASE,
                'template_mode' => CommunicationTemplateModeEnum::SYSTEM,
                'name' => 'Import completed in-app notification',
                'description' => 'In-app notification shown when an import completes.',
                'subject_template' => null,
                'title_template' => 'Import completato',
                'body_template' => 'Il tuo import {import.filename} è stato completato con successo.',
                'cta_label_template' => 'Apri import',
                'cta_url_template' => '/imports/{import.uuid}',
                'is_system_locked' => true,
                'is_active' => true,
            ],
            [
                'key' => 'credit_card_autopay_completed_database',
                'notification_topic_id' => $creditCardAutopayCompleted?->id,
                'channel' => CommunicationChannelEnum::DATABASE,
                'template_mode' => CommunicationTemplateModeEnum::SYSTEM,
                'name' => 'Credit card autopay completed in-app notification',
                'description' => 'In-app notification shown when a credit card cycle charge is completed.',
                'subject_template' => null,
                'title_template' => 'notifications.topics.credit_card_autopay_completed.title',
                'body_template' => 'notifications.topics.credit_card_autopay_completed.message',
                'cta_label_template' => 'notifications.topics.credit_card_autopay_completed.cta',
                'cta_url_template' => null,
                'is_system_locked' => true,
                'is_active' => true,
            ],
            [
                'key' => 'welcome_after_verification_database',
                'notification_topic_id' => null,
                'channel' => CommunicationChannelEnum::DATABASE,
                'template_mode' => CommunicationTemplateModeEnum::SYSTEM,
                'name' => 'Welcome after verification in-app notification',
                'description' => 'In-app welcome notification after email verification.',
                'subject_template' => null,
                'title_template' => 'notifications.topics.welcome_after_verification.title',
                'body_template' => 'notifications.topics.welcome_after_verification.message',
                'cta_label_template' => 'notifications.topics.welcome_after_verification.cta',
                'cta_url_template' => '/dashboard',
                'is_system_locked' => true,
                'is_active' => true,
            ],
            [
                'key' => 'account_invitation_mail',
                'notification_topic_id' => null,
                'channel' => CommunicationChannelEnum::MAIL,
                'template_mode' => CommunicationTemplateModeEnum::SYSTEM,
                'name' => 'Email invito condivisione conto',
                'description' => 'Template email di sistema inviato quando una persona viene invitata a condividere un conto.',
                'subject_template' => 'notifications.topics.account_invitation.subject',
                'title_template' => 'notifications.topics.account_invitation.title',
                'body_template' => 'notifications.topics.account_invitation.message',
                'cta_label_template' => 'notifications.topics.account_invitation.cta',
                'cta_url_template' => '{invitation_accept_url}',
                'is_system_locked' => true,
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            $this->syncTemplate($template);
        }

        $this->syncDefaultCategoryChannelTemplates();
    }

    /**
     * @param  array<string, mixed>  $template
     */
    protected function syncTemplate(array $template): void
    {
        CommunicationTemplate::query()->updateOrCreate(
            ['key' => $template['key']],
            $template
        );
    }

    protected function syncDefaultCategoryChannelTemplates(): void
    {
        foreach (CommunicationCategorySeeder::defaultChannelTemplateMap() as $categoryKey => $channels) {
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
