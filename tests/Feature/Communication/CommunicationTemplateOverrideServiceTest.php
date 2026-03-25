<?php

use App\Models\CommunicationTemplate;
use App\Services\Communication\CommunicationTemplateOverrideService;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
});

it('creates a global override for a customizable template', function () {
    $template = CommunicationTemplate::query()->where('key', 'import_completed_mail')->firstOrFail();

    $override = app(CommunicationTemplateOverrideService::class)->upsertGlobalOverride($template, [
        'subject_template' => 'custom.import.subject',
        'title_template' => 'custom.import.title',
        'body_template' => 'custom.import.body',
        'cta_label_template' => 'custom.import.cta',
        'cta_url_template' => '/imports/{import_uuid}',
        'is_active' => true,
    ]);

    expect($override->communication_template_id)->toBe($template->id)
        ->and($override->subject_template)->toBe('custom.import.subject')
        ->and($override->is_active)->toBeTrue();
});

it('updates the existing global override instead of creating duplicates', function () {
    $template = CommunicationTemplate::query()->where('key', 'monthly_report_ready_mail')->firstOrFail();

    $service = app(CommunicationTemplateOverrideService::class);

    $first = $service->upsertGlobalOverride($template, [
        'subject_template' => 'first.subject',
        'body_template' => 'first.body',
    ]);

    $second = $service->upsertGlobalOverride($template, [
        'subject_template' => 'second.subject',
        'body_template' => 'second.body',
    ]);

    expect($first->id)->toBe($second->id)
        ->and($second->subject_template)->toBe('second.subject')
        ->and($template->overrides()->count())->toBe(1);
});

it('can disable an active global override', function () {
    $template = CommunicationTemplate::query()->where('key', 'monthly_report_ready_mail')->firstOrFail();

    $service = app(CommunicationTemplateOverrideService::class);

    $service->upsertGlobalOverride($template, [
        'subject_template' => 'custom.monthly.subject',
        'body_template' => 'custom.monthly.body',
        'is_active' => true,
    ]);

    $disabled = $service->disableGlobalOverride($template);

    expect($disabled)->not->toBeNull()
        ->and($disabled->is_active)->toBeFalse()
        ->and($service->getActiveGlobalOverride($template))->toBeNull();
});

it('returns null when disabling a missing global override', function () {
    $template = CommunicationTemplate::query()->where('key', 'admin_freeform_mail')->firstOrFail();

    $disabled = app(CommunicationTemplateOverrideService::class)->disableGlobalOverride($template);

    expect($disabled)->toBeNull();
});

it('throws when trying to override a system locked template', function () {
    $template = CommunicationTemplate::query()->where('key', 'auth_verify_email_mail')->firstOrFail();

    expect(fn () => app(CommunicationTemplateOverrideService::class)->upsertGlobalOverride($template, [
        'subject_template' => 'custom.auth.subject',
    ]))->toThrow(InvalidArgumentException::class);
});

it('returns the active global override when present', function () {
    $template = CommunicationTemplate::query()->where('key', 'import_completed_mail')->firstOrFail();

    $service = app(CommunicationTemplateOverrideService::class);

    $created = $service->upsertGlobalOverride($template, [
        'subject_template' => 'custom.import.subject',
        'body_template' => 'custom.import.body',
        'is_active' => true,
    ]);

    $resolved = $service->getActiveGlobalOverride($template);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($created->id);
});
