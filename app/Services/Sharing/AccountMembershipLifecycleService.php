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
use App\Services\Budgets\SharedAccountBudgetConvergenceService;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use App\Services\Recurring\SharedAccountRecurringConvergenceService;
use App\Services\TrackedItems\SharedAccountTrackedItemCatalogService;
use Illuminate\Support\Facades\DB;

class AccountMembershipLifecycleService
{
    public function __construct(
        protected SharedAccountCategoryTaxonomyService $sharedAccountCategoryTaxonomyService,
        protected SharedAccountTrackedItemCatalogService $sharedAccountTrackedItemCatalogService,
        protected SharedAccountRecurringConvergenceService $sharedAccountRecurringConvergenceService,
        protected SharedAccountBudgetConvergenceService $sharedAccountBudgetConvergenceService,
    ) {}

    public function leave(AccountMembership $membership, User $actor, ?string $reason = null): AccountMembership
    {
        if ((int) $membership->user_id !== (int) $actor->id) {
            throw new CannotLeaveAccountMembershipException(__('ui.users_can_only_leave_their_own_account_membership_b74caab5'));
        }

        if ($membership->status !== AccountMembershipStatusEnum::ACTIVE) {
            throw new CannotLeaveAccountMembershipException(__('ui.only_active_memberships_can_be_left_fb257c11'));
        }

        if ($this->wouldLeaveAccountWithoutOwner($membership)) {
            throw new CannotLeaveAccountMembershipException(__('ui.the_last_active_owner_cannot_leave_the_account_d8515e9c'));
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
            throw new CannotRevokeAccountMembershipException(__('ui.only_the_original_account_owner_can_revoke_account_memberships_83c534bc'));
        }

        if ($membership->status !== AccountMembershipStatusEnum::ACTIVE) {
            throw new CannotRevokeAccountMembershipException(__('ui.only_active_memberships_can_be_revoked_a7eb9e65'));
        }

        if ($this->wouldLeaveAccountWithoutOwner($membership)) {
            throw new CannotRevokeAccountMembershipException(__('ui.the_last_active_owner_cannot_be_revoked_from_the_account_6be1953a'));
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
            throw new CannotRestoreAccountMembershipException(__('ui.only_the_original_account_owner_can_restore_account_memberships_d10d9779'));
        }

        if (! in_array($membership->status, [
            AccountMembershipStatusEnum::LEFT,
            AccountMembershipStatusEnum::REVOKED,
        ], true)) {
            throw new CannotRestoreAccountMembershipException(__('ui.only_left_or_revoked_memberships_can_be_restored_7dc90ab7'));
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

            $membership = $membership->fresh(['account']);

            if ($membership?->account !== null) {
                $this->sharedAccountBudgetConvergenceService->ensureForAccount($membership->account);
                $this->sharedAccountCategoryTaxonomyService->ensureForAccount($membership->account);
                $this->sharedAccountTrackedItemCatalogService->ensureForAccount($membership->account);
                $this->sharedAccountRecurringConvergenceService->ensureForAccount($membership->account);
            }

            return $membership;
        });
    }

    public function updateRole(AccountMembership $membership, User $actor, string $role): AccountMembership
    {
        if (! $this->isOriginalAccountOwner($membership, $actor)) {
            throw new CannotUpdateAccountMembershipRoleException(__('ui.only_the_original_account_owner_can_update_account_membership_ro_1984ead5'));
        }

        if ($membership->status !== AccountMembershipStatusEnum::ACTIVE) {
            throw new CannotUpdateAccountMembershipRoleException(__('ui.only_active_memberships_can_be_updated_2d883639'));
        }

        if ($membership->role === AccountMembershipRoleEnum::OWNER) {
            throw new CannotUpdateAccountMembershipRoleException(__('ui.owner_memberships_cannot_be_downgraded_through_this_action_d74efbb0'));
        }

        $targetRole = AccountMembershipRoleEnum::from($role);

        if (! in_array($targetRole, [
            AccountMembershipRoleEnum::VIEWER,
            AccountMembershipRoleEnum::EDITOR,
        ], true)) {
            throw new CannotUpdateAccountMembershipRoleException(__('ui.only_viewer_and_editor_roles_can_be_assigned_through_this_action_ba390408'));
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
