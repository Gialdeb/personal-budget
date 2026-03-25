<?php

namespace App\Actions\Sharing;

use App\Models\Account;
use App\Models\AccountInvitation;
use App\Models\User;
use App\Services\Sharing\AccountInvitationService;

class InviteUserToAccountAction
{
    public function __construct(
        protected AccountInvitationService $invitationService,
    ) {}

    /**
     * @return array{invitation: AccountInvitation, plain_token: string}
     */
    public function execute(
        Account $account,
        User $inviter,
        string $email,
        string $role,
        ?array $permissions = null,
        ?\DateTimeInterface $expiresAt = null,
    ): array {
        return $this->invitationService->createInvitation(
            account: $account,
            inviter: $inviter,
            email: $email,
            role: $role,
            permissions: $permissions,
            expiresAt: $expiresAt,
        );
    }
}
