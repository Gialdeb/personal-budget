<?php

namespace App\Actions\Sharing;

use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\User;
use App\Services\Sharing\AccountMembershipService;

class AcceptAccountInvitationForAuthenticatedUserAction
{
    public function __construct(
        protected AccountMembershipService $membershipService,
    ) {}

    public function execute(
        AccountInvitation $accountInvitation,
        User $user,
        string $plainToken,
    ): AccountMembership {
        return $this->membershipService->acceptInvitation(
            invitation: $accountInvitation,
            user: $user,
            plainToken: $plainToken,
        );
    }
}
