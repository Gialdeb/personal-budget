<?php

namespace App\Actions\Sharing;

use App\Enums\InvitationStatusEnum;
use App\Models\AccountInvitation;
use App\Models\User;

class ResolveAccountInvitationAction
{
    /**
     * @return array{
     *     state: string,
     *     invitation: AccountInvitation,
     *     email: string,
     *     role: string,
     *     expires_at: mixed,
     *     account: array{uuid: string|null, name: string|null},
     *     inviter: array{name: string|null},
     *     requires_registration: bool,
     *     requires_login: bool,
     *     can_accept: bool
     * }
     */
    public function execute(
        AccountInvitation $accountInvitation,
        string $plainToken,
        ?User $authenticatedUser = null,
    ): array {
        if (! hash_equals($accountInvitation->token_hash, hash('sha256', $plainToken))) {
            return $this->payload($accountInvitation, 'invalid', false, false, false);
        }

        if ($accountInvitation->status !== InvitationStatusEnum::PENDING) {
            return $this->payload($accountInvitation, 'already_processed', false, false, false);
        }

        if ($accountInvitation->expires_at && $accountInvitation->expires_at->isPast()) {
            return $this->payload($accountInvitation, 'expired', false, false, false);
        }

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($accountInvitation->email)])
            ->first();

        if (! $existingUser) {
            return $this->payload($accountInvitation, 'registration_required', true, false, false);
        }

        if (! $authenticatedUser) {
            return $this->payload($accountInvitation, 'login_required', false, true, false);
        }

        if (mb_strtolower((string) $authenticatedUser->email) !== mb_strtolower($accountInvitation->email)) {
            return $this->payload($accountInvitation, 'email_mismatch', false, false, false);
        }

        return $this->payload($accountInvitation, 'ready_to_accept', false, false, true);
    }

    protected function payload(
        AccountInvitation $accountInvitation,
        string $state,
        bool $requiresRegistration,
        bool $requiresLogin,
        bool $canAccept,
    ): array {
        $accountInvitation->loadMissing(['account', 'invitedBy']);

        return [
            'state' => $state,
            'invitation' => $accountInvitation,
            'email' => $accountInvitation->email,
            'role' => $accountInvitation->role->value,
            'expires_at' => $accountInvitation->expires_at,
            'account' => [
                'uuid' => $accountInvitation->account?->uuid,
                'name' => $accountInvitation->account?->name,
            ],
            'inviter' => [
                'name' => $accountInvitation->invitedBy?->name,
            ],
            'requires_registration' => $requiresRegistration,
            'requires_login' => $requiresLogin,
            'can_accept' => $canAccept,
        ];
    }
}
