<?php

namespace App\Actions\Sharing;

use App\Exceptions\CannotRegisterFromAccountInvitationException;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\User;
use App\Services\Sharing\AccountMembershipService;
use App\Services\UserProvisioningService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUserFromAccountInvitationAction
{
    public function __construct(
        protected ResolveAccountInvitationAction $resolveAction,
        protected AccountMembershipService $membershipService,
        protected UserProvisioningService $userProvisioningService,
    ) {}

    /**
     * @return array{user: User, membership: AccountMembership}
     *
     * @throws \Throwable
     */
    public function execute(
        AccountInvitation $accountInvitation,
        string $plainToken,
        string $firstName,
        string $lastName,
        string $password,
    ): array {
        $resolved = $this->resolveAction->execute(
            accountInvitation: $accountInvitation,
            plainToken: $plainToken,
            authenticatedUser: null,
        );

        if ($resolved['state'] !== 'registration_required') {
            throw new CannotRegisterFromAccountInvitationException('This invitation cannot be used for registration.');
        }

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($accountInvitation->email)])
            ->first();

        if ($existingUser) {
            throw new CannotRegisterFromAccountInvitationException('A user with this email already exists.');
        }

        return DB::transaction(function () use ($accountInvitation, $plainToken, $firstName, $lastName, $password) {
            $user = User::query()->create([
                'name' => $firstName,
                'surname' => $lastName,
                'email' => $accountInvitation->email,
                'password' => Hash::make($password),
            ]);

            $user = $this->userProvisioningService->provisionApplicationUser($user);
            $user->suppressWelcomeAfterVerification = true;
            $user->markEmailAsVerified();

            $membership = $this->membershipService->acceptInvitation(
                invitation: $accountInvitation->fresh(),
                user: $user,
                plainToken: $plainToken,
            );

            Auth::login($user);

            return [
                'user' => $user,
                'membership' => $membership,
            ];
        });
    }
}
