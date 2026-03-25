<?php

namespace App\Actions\Sharing;

use App\Models\AccountMembership;
use App\Models\User;
use App\Services\Sharing\AccountMembershipLifecycleService;

class RevokeAccountMembershipAction
{
    public function __construct(
        protected AccountMembershipLifecycleService $lifecycleService,
    ) {}

    public function execute(
        AccountMembership $membership,
        User $actor,
        ?string $reason = null,
    ): AccountMembership {
        return $this->lifecycleService->revoke(
            membership: $membership,
            actor: $actor,
            reason: $reason,
        );
    }
}
