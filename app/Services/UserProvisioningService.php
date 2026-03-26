<?php

namespace App\Services;

use App\Models\User;
use App\Services\Accounts\AccountProvisioningService;
use App\Services\Categories\CategoryFoundationService;
use App\Supports\Currency\CurrencySupport;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserProvisioningService
{
    public function __construct(
        protected Request $request,
        protected LocaleResolver $localeResolver,
        protected CurrencySupport $currencySupport,
        protected AccountProvisioningService $accountProvisioningService,
        protected CategoryFoundationService $categoryFoundationService,
        protected UserYearService $userYearService,
    ) {}

    public function provisionApplicationUser(
        User $user,
        ?string $locale = null,
        ?string $formatLocale = null,
    ): User {
        $resolvedLocale = $this->localeResolver->normalize(
            $locale ?? $user->locale ?? $this->localeResolver->current($this->request, $user),
        ) ?? $this->localeResolver->default();

        $resolvedFormatLocale = $this->normalizeFormatLocale(
            $formatLocale ?? $user->format_locale,
            $resolvedLocale,
        );

        $dirtyAttributes = [];

        if (! is_string($user->locale) || $user->locale === '') {
            $dirtyAttributes['locale'] = $resolvedLocale;
        }

        if (! is_string($user->base_currency_code) || $user->base_currency_code === '') {
            $dirtyAttributes['base_currency_code'] = $this->currencySupport->default();
        }

        if (! is_string($user->format_locale) || $user->format_locale === '') {
            $dirtyAttributes['format_locale'] = $resolvedFormatLocale;
        }

        if ($dirtyAttributes !== []) {
            $user->forceFill($dirtyAttributes)->save();
        }

        if (! $user->hasRole('user')) {
            Role::findOrCreate('user', 'web');
            $user->assignRole('user');
        }

        $this->accountProvisioningService->ensureDefaultCashAccount($user);
        $this->categoryFoundationService->ensureForUser($user);
        $this->userYearService->ensureCurrentYearExists($user);

        return $user->fresh(['settings']);
    }

    protected function normalizeFormatLocale(?string $formatLocale, string $resolvedLocale): string
    {
        if (is_string($formatLocale) && $formatLocale !== '') {
            return $formatLocale;
        }

        return match ($resolvedLocale) {
            'en' => 'en-US',
            default => 'it-IT',
        };
    }
}
