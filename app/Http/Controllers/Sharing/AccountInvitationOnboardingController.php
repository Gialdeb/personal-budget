<?php

namespace App\Http\Controllers\Sharing;

use App\Actions\Sharing\AcceptAccountInvitationForAuthenticatedUserAction;
use App\Actions\Sharing\RegisterUserFromAccountInvitationAction;
use App\Actions\Sharing\ResolveAccountInvitationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sharing\AcceptAuthenticatedAccountInvitationRequest;
use App\Http\Requests\Sharing\RegisterFromAccountInvitationRequest;
use App\Http\Resources\Sharing\AccountMembershipResource;
use App\Http\Resources\Sharing\ResolvedAccountInvitationResource;
use App\Models\AccountInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountInvitationOnboardingController extends Controller
{
    public function show(
        Request $request,
        AccountInvitation $accountInvitation,
        ResolveAccountInvitationAction $action,
    ): JsonResponse {
        $token = (string) $request->query('token', '');

        $resolved = $action->execute(
            accountInvitation: $accountInvitation,
            plainToken: $token,
            authenticatedUser: $request->user(),
        );

        return response()->json([
            'message' => 'Invitation resolved successfully.',
            'data' => new ResolvedAccountInvitationResource($resolved),
        ]);
    }

    public function register(
        RegisterFromAccountInvitationRequest $request,
        AccountInvitation $accountInvitation,
        RegisterUserFromAccountInvitationAction $action,
    ): JsonResponse {
        $result = $action->execute(
            accountInvitation: $accountInvitation,
            plainToken: $request->string('token')->toString(),
            firstName: $request->string('first_name')->toString(),
            lastName: $request->string('last_name')->toString(),
            password: $request->string('password')->toString(),
        );

        return response()->json([
            'message' => 'Invitation registration completed successfully.',
            'data' => [
                'user' => [
                    'uuid' => $result['user']->uuid ?? null,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                ],
                'membership' => new AccountMembershipResource($result['membership']->load('user')),
            ],
        ], 201);
    }

    public function acceptAuthenticated(
        AcceptAuthenticatedAccountInvitationRequest $request,
        AccountInvitation $accountInvitation,
        AcceptAccountInvitationForAuthenticatedUserAction $action,
    ): JsonResponse {
        $membership = $action->execute(
            accountInvitation: $accountInvitation,
            user: $request->user(),
            plainToken: $request->string('token')->toString(),
        );

        return response()->json([
            'message' => 'Invitation accepted successfully.',
            'data' => new AccountMembershipResource($membership->load('user')),
        ]);
    }
}
