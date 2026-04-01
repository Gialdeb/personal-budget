<?php

use App\Enums\CommunicationChannelEnum;
use App\Jobs\DeliverOutboundMessageJob;
use App\Models\OutboundMessage;
use App\Models\User;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.telegram.enabled', false);

    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

it('dispatches the welcome communication after email verification', function () {
    Queue::fake();

    $user = User::factory()->create([
        'name' => 'Giuseppe',
        'surname' => 'De Blasio',
        'email' => 'giuseppe@example.com',
        'email_verified_at' => null,
    ]);

    event(new Verified($user));

    $message = OutboundMessage::query()
        ->where('channel', CommunicationChannelEnum::MAIL->value)
        ->first();

    expect($message)->not->toBeNull()
        ->and($message->category->key)->toBe('user.welcome_after_verification')
        ->and($message->status->value)->toBe('queued')
        ->and($message->body_resolved)->toContain('Giuseppe De Blasio');

    expect(OutboundMessage::query()->count())->toBe(2);
    Queue::assertPushedTimes(DeliverOutboundMessageJob::class, 2);
});

it('stores the verified user as both recipient and context', function () {
    Queue::fake();

    $user = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario@example.com',
    ]);

    event(new Verified($user));

    $message = OutboundMessage::query()
        ->where('channel', CommunicationChannelEnum::MAIL->value)
        ->firstOrFail();

    expect($message->recipient_type)->toBe($user->getMorphClass())
        ->and((int) $message->recipient_id)->toBe($user->id)
        ->and($message->context_type)->toBe($user->getMorphClass())
        ->and((int) $message->context_id)->toBe($user->id);
});

it('dispatches welcome only once even if verified event is emitted twice', function () {
    Queue::fake();

    $user = User::factory()->create([
        'name' => 'Giulia',
        'surname' => 'Verdi',
        'locale' => 'it',
    ]);

    $user->forceFill([
        'email_verified_at' => now(),
    ])->save();

    event(new Verified($user));
    event(new Verified($user->fresh()));

    expect(OutboundMessage::query()->count())->toBe(2);
    Queue::assertPushedTimes(DeliverOutboundMessageJob::class, 2);
});

it('localizes welcome communication and normalizes mail cta url for italian users', function () {
    Queue::fake();

    $user = User::factory()->create([
        'name' => 'Giulia',
        'surname' => 'Verdi',
        'email' => 'giulia@example.com',
        'locale' => 'it',
        'email_verified_at' => null,
    ]);

    event(new Verified($user));

    $mailMessage = OutboundMessage::query()
        ->where('channel', CommunicationChannelEnum::MAIL->value)
        ->firstOrFail();
    $databaseMessage = OutboundMessage::query()
        ->where('channel', CommunicationChannelEnum::DATABASE->value)
        ->firstOrFail();

    expect($mailMessage->subject_resolved)->toBe('Benvenuto su Soamco Budget')
        ->and($mailMessage->title_resolved)->toBe('Benvenuto su Soamco Budget')
        ->and($mailMessage->body_resolved)->toContain('Benvenuto Giulia Verdi, ti ringrazio per esserti iscritto.')
        ->and($mailMessage->cta_label_resolved)->toBe('Apri dashboard')
        ->and($mailMessage->cta_url_resolved)->toBe(url('/dashboard'))
        ->and($databaseMessage->title_resolved)->toBe('Benvenuto su Soamco Budget')
        ->and($databaseMessage->body_resolved)->toContain('Benvenuto Giulia Verdi, ti ringrazio per esserti iscritto.')
        ->and($databaseMessage->cta_label_resolved)->toBe('Apri dashboard')
        ->and($databaseMessage->cta_url_resolved)->toBe('/dashboard')
        ->and($mailMessage->title_resolved)->toBe($databaseMessage->title_resolved)
        ->and($mailMessage->body_resolved)->toBe($databaseMessage->body_resolved)
        ->and($mailMessage->cta_label_resolved)->toBe($databaseMessage->cta_label_resolved);
});
