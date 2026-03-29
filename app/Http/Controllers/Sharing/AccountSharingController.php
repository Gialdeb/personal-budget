<?php

namespace App\Http\Controllers\Sharing;

use App\Actions\Sharing\AcceptAccountInvitationAction;
use App\Actions\Sharing\InviteUserToAccountAction;
use App\Actions\Sharing\LeaveAccountAction;
use App\Actions\Sharing\RestoreAccountMembershipAction;
use App\Actions\Sharing\RevokeAccountMembershipAction;
use App\Actions\Sharing\UpdateAccountMembershipRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sharing\AcceptAccountInvitationRequest;
use App\Http\Requests\Sharing\InviteUserToAccountRequest;
use App\Http\Requests\Sharing\LeaveAccountMembershipRequest;
use App\Http\Requests\Sharing\RevokeAccountMembershipRequest;
use App\Http\Requests\Sharing\UpdateAccountMembershipRoleRequest;
use App\Http\Resources\Sharing\AccountInvitationResource;
use App\Http\Resources\Sharing\AccountMembershipResource;
use App\Models\Account;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AccountSharingController extends Controller
{
    use AuthorizesRequests;

    public function members(Account $account): JsonResponse
    {
        $this->authorize('viewMembers', $account);
        $this->ensureShareableAccount($account);

        $memberships = $account->memberships()
            ->with('user')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => AccountMembershipResource::collection($memberships),
        ]);
    }

    public function invitations(Account $account): JsonResponse
    {
        $this->authorize('viewInvitations', $account);
        $this->ensureShareableAccount($account);

        $invitations = $account->invitations()
            ->latest('id')
            ->get();

        return response()->json([
            'data' => AccountInvitationResource::collection($invitations),
        ]);
    }

    public function invite(
        InviteUserToAccountRequest $request,
        Account $account,
        InviteUserToAccountAction $action,
    ): JsonResponse {
        $this->authorize('invite', $account);
        $this->ensureShareableAccount($account);

        $result = $action->execute(
            account: $account,
            inviter: $request->user(),
            email: $request->string('email')->toString(),
            role: $request->string('role')->toString(),
            permissions: $request->input('permissions'),
            expiresAt: $request->date('expires_at'),
        );

        return response()->json([
            'message' => __('accounts.sharing.invite_created'),
            'data' => new AccountInvitationResource($result['invitation']),
            'meta' => [
                'plain_token' => $result['plain_token'],
            ],
        ], 201);
    }

    public function accept(
        AcceptAccountInvitationRequest $request,
        AccountInvitation $accountInvitation,
        AcceptAccountInvitationAction $action,
    ): JsonResponse {
        $this->authorize('accept', $accountInvitation);

        $membership = $action->execute(
            invitation: $accountInvitation,
            user: $request->user(),
            plainToken: $request->string('token')->toString(),
        );

        return response()->json([
            'message' => __('accounts.sharing.invite_accepted'),
            'data' => new AccountMembershipResource($membership->load('user')),
        ]);
    }

    public function leave(
        LeaveAccountMembershipRequest $request,
        AccountMembership $accountMembership,
        LeaveAccountAction $action,
    ): JsonResponse {
        $this->authorize('leave', $accountMembership);

        $membership = $action->execute(
            membership: $accountMembership,
            actor: $request->user(),
            reason: $request->input('reason'),
        );

        return response()->json([
            'message' => __('accounts.sharing.membership_left'),
            'data' => new AccountMembershipResource($membership->load('user')),
        ]);
    }

    public function revoke(
        RevokeAccountMembershipRequest $request,
        AccountMembership $accountMembership,
        RevokeAccountMembershipAction $action,
    ): JsonResponse {
        $this->authorize('revoke', $accountMembership);

        $membership = $action->execute(
            membership: $accountMembership,
            actor: $request->user(),
            reason: $request->input('reason'),
        );

        return response()->json([
            'message' => __('accounts.sharing.membership_revoked'),
            'data' => new AccountMembershipResource($membership->load('user')),
        ]);
    }

    public function updateRole(
        UpdateAccountMembershipRoleRequest $request,
        AccountMembership $accountMembership,
        UpdateAccountMembershipRoleAction $action,
    ): JsonResponse {
        $this->authorize('updateRole', $accountMembership);

        $membership = $action->execute(
            membership: $accountMembership,
            actor: $request->user(),
            role: $request->string('role')->toString(),
        );

        return response()->json([
            'message' => __('accounts.sharing.membership_role_updated'),
            'data' => new AccountMembershipResource($membership->load('user')),
        ]);
    }

    public function restore(
        AccountMembership $accountMembership,
        RestoreAccountMembershipAction $action,
    ): JsonResponse {
        $this->authorize('restore', $accountMembership);

        $membership = $action->execute(
            membership: $accountMembership,
            actor: request()->user(),
        );

        return response()->json([
            'message' => __('accounts.sharing.membership_restored'),
            'data' => new AccountMembershipResource($membership->load('user')),
        ]);
    }

    protected function ensureShareableAccount(Account $account): void
    {
        $account->loadMissing('accountType');

        if (! $account->supportsSharing()) {
            throw ValidationException::withMessages([
                'account' => __('accounts.sharing.unsupported_account'),
            ]);
        }
    }
}
