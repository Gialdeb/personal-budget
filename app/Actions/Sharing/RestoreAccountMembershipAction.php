<?php

namespace App\Actions\Sharing;

use App\Models\AccountMembership;
use App\Models\User;
use App\Services\Sharing\AccountMembershipLifecycleService;

class RestoreAccountMembershipAction
{
    public function __construct(
        protected AccountMembershipLifecycleService $lifecycleService,
    ) {}

    public function execute(
        AccountMembership $membership,
        User $actor,
    ): AccountMembership {
        return $this->lifecycleService->restore(
            membership: $membership,
            actor: $actor,
        );
    }
}
