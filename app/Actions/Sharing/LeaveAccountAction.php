<?php

namespace App\Actions\Sharing;

use App\Models\AccountMembership;
use App\Models\User;
use App\Services\Sharing\AccountMembershipLifecycleService;

class LeaveAccountAction
{
    public function __construct(
        protected AccountMembershipLifecycleService $lifecycleService,
    ) {}

    public function execute(
        AccountMembership $membership,
        User $actor,
        ?string $reason = null,
    ): AccountMembership {
        return $this->lifecycleService->leave(
            membership: $membership,
            actor: $actor,
            reason: $reason,
        );
    }
}
