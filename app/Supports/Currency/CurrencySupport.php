<?php

namespace App\Supports\Currency;

class CurrencySupport
{
    public function default(): string
    {
        return (string) config('currencies.default', 'EUR');
    }

    public function supported(): array
    {
        return (array) config('currencies.supported', []);
    }

    public function codes(): array
    {
        return array_keys($this->supported());
    }

    public function isSupported(string $code): bool
    {
        return array_key_exists(strtoupper($code), $this->supported());
    }

    public function normalize(string $code): ?string
    {
        $code = strtoupper(trim($code));

        return $this->isSupported($code) ? $code : null;
    }

    public function options(): array
    {
        return array_values($this->supported());
    }

    public function for(string $code): ?array
    {
        $code = strtoupper(trim($code));

        return $this->supported()[$code] ?? null;
    }
}
