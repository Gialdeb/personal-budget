<?php

namespace App\Supports\Locale;

use App\Models\User;
use Illuminate\Http\Request;

class LocaleResolver
{
    public function current(Request $request, ?User $user = null): string
    {
        $user ??= $request->user();

        $userLocale = is_string($user?->locale) ? $this->normalize($user->locale) : null;
        if ($userLocale !== null) {
            return $userLocale;
        }

        if ($request->hasSession()) {
            $sessionLocale = $request->session()->get('locale');
            if (is_string($sessionLocale)) {
                $normalizedSessionLocale = $this->normalize($sessionLocale);

                if ($normalizedSessionLocale !== null) {
                    return $normalizedSessionLocale;
                }
            }
        }

        $browserLocale = $this->resolveFromBrowser($request);
        if ($browserLocale !== null) {
            return $browserLocale;
        }

        return $this->default();
    }

    public function resolveFromBrowser(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $normalizedLocale = $this->normalize($language);

            if ($normalizedLocale !== null) {
                return $normalizedLocale;
            }
        }

        return null;
    }

    public function normalize(string $locale): ?string
    {
        $locale = str_replace('_', '-', strtolower($locale));

        if ($this->isSupported($locale)) {
            return $locale;
        }

        $base = explode('-', $locale)[0] ?? null;

        if (is_string($base) && $this->isSupported($base)) {
            return $base;
        }

        return null;
    }

    public function isSupported(string $locale): bool
    {
        return array_key_exists($locale, config('locales.supported', []));
    }

    public function supportedCodes(): array
    {
        return array_keys(config('locales.supported', []));
    }

    public function available(): array
    {
        return array_values(config('locales.supported', []));
    }

    public function default(): string
    {
        return (string) config('locales.default', 'it');
    }

    public function fallback(): string
    {
        return (string) config('locales.fallback', 'en');
    }
}
