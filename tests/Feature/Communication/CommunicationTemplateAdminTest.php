<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\CommunicationTemplateModeEnum;
use App\Enums\CommunicationTemplateOverrideScopeEnum;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateOverride;
use App\Models\User;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
});

it('renders the admin communication templates index with uuid only payload', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $template = CommunicationTemplate::query()
        ->where('key', 'import_completed_mail')
        ->firstOrFail();

    $this->actingAs($user)
        ->get(route('admin.communication-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CommunicationTemplates/Index')
            ->where('auth.user.is_admin', true)
            ->where('filters.search', '')
            ->where('filters.channel', null)
            ->where('filters.template_mode', null)
            ->where('options.channels', ['mail', 'database', 'sms', 'telegram'])
            ->where('options.template_modes', ['system', 'customizable', 'freeform'])
            ->where('templates.data', fn ($templates) => count($templates) >= 1
                && collect($templates)->every(
                    fn ($item) => is_string($item['uuid'])
                        && ! array_key_exists('id', $item)
                ))
            ->where('templates.meta.total', fn ($total) => is_int($total) && $total >= 1)
            ->where('templates.data', fn ($templates) => collect($templates)->contains(
                fn ($item) => $item['uuid'] === $template->uuid
                    && $item['key'] === 'import_completed_mail'
                    && ! array_key_exists('notification_topic_id', $item)
                    && ! array_key_exists('id', $item['topic'] ?? [])
            ))
        );
});

it('filters the admin communication templates index using query params', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)
        ->get(route('admin.communication-templates.index', [
            'search' => 'import',
            'channel' => 'mail',
            'template_mode' => 'customizable',
            'override_state' => 'without_override',
            'lock_state' => 'editable',
            'page' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CommunicationTemplates/Index')
            ->where('filters.search', 'import')
            ->where('filters.channel', 'mail')
            ->where('filters.template_mode', 'customizable')
            ->where('filters.override_state', 'without_override')
            ->where('filters.lock_state', 'editable')
            ->where('templates.data', fn ($templates) => count($templates) === 1
                && collect($templates)->every(fn ($item) => $item['key'] === 'import_completed_mail'))
        );
});

it('paginates the admin communication templates index', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    foreach (range(1, 8) as $index) {
        CommunicationTemplate::query()->create([
            'key' => "bulk_template_{$index}",
            'channel' => CommunicationChannelEnum::MAIL,
            'template_mode' => $index % 2 === 0
                ? CommunicationTemplateModeEnum::FREEFORM
                : CommunicationTemplateModeEnum::CUSTOMIZABLE,
            'name' => "Bulk Template {$index}",
            'description' => 'Generated for pagination test.',
            'subject_template' => null,
            'title_template' => null,
            'body_template' => 'Body',
            'cta_label_template' => null,
            'cta_url_template' => null,
            'is_system_locked' => false,
            'is_active' => true,
        ]);
    }

    $this->actingAs($user)
        ->get(route('admin.communication-templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CommunicationTemplates/Index')
            ->where('templates.meta.current_page', 1)
            ->where('templates.meta.last_page', fn ($lastPage) => is_int($lastPage) && $lastPage >= 2)
            ->where('templates.data', fn ($templates) => count($templates) === 10)
        );
});

it('renders the admin communication template detail with base override and preview payload', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $template = CommunicationTemplate::query()
        ->where('key', 'import_completed_mail')
        ->firstOrFail();

    CommunicationTemplateOverride::query()->create([
        'communication_template_id' => $template->id,
        'scope' => CommunicationTemplateOverrideScopeEnum::GLOBAL,
        'subject_template' => 'custom.import.subject',
        'title_template' => 'custom.import.title',
        'body_template' => 'custom.import.body',
        'cta_label_template' => 'custom.import.cta',
        'cta_url_template' => '/imports/{import_uuid}',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('admin.communication-templates.show', $template->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CommunicationTemplates/Show')
            ->where('template.uuid', $template->uuid)
            ->where('template.key', 'import_completed_mail')
            ->where('template.flags.can_edit_override', true)
            ->where('template.global_override.uuid', fn ($uuid) => is_string($uuid) && $uuid !== '')
            ->where('template.preview.subject', fn ($value) => is_string($value) && $value !== '')
            ->has('template.available_variables')
            ->missing('template.id')
            ->missing('template.global_override.id')
            ->missing('template.topic.id')
        );
});

it('renders the admin communication template edit page with uuid only payload', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $template = CommunicationTemplate::query()
        ->where('key', 'monthly_report_ready_mail')
        ->firstOrFail();

    $this->actingAs($user)
        ->get(route('admin.communication-templates.edit', $template->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/CommunicationTemplates/Edit')
            ->where('template.uuid', $template->uuid)
            ->where('template.flags.can_preview', true)
            ->has('template.available_variables')
            ->missing('template.id')
            ->missing('template.topic.id')
        );
});

it('creates or updates a global override from the admin endpoint', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $template = CommunicationTemplate::query()
        ->where('key', 'monthly_report_ready_mail')
        ->firstOrFail();

    $this->actingAs($user)
        ->from(route('admin.communication-templates.edit', $template->uuid))
        ->patch(route('admin.communication-templates.global-override.update', $template->uuid), [
            'subject_template' => 'custom.monthly.subject',
            'title_template' => 'custom.monthly.title',
            'body_template' => 'custom.monthly.body',
            'cta_label_template' => 'custom.monthly.cta',
            'cta_url_template' => '/reports/{period}',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.communication-templates.edit', $template->uuid))
        ->assertSessionHas('success');

    $override = $template->fresh()->overrides()->latest('id')->firstOrFail();

    expect($override->uuid)->not->toBe('')
        ->and($override->subject_template)->toBe('custom.monthly.subject')
        ->and($override->is_active)->toBeTrue();
});

it('disables a global override from the admin endpoint', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $template = CommunicationTemplate::query()
        ->where('key', 'import_completed_mail')
        ->firstOrFail();

    CommunicationTemplateOverride::query()->create([
        'communication_template_id' => $template->id,
        'scope' => CommunicationTemplateOverrideScopeEnum::GLOBAL,
        'subject_template' => 'custom.import.subject',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->from(route('admin.communication-templates.edit', $template->uuid))
        ->post(route('admin.communication-templates.global-override.disable', $template->uuid))
        ->assertRedirect(route('admin.communication-templates.edit', $template->uuid))
        ->assertSessionHas('success');

    expect($template->fresh()->overrides()->latest('id')->firstOrFail()->is_active)->toBeFalse();
});
