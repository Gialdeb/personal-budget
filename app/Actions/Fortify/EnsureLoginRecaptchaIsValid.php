<?php

namespace App\Actions\Fortify;

use App\Services\Security\RecaptchaV3Verifier;

class EnsureLoginRecaptchaIsValid
{
    public function __construct(
        protected RecaptchaV3Verifier $recaptchaV3Verifier,
    ) {}

    public function __invoke($request, $next)
    {
        $this->recaptchaV3Verifier->assertValid($request, 'login');

        return $next($request);
    }
}
