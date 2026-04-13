<?php

namespace App\Exceptions;

use RuntimeException;

class ExchangeRateLookupException extends RuntimeException
{
    /**
     * @param  array<int, array{provider: string, message: string}>  $attempts
     */
    public static function providersFailed(
        string $fromCurrencyCode,
        string $toCurrencyCode,
        string $rateDate,
        array $attempts,
    ): self {
        $details = collect($attempts)
            ->map(fn (array $attempt): string => "{$attempt['provider']}: {$attempt['message']}")
            ->implode(' | ');

        return new self(sprintf(
            'Unable to resolve exchange rate %s/%s for %s. Provider attempts: %s',
            $fromCurrencyCode,
            $toCurrencyCode,
            $rateDate,
            $details !== '' ? $details : 'none',
        ));
    }

    public static function invalidResponse(
        string $provider,
        string $message,
    ): self {
        return new self(sprintf(
            'Exchange rate provider [%s] returned an invalid response: %s',
            $provider,
            $message,
        ));
    }
}
