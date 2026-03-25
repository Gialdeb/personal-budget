<?php

namespace App\Actions\Sharing;

use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\User;
use App\Services\Sharing\AccountMembershipService;

class AcceptAccountInvitationAction
{
    public function __construct(
        protected AccountMembershipService $membershipService,
    ) {}

    public function execute(
        AccountInvitation $invitation,
        User $user,
        string $plainToken,
    ): AccountMembership {
        return $this->membershipService->acceptInvitation(
            invitation: $invitation,
            user: $user,
            plainToken: $plainToken,
        );
    }
}
