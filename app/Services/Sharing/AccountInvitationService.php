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
            throw new CannotInviteToAccountException(__('ui.only_the_original_account_owner_can_invite_users_to_this_account_01d333f6'));
        }

        if ($inviter->email && mb_strtolower($inviter->email) === $normalizedEmail) {
            throw new CannotInviteToAccountException(__('ui.you_cannot_invite_yourself_to_the_same_account_d69b1119'));
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
                throw new CannotInviteToAccountException(__('ui.the_invited_user_already_has_access_to_this_account_94519d0a'));
            }
        }

        $pendingInvitationExists = AccountInvitation::query()
            ->where('account_id', $account->id)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->where('status', InvitationStatusEnum::PENDING)
            ->exists();

        if ($pendingInvitationExists) {
            throw new CannotInviteToAccountException(__('ui.there_is_already_a_pending_invitation_for_this_email_on_this_acc_2cf1b17c'));
        }
    }
}
