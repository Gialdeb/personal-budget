<?php

use App\Jobs\DeliverOutboundMessageJob;
use App\Models\CommunicationCategory;
use App\Models\OutboundMessage;
use App\Models\User;
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

it('renders the admin communication composer with compatible manual send categories', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('admin.communications.compose.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Communications/Compose')
            ->where('auth.user.is_admin', true)
            ->where('categories', function ($categories) {
                $keys = collect($categories)->pluck('key')->sort()->values()->all();
                $autopayCompleted = collect($categories)->firstWhere('key', 'credit_cards.autopay_completed');
                $verifyEmail = collect($categories)->firstWhere('key', 'auth.verify_email');
                $welcome = collect($categories)->firstWhere('key', 'user.welcome_after_verification');

                return $keys === ['auth.reset_password', 'auth.verify_email', 'credit_cards.autopay_completed', 'reports.weekly_ready', 'user.welcome_after_verification']
                    && collect($categories)->every(
                        fn ($category) => is_string($category['uuid'])
                            && ($category['flags']['available_for_manual_send'] ?? false) === true
                            && ! array_key_exists('id', $category)
                    )
                    && $autopayCompleted !== null
                    && collect($autopayCompleted['channel_options'])->firstWhere('value', 'mail')['is_disabled'] === false
                    && collect($autopayCompleted['channel_options'])->firstWhere('value', 'database')['is_disabled'] === false
                    && collect($autopayCompleted['channel_options'])->firstWhere('value', 'sms')['is_disabled'] === true
                    && collect($autopayCompleted['channel_options'])->firstWhere('value', 'telegram')['is_disabled'] === true
                    && $verifyEmail !== null
                    && $verifyEmail['fixed_channel'] === 'mail'
                    && collect($verifyEmail['channel_options'])->firstWhere('value', 'mail')['is_fixed'] === true
                    && collect($verifyEmail['channel_options'])->firstWhere('value', 'database')['is_disabled'] === true
                    && collect($verifyEmail['channel_options'])->firstWhere('value', 'sms')['is_disabled'] === true
                    && collect($verifyEmail['channel_options'])->firstWhere('value', 'telegram')['is_disabled'] === true
                    && $welcome !== null
                    && collect($welcome['channel_options'])->firstWhere('value', 'sms')['is_disabled'] === true
                    && collect($welcome['channel_options'])->firstWhere('value', 'telegram')['is_disabled'] === true;
            })
            ->where('channels', fn ($channels) => collect($channels)->contains(fn ($channel) => $channel['value'] === 'mail'))
            ->where('locale_options.0.value', 'recipient')
            ->where('content_modes.0.value', 'template')
        );
});

it('returns recipient lookup payload with uuid and no internal ids', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $recipient = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
    ]);

    $otherAdmin = User::factory()->create([
        'name' => 'Ignored',
        'email' => 'ignored@example.com',
    ]);
    $otherAdmin->assignRole('admin');

    $this->actingAs($admin)
        ->getJson(route('admin.communications.compose.recipients', [
            'search' => 'Mario',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $recipient->uuid)
        ->assertJsonPath('data.0.email', 'mario@example.com')
        ->assertJsonMissingPath('data.0.id');
});

it('searches recipients by name surname email and full name tokens', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $recipient = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario.rossi@example.com',
    ]);

    foreach (['Mario', 'Rossi', 'mario.rossi@example.com', 'Mario Rossi'] as $search) {
        $this->actingAs($admin)
            ->getJson(route('admin.communications.compose.recipients', [
                'search' => $search,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $recipient->uuid);
    }
});

it('returns a real backend preview for manual composer selections', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $recipient = User::factory()->create([
        'name' => 'Lucia',
        'surname' => 'Bianchi',
        'email' => 'lucia@example.com',
    ]);

    $categoryUuid = CommunicationCategory::query()
        ->where('key', 'user.welcome_after_verification')
        ->value('uuid');

    $this->actingAs($admin)
        ->postJson(route('admin.communications.compose.preview'), [
            'category_uuid' => $categoryUuid,
            'channels' => ['mail', 'database'],
            'recipient_uuids' => [$recipient->uuid],
            'locale' => 'recipient',
            'content_mode' => 'template',
        ])
        ->assertOk()
        ->assertJsonPath('data.category.key', 'user.welcome_after_verification')
        ->assertJsonPath('data.sample_recipient.uuid', $recipient->uuid)
        ->assertJsonPath('data.recipient_count', 1)
        ->assertJsonPath('data.previews.0.channel.value', 'mail')
        ->assertJsonPath('data.previews.0.context.uuid', $recipient->uuid)
        ->assertJsonPath('data.previews.0.presentation.layout', 'mail')
        ->assertJsonPath('data.previews.1.channel.value', 'database');
});

it('omits cta values from custom preview when custom cta fields are empty', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $recipient = User::factory()->create([
        'name' => 'Lucia',
        'surname' => 'Bianchi',
        'email' => 'lucia@example.com',
    ]);

    $categoryUuid = CommunicationCategory::query()
        ->where('key', 'user.welcome_after_verification')
        ->value('uuid');

    $this->actingAs($admin)
        ->postJson(route('admin.communications.compose.preview'), [
            'category_uuid' => $categoryUuid,
            'channels' => ['mail'],
            'recipient_uuids' => [$recipient->uuid],
            'locale' => 'recipient',
            'content_mode' => 'custom',
            'custom_content' => [
                'subject' => 'Messaggio admin',
                'title' => 'Titolo admin',
                'body' => 'Contenuto amministrativo',
                'cta_label' => '',
                'cta_url' => '',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.previews.0.content.cta_label', null)
        ->assertJsonPath('data.previews.0.content.cta_url', null);
});

it('dispatches a manual communication to a single user and creates outbound messages for selected channels', function () {
    Queue::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $recipient = User::factory()->create([
        'name' => 'Lucia',
        'surname' => 'Bianchi',
        'email' => 'lucia@example.com',
    ]);

    $category = CommunicationCategory::query()
        ->where('key', 'user.welcome_after_verification')
        ->firstOrFail();

    $this->actingAs($admin)
        ->postJson(route('admin.communications.compose.send'), [
            'category_uuid' => $category->uuid,
            'channels' => ['mail', 'database'],
            'recipient_uuids' => [$recipient->uuid],
            'locale' => 'recipient',
            'content_mode' => 'template',
        ])
        ->assertOk()
        ->assertJsonPath('data.outbound_count', 2)
        ->assertJsonPath('data.recipient_count', 1)
        ->assertJsonPath('data.channel_count', 2)
        ->assertJsonPath('data.messages.0.channel', 'mail');

    $message = OutboundMessage::query()
        ->where('channel', 'mail')
        ->latest('id')
        ->firstOrFail();

    expect($message->uuid)->not->toBe('')
        ->and($message->category->key)->toBe('user.welcome_after_verification')
        ->and($message->recipient_id)->toBe($recipient->id)
        ->and($message->created_by)->toBe($admin->id);

    Queue::assertPushedTimes(DeliverOutboundMessageJob::class, 2);
});

it('dispatches a manual communication to multiple users with forced locale and custom content', function () {
    Queue::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $firstRecipient = User::factory()->create([
        'name' => 'Giulia',
        'surname' => 'Verdi',
        'locale' => 'it',
    ]);

    $secondRecipient = User::factory()->create([
        'name' => 'John',
        'surname' => 'Doe',
        'locale' => 'en',
    ]);

    $category = CommunicationCategory::query()
        ->where('key', 'user.welcome_after_verification')
        ->firstOrFail();

    $this->actingAs($admin)
        ->postJson(route('admin.communications.compose.send'), [
            'category_uuid' => $category->uuid,
            'channels' => ['mail'],
            'recipient_uuids' => [$firstRecipient->uuid, $secondRecipient->uuid],
            'locale' => 'it',
            'content_mode' => 'custom',
            'custom_content' => [
                'subject' => 'Messaggio admin',
                'title' => 'Titolo admin',
                'body' => 'Contenuto amministrativo',
                'cta_label' => 'Apri dashboard',
                'cta_url' => '/dashboard',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.outbound_count', 2)
        ->assertJsonPath('data.recipient_count', 2)
        ->assertJsonPath('data.channel_count', 1);

    expect(OutboundMessage::query()->count())->toBe(2)
        ->and(OutboundMessage::query()->pluck('subject_resolved')->unique()->all())->toBe(['Messaggio admin'])
        ->and(OutboundMessage::query()->pluck('title_resolved')->unique()->all())->toBe(['Titolo admin'])
        ->and(OutboundMessage::query()->pluck('body_resolved')->unique()->all())->toBe(['Contenuto amministrativo']);

    Queue::assertPushedTimes(DeliverOutboundMessageJob::class, 2);
});

it('rejects manual composer preview for invalid categories', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $recipient = User::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('admin.communications.compose.preview'), [
            'category_uuid' => (string) str()->uuid(),
            'channels' => ['mail'],
            'recipient_uuids' => [$recipient->uuid],
            'locale' => 'recipient',
            'content_mode' => 'template',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category_uuid']);
});
