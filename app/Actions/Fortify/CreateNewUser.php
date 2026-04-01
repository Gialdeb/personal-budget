<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\Security\RecaptchaV3Verifier;
use App\Services\UserProvisioningService;
use App\Supports\Currency\CurrencySupport;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        protected Request $request,
        protected CurrencySupport $currencySupport,
        protected LocaleResolver $localeResolver,
        protected RecaptchaV3Verifier $recaptchaV3Verifier,
        protected UserProvisioningService $userProvisioningService,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['format_locale'] ??= 'it-IT';

        $this->recaptchaV3Verifier->assertValid($this->request, 'register');

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
            'base_currency_code' => $this->currencySupport->default(),
            'format_locale' => $input['format_locale'],
        ]);

        return $this->userProvisioningService->provisionApplicationUser(
            $user,
            locale: $input['locale'] ?? null,
            formatLocale: $input['format_locale'] ?? null,
        );
    }
}
