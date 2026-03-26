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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class AccountInvitationOnboardingController extends Controller
{
    public function show(
        Request $request,
        AccountInvitation $accountInvitation,
        ResolveAccountInvitationAction $action,
    ): JsonResponse|Response {
        $token = (string) $request->query('token', '');

        $resolved = $action->execute(
            accountInvitation: $accountInvitation,
            plainToken: $token,
            authenticatedUser: $request->user(),
        );

        if (! $request->expectsJson()) {
            $inviterLocale = $resolved['invitation']->invitedBy?->locale;

            if (is_string($inviterLocale) && $inviterLocale !== '' && $request->hasSession()) {
                $request->session()->put('locale', $inviterLocale);
                app()->setLocale($inviterLocale);
            }

            if ($resolved['state'] === 'login_required' && $request->user() === null) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return Inertia::render('auth/AccountInvitation', [
                'invitation' => (new ResolvedAccountInvitationResource($resolved))->resolve(),
                'token' => $token,
                'canRegister' => Features::enabled(Features::registration()),
            ]);
        }

        return response()->json([
            'message' => 'Invitation resolved successfully.',
            'data' => new ResolvedAccountInvitationResource($resolved),
        ]);
    }

    public function register(
        RegisterFromAccountInvitationRequest $request,
        AccountInvitation $accountInvitation,
        RegisterUserFromAccountInvitationAction $action,
    ): JsonResponse|RedirectResponse {
        $result = $action->execute(
            accountInvitation: $accountInvitation,
            plainToken: $request->string('token')->toString(),
            firstName: $request->string('first_name')->toString(),
            lastName: $request->string('last_name')->toString(),
            password: $request->string('password')->toString(),
        );

        if (! $request->expectsJson()) {
            return to_route('accounts.edit')->with('success', __('accounts.sharing.invite_accepted'));
        }

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
    ): JsonResponse|RedirectResponse {
        $membership = $action->execute(
            accountInvitation: $accountInvitation,
            user: $request->user(),
            plainToken: $request->string('token')->toString(),
        );

        if (! $request->expectsJson()) {
            return to_route('accounts.edit')->with('success', __('accounts.sharing.invite_accepted'));
        }

        return response()->json([
            'message' => 'Invitation accepted successfully.',
            'data' => new AccountMembershipResource($membership->load('user')),
        ]);
    }
}
