<?php

namespace App\Actions\Sharing;

use App\Models\AccountMembership;
use App\Models\User;
use App\Services\Sharing\AccountMembershipLifecycleService;

class UpdateAccountMembershipRoleAction
{
    public function __construct(
        protected AccountMembershipLifecycleService $lifecycleService,
    ) {}

    public function execute(
        AccountMembership $membership,
        User $actor,
        string $role,
    ): AccountMembership {
        return $this->lifecycleService->updateRole(
            membership: $membership,
            actor: $actor,
            role: $role,
        );
    }
}
