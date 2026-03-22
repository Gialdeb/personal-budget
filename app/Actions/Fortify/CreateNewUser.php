<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\Accounts\AccountProvisioningService;
use App\Services\UserYearService;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        protected Request $request,
        protected LocaleResolver $localeResolver,
        protected AccountProvisioningService $accountProvisioningService,
        protected UserYearService $userYearService,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::query()->forceCreate([
            'name' => $input['name'],
            'surname' => $input['surname'] ?? null,
            'email' => $input['email'],
            'password' => $input['password'],
            'locale' => $this->localeResolver->current($this->request),
        ]);

        $user->assignRole('user');

        $this->accountProvisioningService->ensureDefaultCashAccount($user);
        $this->userYearService->ensureCurrentYearExists($user);

        return $user;
    }
}
