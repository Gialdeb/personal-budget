<?php

namespace App\Listeners\Sharing;

use App\Enums\CommunicationChannelEnum;
use App\Events\Sharing\AccountInvitationCreated;
use App\Models\CommunicationCategory;
use App\Models\User;
use App\Services\Communication\CommunicationDispatchService;

class SendAccountInvitationCommunication
{
    public function __construct(
        protected CommunicationDispatchService $dispatchService,
    ) {}

    public function handle(AccountInvitationCreated $event): void
    {
        $invitation = $event->invitation->loadMissing(['account', 'invitedBy']);
        $recipient = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])
            ->first();

        if (! $recipient instanceof User) {
            return;
        }

        $categoryExists = CommunicationCategory::query()
            ->where('key', 'sharing.account_invitation')
            ->where('is_active', true)
            ->exists();

        if (! $categoryExists) {
            return;
        }

        $invitation->setAttribute('plain_token', $event->plainToken);

        $this->dispatchService->dispatchManualCategory(
            categoryKey: 'sharing.account_invitation',
            channel: CommunicationChannelEnum::MAIL,
            recipient: $recipient,
            contextModel: $invitation,
            actor: $invitation->invitedBy,
        );
    }
}
