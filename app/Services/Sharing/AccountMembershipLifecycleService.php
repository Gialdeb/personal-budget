<?php

namespace App\Services\Sharing;

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Exceptions\CannotLeaveAccountMembershipException;
use App\Exceptions\CannotRestoreAccountMembershipException;
use App\Exceptions\CannotRevokeAccountMembershipException;
use App\Exceptions\CannotUpdateAccountMembershipRoleException;
use App\Models\AccountMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountMembershipLifecycleService
{
    public function leave(AccountMembership $membership, User $actor, ?string $reason = null): AccountMembership
    {
        if ((int) $membership->user_id !== (int) $actor->id) {
            throw new CannotLeaveAccountMembershipException('Users can only leave their own account membership.');
        }

        if ($membership->status !== AccountMembershipStatusEnum::ACTIVE) {
            throw new CannotLeaveAccountMembershipException('Only active memberships can be left.');
        }

        if ($this->wouldLeaveAccountWithoutOwner($membership)) {
            throw new CannotLeaveAccountMembershipException('The last active owner cannot leave the account.');
        }

        return DB::transaction(function () use ($membership, $reason) {
            $membership->status = AccountMembershipStatusEnum::LEFT;
            $membership->left_at = now();
            $membership->left_reason = $reason;
            $membership->save();

            return $membership->fresh();
        });
    }

    public function revoke(AccountMembership $membership, User $actor, ?string $reason = null): AccountMembership
    {
        if (! $this->isOriginalAccountOwner($membership, $actor)) {
            throw new CannotRevokeAccountMembershipException('Only the original account owner can revoke account memberships.');
        }

        if ($membership->status !== AccountMembershipStatusEnum::ACTIVE) {
            throw new CannotRevokeAccountMembershipException('Only active memberships can be revoked.');
        }

        if ($this->wouldLeaveAccountWithoutOwner($membership)) {
            throw new CannotRevokeAccountMembershipException('The last active owner cannot be revoked from the account.');
        }

        return DB::transaction(function () use ($membership, $actor, $reason) {
            $membership->status = AccountMembershipStatusEnum::REVOKED;
            $membership->revoked_at = now();
            $membership->revoked_by_user_id = $actor->id;
            $membership->left_reason = $reason;
            $membership->save();

            return $membership->fresh();
        });
    }

    public function restore(AccountMembership $membership, User $actor): AccountMembership
    {
        if (! $this->isOriginalAccountOwner($membership, $actor)) {
            throw new CannotRestoreAccountMembershipException('Only the original account owner can restore account memberships.');
        }

        if (! in_array($membership->status, [
            AccountMembershipStatusEnum::LEFT,
            AccountMembershipStatusEnum::REVOKED,
        ], true)) {
            throw new CannotRestoreAccountMembershipException('Only left or revoked memberships can be restored.');
        }

        return DB::transaction(function () use ($membership, $actor) {
            $membership->status = AccountMembershipStatusEnum::ACTIVE;
            $membership->restored_at = now();
            $membership->restored_by_user_id = $actor->id;
            $membership->left_at = null;
            $membership->left_reason = null;
            $membership->revoked_at = null;
            $membership->revoked_by_user_id = null;
            $membership->save();

            return $membership->fresh();
        });
    }

    public function updateRole(AccountMembership $membership, User $actor, string $role): AccountMembership
    {
        if (! $this->isOriginalAccountOwner($membership, $actor)) {
            throw new CannotUpdateAccountMembershipRoleException('Only the original account owner can update account membership roles.');
        }

        if ($membership->status !== AccountMembershipStatusEnum::ACTIVE) {
            throw new CannotUpdateAccountMembershipRoleException('Only active memberships can be updated.');
        }

        if ($membership->role === AccountMembershipRoleEnum::OWNER) {
            throw new CannotUpdateAccountMembershipRoleException('Owner memberships cannot be downgraded through this action.');
        }

        $targetRole = AccountMembershipRoleEnum::from($role);

        if (! in_array($targetRole, [
            AccountMembershipRoleEnum::VIEWER,
            AccountMembershipRoleEnum::EDITOR,
        ], true)) {
            throw new CannotUpdateAccountMembershipRoleException('Only viewer and editor roles can be assigned through this action.');
        }

        return DB::transaction(function () use ($membership, $targetRole) {
            $membership->role = $targetRole;
            $membership->save();

            return $membership->fresh();
        });
    }

    protected function isOriginalAccountOwner(AccountMembership $membership, User $actor): bool
    {
        $account = $membership->account()->first();

        return $account && (int) $account->user_id === (int) $actor->id;
    }

    protected function wouldLeaveAccountWithoutOwner(AccountMembership $membership): bool
    {
        if ($membership->role !== AccountMembershipRoleEnum::OWNER) {
            return false;
        }

        $activeOwnerCount = AccountMembership::query()
            ->where('account_id', $membership->account_id)
            ->where('role', AccountMembershipRoleEnum::OWNER)
            ->where('status', AccountMembershipStatusEnum::ACTIVE)
            ->count();

        return $activeOwnerCount <= 1;
    }
}
