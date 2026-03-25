<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationTemplateOverrideScopeEnum;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateOverride;
use App\Services\Communication\CommunicationTemplateResolver;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
});

it('creates communication template overrides table with expected columns', function () {
    expect(Schema::hasTable('communication_template_overrides'))->toBeTrue();

    foreach ([
        'id',
        'uuid',
        'communication_template_id',
        'scope',
        'scope_key',
        'subject_template',
        'title_template',
        'body_template',
        'cta_label_template',
        'cta_url_template',
        'is_active',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('communication_template_overrides', $column))->toBeTrue();
    }
});

it('links override to communication template', function () {
    $template = CommunicationTemplate::query()->where('key', 'import_completed_mail')->firstOrFail();

    $override = CommunicationTemplateOverride::query()->create([
        'communication_template_id' => $template->id,
        'scope' => CommunicationTemplateOverrideScopeEnum::GLOBAL,
        'subject_template' => 'custom.subject',
        'is_active' => true,
    ]);

    expect($override->communicationTemplate->is($template))->toBeTrue();
});

it('resolves base template values when no override exists', function () {
    $resolved = app(CommunicationTemplateResolver::class)->resolveForTopic(
        'import_completed',
        CommunicationChannelEnum::MAIL,
    );

    expect($resolved['template'])->toBeInstanceOf(CommunicationTemplate::class)
        ->and($resolved['override'])->toBeNull()
        ->and($resolved['subject_template'])->toBe('notifications.topics.import_completed.subject')
        ->and($resolved['title_template'])->toBe('notifications.topics.import_completed.title');
});

it('prefers active global override values over base template values', function () {
    $template = CommunicationTemplate::query()->where('key', 'import_completed_mail')->firstOrFail();

    CommunicationTemplateOverride::query()->create([
        'communication_template_id' => $template->id,
        'scope' => CommunicationTemplateOverrideScopeEnum::GLOBAL,
        'subject_template' => 'custom.import_completed.subject',
        'title_template' => 'custom.import_completed.title',
        'body_template' => 'custom.import_completed.body',
        'cta_label_template' => 'custom.import_completed.cta',
        'is_active' => true,
    ]);

    $resolved = app(CommunicationTemplateResolver::class)->resolveForTopic(
        'import_completed',
        CommunicationChannelEnum::MAIL,
    );

    expect($resolved['subject_template'])->toBe('custom.import_completed.subject')
        ->and($resolved['title_template'])->toBe('custom.import_completed.title')
        ->and($resolved['body_template'])->toBe('custom.import_completed.body')
        ->and($resolved['cta_label_template'])->toBe('custom.import_completed.cta')
        ->and($resolved['override'])->not->toBeNull();
});

it('ignores inactive overrides', function () {
    $template = CommunicationTemplate::query()->where('key', 'monthly_report_ready_mail')->firstOrFail();

    CommunicationTemplateOverride::query()->create([
        'communication_template_id' => $template->id,
        'scope' => CommunicationTemplateOverrideScopeEnum::GLOBAL,
        'subject_template' => 'custom.monthly.subject',
        'is_active' => false,
    ]);

    $resolved = app(CommunicationTemplateResolver::class)->resolveForTopic(
        'monthly_report_ready',
        CommunicationChannelEnum::MAIL,
    );

    expect($resolved['override'])->toBeNull()
        ->and($resolved['subject_template'])->toBe('notifications.topics.monthly_report_ready.subject');
});

it('can resolve a freeform template by template key', function () {
    $resolved = app(CommunicationTemplateResolver::class)->resolveByTemplateKey('admin_freeform_mail');

    expect($resolved['template']->key)->toBe('admin_freeform_mail')
        ->and($resolved['template']->notificationTopic)->toBeNull();
});

it('ignores active overrides for system locked welcome templates', function () {
    $template = CommunicationTemplate::query()->where('key', 'welcome_after_verification_mail')->firstOrFail();

    CommunicationTemplateOverride::query()->create([
        'communication_template_id' => $template->id,
        'scope' => CommunicationTemplateOverrideScopeEnum::GLOBAL,
        'subject_template' => 'Welcome',
        'title_template' => 'Welcome',
        'body_template' => 'Welcome {user.full_name}, your account is now active.',
        'cta_label_template' => 'Open dashboard',
        'cta_url_template' => '/dashboard',
        'is_active' => true,
    ]);

    $resolved = app(CommunicationTemplateResolver::class)->resolveByTemplateKey('welcome_after_verification_mail');

    expect($resolved['override'])->toBeNull()
        ->and($resolved['subject_template'])->toBe('notifications.topics.welcome_after_verification.subject')
        ->and($resolved['title_template'])->toBe('notifications.topics.welcome_after_verification.title')
        ->and($resolved['body_template'])->toBe('notifications.topics.welcome_after_verification.message')
        ->and($resolved['cta_label_template'])->toBe('notifications.topics.welcome_after_verification.cta');
});
