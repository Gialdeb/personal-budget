<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationDeliveryModeEnum;
use App\Enums\NotificationPreferenceModeEnum;
use App\Models\CommunicationCategory;
use App\Models\CommunicationCategoryChannelTemplate;
use App\Models\CommunicationTemplate;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

it('creates communication categories table with expected columns', function () {
    expect(Schema::hasTable('communication_categories'))->toBeTrue();

    foreach ([
        'id',
        'uuid',
        'key',
        'name',
        'description',
        'audience',
        'delivery_mode',
        'preference_mode',
        'context_type',
        'is_active',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('communication_categories', $column))->toBeTrue();
    }
});

it('creates communication category channel templates table with expected columns', function () {
    expect(Schema::hasTable('communication_category_channel_templates'))->toBeTrue();

    foreach ([
        'id',
        'uuid',
        'communication_category_id',
        'communication_template_id',
        'channel',
        'is_default',
        'is_active',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('communication_category_channel_templates', $column))->toBeTrue();
    }
});

it('seeds core communication categories', function () {
    expect(CommunicationCategory::query()->where('key', 'auth.verify_email')->exists())->toBeTrue()
        ->and(CommunicationCategory::query()->where('key', 'auth.reset_password')->exists())->toBeTrue()
        ->and(CommunicationCategory::query()->where('key', 'credit_cards.autopay_completed')->exists())->toBeTrue()
        ->and(CommunicationCategory::query()->where('key', 'user.welcome_after_verification')->exists())->toBeTrue()
        ->and(CommunicationCategory::query()->where('key', 'imports.completed')->exists())->toBeTrue()
        ->and(CommunicationCategory::query()->where('key', 'reports.weekly_ready')->exists())->toBeTrue();
});

it('casts category enums correctly', function () {
    $category = CommunicationCategory::query()->where('key', 'imports.completed')->firstOrFail();

    expect($category->delivery_mode)->toBe(CommunicationDeliveryModeEnum::TRANSACTIONAL)
        ->and($category->preference_mode)->toBe(NotificationPreferenceModeEnum::USER_CONFIGURABLE)
        ->and($category->context_type)->toBe('import');
});

it('maps default mail templates to categories', function () {
    $mapping = CommunicationCategoryChannelTemplate::query()
        ->where('channel', CommunicationChannelEnum::MAIL->value)
        ->where('is_default', true)
        ->firstOrFail();

    expect($mapping->template)->not->toBeNull()
        ->and($mapping->category)->not->toBeNull();
});

it('links imports completed category to import completed mail template', function () {
    $category = CommunicationCategory::query()->where('key', 'imports.completed')->firstOrFail();

    $mapping = $category->channelTemplates()
        ->where('channel', CommunicationChannelEnum::MAIL->value)
        ->where('is_default', true)
        ->firstOrFail();

    expect($mapping->template->key)->toBe('import_completed_mail');
});

it('creates active welcome after verification channel mappings for mail and database', function () {
    $category = CommunicationCategory::query()
        ->where('key', 'user.welcome_after_verification')
        ->firstOrFail();

    $mappings = $category->channelTemplates()
        ->where('is_default', true)
        ->where('is_active', true)
        ->with('template')
        ->get()
        ->keyBy(fn ($mapping) => $mapping->channel->value);

    expect($mappings)->toHaveCount(2)
        ->and($mappings[CommunicationChannelEnum::MAIL->value]->template->key)->toBe('welcome_after_verification_mail')
        ->and($mappings[CommunicationChannelEnum::DATABASE->value]->template->key)->toBe('welcome_after_verification_database');
});

it('backfills welcome after verification mappings when templates are seeded after categories', function () {
    CommunicationCategoryChannelTemplate::query()->delete();
    CommunicationTemplate::query()->delete();

    $this->seed(CommunicationCategorySeeder::class);

    expect(CommunicationCategoryChannelTemplate::query()->whereHas('category', function ($query): void {
        $query->where('key', 'user.welcome_after_verification');
    })->exists())->toBeFalse();

    $this->seed(CommunicationTemplateSeeder::class);

    $category = CommunicationCategory::query()
        ->where('key', 'user.welcome_after_verification')
        ->firstOrFail();

    expect($category->channelTemplates()->where('is_default', true)->count())->toBe(2);
});
