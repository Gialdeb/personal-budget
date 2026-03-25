<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationTemplateModeEnum;
use App\Models\CommunicationCategory;
use App\Models\CommunicationCategoryChannelTemplate;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationDispatchService;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

it('renders admin communication categories pages with global channel capability data', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $verifyCategory = CommunicationCategory::query()
        ->where('key', 'auth.verify_email')
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.communication-categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CommunicationCategories/Index')
            ->where('auth.user.is_admin', true)
            ->where('categories.data.0.channels', fn ($channels) => collect($channels)->every(
                fn ($channel) => ! array_key_exists('id', $channel)
            )));

    $this->actingAs($admin)
        ->get(route('admin.communication-categories.show', $verifyCategory->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CommunicationCategories/Show')
            ->where('category.key', 'auth.verify_email')
            ->where('category.fixed_channel', 'mail')
            ->where('category.channels', function ($channels) {
                $telegram = collect($channels)->firstWhere('value', 'telegram');

                return $telegram !== null
                    && $telegram['is_globally_available'] === false
                    && $telegram['is_disabled'] === true;
            }));
});

it('uses the same category channel configuration for composer and automatic dispatch', function () {
    Queue::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $category = CommunicationCategory::query()
        ->where('key', 'user.welcome_after_verification')
        ->firstOrFail();

    $mailTemplate = CommunicationTemplate::query()
        ->where('key', 'welcome_after_verification_mail')
        ->firstOrFail();

    $databaseTemplate = CommunicationTemplate::query()
        ->where('key', 'welcome_after_verification_database')
        ->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('admin.communication-categories.channels.update', $category->uuid), [
            'channels' => [
                [
                    'value' => 'mail',
                    'enabled' => true,
                    'template_uuid' => $mailTemplate->uuid,
                ],
                [
                    'value' => 'database',
                    'enabled' => false,
                    'template_uuid' => $databaseTemplate->uuid,
                ],
                [
                    'value' => 'sms',
                    'enabled' => false,
                    'template_uuid' => null,
                ],
                [
                    'value' => 'telegram',
                    'enabled' => false,
                    'template_uuid' => null,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->get(route('admin.communications.compose.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories', function ($categories) {
                $welcome = collect($categories)->firstWhere('key', 'user.welcome_after_verification');

                return $welcome !== null
                    && collect($welcome['channel_options'])->firstWhere('value', 'mail')['is_supported'] === true
                    && collect($welcome['channel_options'])->firstWhere('value', 'database')['is_supported'] === false;
            }));

    $recipient = User::factory()->create();

    $messages = app(CommunicationDispatchService::class)->dispatchForUserCategory(
        'user.welcome_after_verification',
        $recipient,
        $recipient,
    );

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->channel->value)->toBe(CommunicationChannelEnum::MAIL->value);
});

it('keeps globally unavailable channels disabled even when a category mapping exists', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $category = CommunicationCategory::query()
        ->where('key', 'user.welcome_after_verification')
        ->firstOrFail();

    $telegramTemplate = CommunicationTemplate::query()->create([
        'key' => 'welcome_after_verification_telegram',
        'channel' => CommunicationChannelEnum::TELEGRAM,
        'template_mode' => CommunicationTemplateModeEnum::CUSTOMIZABLE,
        'name' => 'Welcome Telegram',
        'description' => 'Telegram welcome template',
        'subject_template' => null,
        'title_template' => 'Benvenuto',
        'body_template' => 'Benvenuto {user.full_name}',
        'cta_label_template' => null,
        'cta_url_template' => null,
        'is_system_locked' => false,
        'is_active' => true,
    ]);

    CommunicationCategoryChannelTemplate::query()->create([
        'communication_category_id' => $category->id,
        'communication_template_id' => $telegramTemplate->id,
        'channel' => CommunicationChannelEnum::TELEGRAM,
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.communications.compose.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories', function ($categories) {
                $welcome = collect($categories)->firstWhere('key', 'user.welcome_after_verification');

                return $welcome !== null
                    && collect($welcome['channel_options'])->firstWhere('value', 'telegram')['is_globally_available'] === false
                    && collect($welcome['channel_options'])->firstWhere('value', 'telegram')['is_disabled'] === true;
            }));

    $recipient = User::factory()->create();

    $messages = app(CommunicationDispatchService::class)->dispatchForUserCategory(
        'user.welcome_after_verification',
        $recipient,
        $recipient,
    );

    expect(collect($messages)->pluck('channel')->map->value->all())
        ->not->toContain(CommunicationChannelEnum::TELEGRAM->value);
});
