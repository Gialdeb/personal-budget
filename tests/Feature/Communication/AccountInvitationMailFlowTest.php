<?php

use App\Enums\CommunicationChannelEnum;
use App\Enums\OutboundMessageStatusEnum;
use App\Events\Sharing\AccountInvitationCreated;
use App\Jobs\DeliverOutboundMessageJob;
use App\Listeners\Sharing\SendAccountInvitationCommunication;
use App\Models\AccountInvitation;
use App\Models\CommunicationCategory;
use App\Models\CommunicationTemplate;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Notifications\DeliveredOutboundMailNotification;
use App\Services\Communication\CommunicationComposerService;
use App\Services\Communication\ContextResolvers\AccountInvitationCommunicationContextResolver;
use App\Services\Communication\OutboundMessageDeliveryService;
use App\Services\Sharing\AccountInvitationService;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
    app()->setLocale('it');
});

it('dispatches the account invitation event and queues an outbound mail for an external email address', function () {
    Event::fake([AccountInvitationCreated::class]);
    Queue::fake();

    $owner = User::factory()->create([
        'name' => 'Giuseppe',
        'surname' => 'De Blasio',
        'email' => 'owner@example.com',
        'locale' => 'it',
    ]);
    $account = createTestAccount($owner, ['name' => 'Conto Famiglia']);

    $created = app(AccountInvitationService::class)->createInvitation(
        $account,
        $owner,
        'invitee@example.com',
        'viewer',
        null,
        now()->addDays(7),
    );

    Event::assertDispatched(AccountInvitationCreated::class);

    $listener = app(SendAccountInvitationCommunication::class);
    $listener->handle(new AccountInvitationCreated(
        invitation: $created['invitation']->fresh(),
        plainToken: $created['plain_token'],
    ));

    $message = OutboundMessage::query()
        ->whereHas('category', fn ($query) => $query->where('key', 'sharing.account_invitation'))
        ->latest('id')
        ->first();

    expect($message)->not->toBeNull()
        ->and($message->channel)->toBe(CommunicationChannelEnum::MAIL)
        ->and($message->status)->toBe(OutboundMessageStatusEnum::QUEUED)
        ->and($message->recipient_type)->toBe($created['invitation']->getMorphClass())
        ->and((int) $message->recipient_id)->toBe($created['invitation']->id)
        ->and(data_get($message->payload_snapshot, 'recipient.email'))->toBe('invitee@example.com')
        ->and($message->category->name)->toBe('Invito condivisione conto')
        ->and($message->template->key)->toBe('account_invitation_mail')
        ->and($message->cta_url_resolved)->toBe(route('account-invitations.onboarding.show', $created['invitation']).'?token='.$created['plain_token'])
        ->and($message->subject_resolved)->toContain('Giuseppe')
        ->and($message->body_resolved)->toContain('Conto Famiglia')
        ->and($message->body_resolved)->toContain('Solo visualizzazione');

    Queue::assertPushedTimes(DeliverOutboundMessageJob::class, 1);
});

it('resolves the account invitation communication context with the expected template fields', function () {
    $owner = User::factory()->create([
        'name' => 'Giuseppe',
        'surname' => 'De Blasio',
        'email' => 'owner@example.com',
    ]);
    $account = createTestAccount($owner, ['name' => 'Conto Famiglia']);

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) str()->uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'invitee@example.com',
        'role' => 'editor',
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', 'secret-token'),
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);
    $invitation->setAttribute('plain_token', 'secret-token');

    $context = app(AccountInvitationCommunicationContextResolver::class)->resolve($invitation);

    expect($context['inviter_name'])->toBe('Giuseppe De Blasio')
        ->and($context['account_name'])->toBe('Conto Famiglia')
        ->and($context['invitee_email'])->toBe('invitee@example.com')
        ->and($context['invitation_role'])->toBe('editor')
        ->and($context['invitation_role_label'])->toBe('Può modificare')
        ->and($context['invitation_accept_url'])->toContain('token=secret-token')
        ->and($context['invitation_expires_at'])->not->toBeNull();
});

it('composes the invitation email with italian content and required variables', function () {
    $owner = User::factory()->create([
        'name' => 'Giuseppe',
        'surname' => 'De Blasio',
        'email' => 'owner@example.com',
        'locale' => 'it',
    ]);
    $account = createTestAccount($owner, ['name' => 'Conto Famiglia']);

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) str()->uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'invitee@example.com',
        'role' => 'viewer',
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', 'secret-token'),
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);
    $invitation->setAttribute('plain_token', 'secret-token');

    $composed = app(CommunicationComposerService::class)->compose(
        'sharing.account_invitation',
        CommunicationChannelEnum::MAIL,
        $invitation,
        'it',
    );

    expect($composed->template->key)->toBe('account_invitation_mail')
        ->and($composed->subject)->toContain('Giuseppe De Blasio')
        ->and($composed->title)->toBe('Hai ricevuto un invito')
        ->and($composed->body)->toContain('Conto Famiglia')
        ->and($composed->body)->toContain('Solo visualizzazione')
        ->and($composed->body)->toContain('Questo invito scade il')
        ->and($composed->body)->not->toContain('{invitation_expires_at}')
        ->and($composed->ctaLabel)->toBe('Accetta invito')
        ->and($composed->ctaUrl)->toContain('token=secret-token');
});

it('omits unresolved expiration placeholders when the invitation has no expiration date', function () {
    $owner = User::factory()->create([
        'name' => 'Giuseppe',
        'surname' => 'De Blasio',
        'email' => 'owner@example.com',
        'locale' => 'it',
    ]);
    $account = createTestAccount($owner, ['name' => 'Conto Famiglia']);

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) str()->uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'invitee@example.com',
        'role' => 'viewer',
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', 'secret-token'),
        'status' => 'pending',
        'expires_at' => null,
    ]);
    $invitation->setAttribute('plain_token', 'secret-token');

    $composed = app(CommunicationComposerService::class)->compose(
        'sharing.account_invitation',
        CommunicationChannelEnum::MAIL,
        $invitation,
        'it',
    );

    expect($composed->body)->not->toContain('{invitation_expires_at}')
        ->and($composed->body)->not->toContain('{invitation_expiry_notice}')
        ->and($composed->body)->not->toContain('Questo invito scade il');
});

it('delivers the queued invitation email through the mail channel for a non registered recipient', function () {
    Notification::fake();

    $owner = User::factory()->create([
        'email' => 'owner@example.com',
    ]);
    $account = createTestAccount($owner, ['name' => 'Conto Famiglia']);
    $category = CommunicationCategory::query()->where('key', 'sharing.account_invitation')->firstOrFail();
    $template = CommunicationTemplate::query()->where('key', 'account_invitation_mail')->firstOrFail();

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) str()->uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'invitee@example.com',
        'role' => 'viewer',
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', 'secret-token'),
        'status' => 'pending',
        'expires_at' => now()->addDays(7),
    ]);

    $message = OutboundMessage::query()->create([
        'communication_category_id' => $category->id,
        'communication_template_id' => $template->id,
        'channel' => CommunicationChannelEnum::MAIL,
        'status' => OutboundMessageStatusEnum::QUEUED,
        'recipient_type' => $invitation->getMorphClass(),
        'recipient_id' => $invitation->id,
        'context_type' => $invitation->getMorphClass(),
        'context_id' => $invitation->id,
        'subject_resolved' => 'Giuseppe ti ha invitato a condividere un conto su Soamco Budget',
        'title_resolved' => 'Hai ricevuto un invito',
        'body_resolved' => 'Livello di accesso assegnato: Solo visualizzazione',
        'cta_label_resolved' => 'Accetta invito',
        'cta_url_resolved' => 'https://soamco.lo/account-invitations/test/onboarding?token=secret-token',
        'payload_snapshot' => [
            'recipient' => [
                'email' => 'invitee@example.com',
                'label' => 'invitee@example.com',
                'type' => 'email',
            ],
        ],
    ]);

    $delivered = app(OutboundMessageDeliveryService::class)->deliver($message);

    Notification::assertSentOnDemand(DeliveredOutboundMailNotification::class, function ($notification, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'invitee@example.com';
    });

    expect($delivered->status)->toBe(OutboundMessageStatusEnum::SENT)
        ->and($delivered->sent_at)->not->toBeNull();
});
