<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\OutboundMessageStatusEnum;
use App\Models\CommunicationCategory;
use App\Models\CommunicationTemplate;
use App\Models\OutboundMessage;
use App\Models\User;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

function createOutbound(array $attributes = []): OutboundMessage
{
    $recipient = $attributes['recipient'] ?? User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => fake()->unique()->safeEmail(),
    ]);

    $category = $attributes['category'] ?? CommunicationCategory::query()
        ->where('key', 'user.welcome_after_verification')
        ->firstOrFail();

    $template = $attributes['template'] ?? CommunicationTemplate::query()
        ->where('key', 'welcome_after_verification_mail')
        ->firstOrFail();

    $message = OutboundMessage::query()->create([
        'communication_category_id' => $category->id,
        'communication_template_id' => $template->id,
        'channel' => $attributes['channel'] ?? CommunicationChannelEnum::MAIL,
        'status' => $attributes['status'] ?? OutboundMessageStatusEnum::QUEUED,
        'recipient_type' => User::class,
        'recipient_id' => $recipient->id,
        'context_type' => User::class,
        'context_id' => $recipient->id,
        'subject_resolved' => $attributes['subject_resolved'] ?? 'Benvenuto',
        'title_resolved' => $attributes['title_resolved'] ?? 'Titolo welcome',
        'body_resolved' => $attributes['body_resolved'] ?? 'Contenuto welcome',
        'cta_label_resolved' => $attributes['cta_label_resolved'] ?? 'Apri dashboard',
        'cta_url_resolved' => $attributes['cta_url_resolved'] ?? 'https://example.test/dashboard',
        'payload_snapshot' => $attributes['payload_snapshot'] ?? ['foo' => 'bar'],
        'queued_at' => $attributes['queued_at'] ?? now(),
        'sent_at' => $attributes['sent_at'] ?? null,
        'failed_at' => $attributes['failed_at'] ?? null,
        'error_message' => $attributes['error_message'] ?? null,
        'created_by' => $attributes['created_by'] ?? null,
    ]);

    if (array_key_exists('created_at', $attributes) || array_key_exists('updated_at', $attributes)) {
        $message->forceFill([
            'created_at' => $attributes['created_at'] ?? $message->created_at,
            'updated_at' => $attributes['updated_at'] ?? $message->updated_at,
        ])->save();
    }

    return $message->fresh();
}

it('renders the admin outbound history page with paginated outbound messages', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $message = createOutbound();

    $this->actingAs($admin)
        ->get(route('admin.communications.outbound.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Communications/Outbound/Index')
            ->where('auth.user.is_admin', true)
            ->where('outboundMessages.data.0.uuid', $message->uuid)
            ->where('outboundMessages.data.0.status', 'queued')
            ->where('outboundMessages.data.0.channel', 'mail')
            ->where('outboundMessages.data.0.category.key', 'user.welcome_after_verification')
            ->missing('outboundMessages.data.0.id'));
});

it('supports real pagination on admin outbound history', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    foreach (range(1, 21) as $index) {
        createOutbound([
            'subject_resolved' => "Messaggio {$index}",
            'queued_at' => now()->subMinutes($index),
            'created_at' => now()->subMinutes($index),
            'updated_at' => now()->subMinutes($index),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.communications.outbound.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('outboundMessages.meta.current_page', 2)
            ->where('outboundMessages.meta.last_page', 2)
            ->where('outboundMessages.meta.total', 21)
            ->where('outboundMessages.meta.from', 21)
            ->where('outboundMessages.meta.to', 21)
            ->where('outboundMessages.data.0.content.subject', 'Messaggio 21'));
});

it('filters admin outbound history by status channel category and recipient', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $recipient = User::factory()->create([
        'name' => 'Lucia',
        'surname' => 'Bianchi',
        'email' => 'lucia@example.com',
    ]);

    createOutbound([
        'recipient' => $recipient,
        'channel' => CommunicationChannelEnum::DATABASE,
        'status' => OutboundMessageStatusEnum::FAILED,
        'error_message' => 'Delivery exploded',
    ]);

    createOutbound([
        'status' => OutboundMessageStatusEnum::SENT,
        'channel' => CommunicationChannelEnum::MAIL,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.communications.outbound.index', [
            'status' => 'failed',
            'channel' => 'database',
            'category' => 'user.welcome_after_verification',
            'recipient' => 'lucia@example.com',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('outboundMessages.meta.total', 1)
            ->where('outboundMessages.data.0.status', 'failed')
            ->where('outboundMessages.data.0.channel', 'database')
            ->where('outboundMessages.data.0.recipient.email', 'lucia@example.com'));
});

it('renders the admin outbound detail page with resolved content and payload', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $message = createOutbound([
        'status' => OutboundMessageStatusEnum::FAILED,
        'error_message' => 'SMTP rejected message',
        'payload_snapshot' => ['recipient' => ['email' => 'mario@example.com']],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.communications.outbound.show', $message->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Communications/Outbound/Show')
            ->where('outboundMessage.uuid', $message->uuid)
            ->where('outboundMessage.status', 'failed')
            ->where('outboundMessage.error_message', 'SMTP rejected message')
            ->where('outboundMessage.content.subject', 'Benvenuto')
            ->where('outboundMessage.payload_snapshot.recipient.email', 'mario@example.com')
            ->missing('outboundMessage.id'));
});
