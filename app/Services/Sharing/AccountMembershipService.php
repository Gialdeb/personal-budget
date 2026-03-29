<?php

namespace App\Services\Sharing;

use App\Enums\AccountMembershipStatusEnum;
use App\Enums\InvitationStatusEnum;
use App\Enums\MembershipSourceEnum;
use App\Exceptions\InvalidAccountInvitationException;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\User;
use App\Services\Budgets\SharedAccountBudgetConvergenceService;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use App\Services\Recurring\SharedAccountRecurringConvergenceService;
use App\Services\TrackedItems\SharedAccountTrackedItemCatalogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountMembershipService
{
    public function __construct(
        protected SharedAccountCategoryTaxonomyService $sharedAccountCategoryTaxonomyService,
        protected SharedAccountTrackedItemCatalogService $sharedAccountTrackedItemCatalogService,
        protected SharedAccountRecurringConvergenceService $sharedAccountRecurringConvergenceService,
        protected SharedAccountBudgetConvergenceService $sharedAccountBudgetConvergenceService,
    ) {}

    public function acceptInvitation(AccountInvitation $invitation, User $user, string $plainToken): AccountMembership
    {
        $this->assertInvitationCanBeAccepted($invitation, $user, $plainToken);

        return DB::transaction(function () use ($invitation, $user) {
            $membership = AccountMembership::query()
                ->where('account_id', $invitation->account_id)
                ->where('user_id', $user->id)
                ->first();

            if ($membership) {
                $membership->role = $invitation->role;
                $membership->permissions = $invitation->permissions;
                $membership->status = AccountMembershipStatusEnum::ACTIVE;
                $membership->household_id = $invitation->household_id;
                $membership->restored_at = now();
                $membership->restored_by_user_id = $invitation->invited_by_user_id;
                $membership->left_at = null;
                $membership->left_reason = null;
                $membership->revoked_at = null;
                $membership->revoked_by_user_id = null;
                $membership->save();
            } else {
                $membership = AccountMembership::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'account_id' => $invitation->account_id,
                    'user_id' => $user->id,
                    'household_id' => $invitation->household_id,
                    'role' => $invitation->role,
                    'status' => AccountMembershipStatusEnum::ACTIVE,
                    'permissions' => $invitation->permissions,
                    'granted_by_user_id' => $invitation->invited_by_user_id,
                    'source' => MembershipSourceEnum::INVITATION,
                    'joined_at' => now(),
                ]);
            }

            $invitation->status = InvitationStatusEnum::ACCEPTED;
            $invitation->accepted_by_user_id = $user->id;
            $invitation->accepted_at = now();
            $invitation->save();

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

    protected function assertInvitationCanBeAccepted(AccountInvitation $invitation, User $user, string $plainToken): void
    {
        if ($invitation->status !== InvitationStatusEnum::PENDING) {
            throw new InvalidAccountInvitationException('Invitation is not pending.');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            throw new InvalidAccountInvitationException('Invitation has expired.');
        }

        if (! $user->email || mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            throw new InvalidAccountInvitationException('Invitation email does not match authenticated user.');
        }

        if (! hash_equals($invitation->token_hash, hash('sha256', $plainToken))) {
            throw new InvalidAccountInvitationException('Invitation token is invalid.');
        }
    }
}
