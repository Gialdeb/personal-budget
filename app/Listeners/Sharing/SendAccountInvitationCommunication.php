<?php

namespace App\Listeners\Sharing;

use App\Events\Sharing\AccountInvitationCreated;
use App\Models\CommunicationCategory;
use App\Services\Communication\CommunicationDispatchService;

class SendAccountInvitationCommunication
{
    public function __construct(
        protected CommunicationDispatchService $dispatchService,
    ) {}

    public function handle(AccountInvitationCreated $event): void
    {
        $invitation = $event->invitation->loadMissing(['account', 'invitedBy']);

        $categoryExists = CommunicationCategory::query()
            ->where('key', 'sharing.account_invitation')
            ->where('is_active', true)
            ->exists();

        if (! $categoryExists) {
            return;
        }

        $invitation->setAttribute('plain_token', $event->plainToken);

        $recipientLabel = trim(implode(' ', array_filter([
            $invitation->email,
        ])));

        $this->dispatchService->dispatchManualCategoryToMailAddress(
            categoryKey: 'sharing.account_invitation',
            email: $invitation->email,
            recipientLabel: $recipientLabel,
            contextModel: $invitation,
            actor: $invitation->invitedBy,
            forcedLocale: $invitation->invitedBy?->locale,
        );
    }
}
