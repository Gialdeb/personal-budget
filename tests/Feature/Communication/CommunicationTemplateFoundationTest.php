<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationTemplateModeEnum;
use App\Models\CommunicationTemplate;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
});

it('creates communication templates table with expected columns', function () {
    expect(Schema::hasTable('communication_templates'))->toBeTrue();

    foreach ([
        'id',
        'uuid',
        'key',
        'notification_topic_id',
        'channel',
        'template_mode',
        'name',
        'description',
        'subject_template',
        'title_template',
        'body_template',
        'cta_label_template',
        'cta_url_template',
        'is_system_locked',
        'is_active',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('communication_templates', $column))->toBeTrue();
    }
});

it('casts channel and template mode correctly', function () {
    $template = CommunicationTemplate::query()->where('key', 'import_completed_mail')->firstOrFail();

    expect($template->channel)->toBe(CommunicationChannelEnum::MAIL)
        ->and($template->template_mode)->toBe(CommunicationTemplateModeEnum::CUSTOMIZABLE)
        ->and($template->is_system_locked)->toBeFalse();
});

it('seeds system and customizable templates', function () {
    expect(CommunicationTemplate::query()->where('key', 'automation_failed_mail')->exists())->toBeTrue()
        ->and(CommunicationTemplate::query()->where('key', 'credit_card_autopay_completed_mail')->exists())->toBeTrue()
        ->and(CommunicationTemplate::query()->where('key', 'credit_card_autopay_completed_database')->exists())->toBeTrue()
        ->and(CommunicationTemplate::query()->where('key', 'import_completed_mail')->exists())->toBeTrue()
        ->and(CommunicationTemplate::query()->where('key', 'monthly_report_ready_mail')->exists())->toBeTrue()
        ->and(CommunicationTemplate::query()->where('key', 'auth_verify_email_mail')->exists())->toBeTrue()
        ->and(CommunicationTemplate::query()->where('key', 'auth_reset_password_mail')->exists())->toBeTrue()
        ->and(CommunicationTemplate::query()->where('key', 'admin_freeform_mail')->exists())->toBeTrue();
});

it('links template to notification topic when available', function () {
    $template = CommunicationTemplate::query()->where('key', 'import_completed_mail')->firstOrFail();

    expect($template->notificationTopic)->not->toBeNull()
        ->and($template->notificationTopic->key)->toBe('import_completed');
});

it('allows freeform templates without notification topic', function () {
    $template = CommunicationTemplate::query()->where('key', 'admin_freeform_mail')->firstOrFail();

    expect($template->notificationTopic)->toBeNull()
        ->and($template->template_mode)->toBe(CommunicationTemplateModeEnum::FREEFORM);
});

it('seeds welcome mail template with localized base keys instead of legacy raw english text', function () {
    $template = CommunicationTemplate::query()->where('key', 'welcome_after_verification_mail')->firstOrFail();

    expect($template->subject_template)->toBe('notifications.topics.welcome_after_verification.subject')
        ->and($template->title_template)->toBe('notifications.topics.welcome_after_verification.title')
        ->and($template->body_template)->toBe('notifications.topics.welcome_after_verification.message')
        ->and($template->cta_label_template)->toBe('notifications.topics.welcome_after_verification.cta')
        ->and($template->cta_url_template)->toBe('/dashboard');
});

it('backfills legacy welcome mail template records when the seeder is re-run', function () {
    $template = CommunicationTemplate::query()->where('key', 'welcome_after_verification_mail')->firstOrFail();

    $template->update([
        'subject_template' => 'Welcome',
        'title_template' => 'Welcome',
        'body_template' => 'Welcome {user.full_name}, your account is now active.',
        'cta_label_template' => 'Open dashboard',
        'cta_url_template' => '/dashboard',
    ]);

    $this->seed(CommunicationTemplateSeeder::class);

    expect($template->fresh()->subject_template)->toBe('notifications.topics.welcome_after_verification.subject')
        ->and($template->fresh()->title_template)->toBe('notifications.topics.welcome_after_verification.title')
        ->and($template->fresh()->body_template)->toBe('notifications.topics.welcome_after_verification.message')
        ->and($template->fresh()->cta_label_template)->toBe('notifications.topics.welcome_after_verification.cta');
});
