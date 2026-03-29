<?php

use App\Enums\CommunicationChannelEnum;
use App\Jobs\DeliverOutboundMessageJob;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Services\Sharing\AccountInvitationService;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

it('dispatches an account invitation as a mail-only outbound communication', function () {
    Queue::fake();

    $owner = User::factory()->create([
        'name' => 'Giuseppe',
        'surname' => 'De Blasio',
        'email' => 'owner@example.com',
    ]);
    $invitee = User::factory()->create([
        'name' => 'Giulia',
        'surname' => 'Verdi',
        'email' => 'invitee@example.com',
    ]);
    $account = createTestAccount($owner, ['name' => 'Conto Famiglia']);

    $created = app(AccountInvitationService::class)->createInvitation(
        $account,
        $owner,
        $invitee->email,
        'viewer',
        null,
        now()->addDays(7),
    );

    $messages = OutboundMessage::query()
        ->whereHas('category', fn ($query) => $query->where('key', 'sharing.account_invitation'))
        ->get();

    expect($messages)->toHaveCount(1)
        ->and($messages->first()->channel)->toBe(CommunicationChannelEnum::MAIL)
        ->and($messages->first()->status->value)->toBe('queued')
        ->and($messages->first()->category->key)->toBe('sharing.account_invitation')
        ->and((int) $messages->first()->recipient_id)->toBe($created['invitation']->id)
        ->and($messages->first()->recipient_type)->toBe($created['invitation']->getMorphClass())
        ->and(data_get($messages->first()->payload_snapshot, 'recipient.email'))->toBe($invitee->email)
        ->and($messages->first()->cta_url_resolved)->toBe(route('account-invitations.onboarding.show', $created['invitation']).'?token='.$created['plain_token'])
        ->and($messages->first()->body_resolved)->toContain('Giuseppe De Blasio')
        ->and($messages->first()->body_resolved)->toContain('Conto Famiglia');

    expect(OutboundMessage::query()->where('channel', CommunicationChannelEnum::DATABASE->value)->count())->toBe(0)
        ->and(OutboundMessage::query()->where('channel', 'sms')->count())->toBe(0)
        ->and(OutboundMessage::query()->where('channel', 'telegram')->count())->toBe(0);

    Queue::assertPushedTimes(DeliverOutboundMessageJob::class, 1);
});
