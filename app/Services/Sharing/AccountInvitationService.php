<?php

namespace App\Services\Sharing;

use App\Enums\AccountMembershipStatusEnum;
use App\Enums\InvitationStatusEnum;
use App\Events\Sharing\AccountInvitationCreated;
use App\Exceptions\CannotInviteToAccountException;
use App\Models\Account;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountInvitationService
{
    public function createInvitation(
        Account $account,
        User $inviter,
        string $email,
        string $role,
        ?array $permissions = null,
        ?\DateTimeInterface $expiresAt = null,
    ): array {
        $normalizedEmail = mb_strtolower(trim($email));

        $this->assertCanInvite($account, $inviter, $normalizedEmail);

        $created = DB::transaction(function () use ($account, $inviter, $normalizedEmail, $role, $permissions, $expiresAt) {
            $plainToken = Str::random(64);

            $invitation = AccountInvitation::query()->create([
                'uuid' => (string) Str::uuid(),
                'account_id' => $account->id,
                'household_id' => $account->household_id,
                'email' => $normalizedEmail,
                'role' => $role,
                'permissions' => $permissions,
                'invited_by_user_id' => $inviter->id,
                'token_hash' => hash('sha256', $plainToken),
                'status' => InvitationStatusEnum::PENDING,
                'expires_at' => $expiresAt,
            ]);

            return [
                'invitation' => $invitation,
                'plain_token' => $plainToken,
            ];
        });

        event(new AccountInvitationCreated(
            invitation: $created['invitation'],
            plainToken: $created['plain_token'],
        ));

        return $created;
    }

    protected function assertCanInvite(Account $account, User $inviter, string $normalizedEmail): void
    {
        if ((int) $account->user_id !== (int) $inviter->id) {
            throw new CannotInviteToAccountException('Only the original account owner can invite users to this account.');
        }

        if ($inviter->email && mb_strtolower($inviter->email) === $normalizedEmail) {
            throw new CannotInviteToAccountException('You cannot invite yourself to the same account.');
        }

        $targetUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if ($targetUser) {
            $existingMembership = AccountMembership::query()
                ->where('account_id', $account->id)
                ->where('user_id', $targetUser->id)
                ->first();

            if ($existingMembership && $existingMembership->status->value === AccountMembershipStatusEnum::ACTIVE->value) {
                throw new CannotInviteToAccountException('The invited user already has access to this account.');
            }
        }

        $pendingInvitationExists = AccountInvitation::query()
            ->where('account_id', $account->id)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->where('status', InvitationStatusEnum::PENDING)
            ->exists();

        if ($pendingInvitationExists) {
            throw new CannotInviteToAccountException('There is already a pending invitation for this email on this account.');
        }
    }
}
