<?php

namespace App\Supports\Locale;

use App\Models\User;

class LocaleResolver
{
    public function current(?User $user = null): string
    {
        $locale = $user?->locale;

        if (is_string($locale) && $this->isSupported($locale)) {
            return $locale;
        }

        return (string) config('locales.default', 'it');
    }

    public function isSupported(string $locale): bool
    {
        return array_key_exists($locale, config('locales.supported', []));
    }

    public function available(): array
    {
        return array_values(config('locales.supported', []));
    }

    public function fallback(): string
    {
        return (string) config('locales.fallback', 'en');
    }
}
