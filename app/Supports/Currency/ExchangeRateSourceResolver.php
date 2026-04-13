<?php

namespace App\Supports\Currency;

class ExchangeRateSourceResolver
{
    /**
     * @return array{label: string, url: string|null}
     */
    public function resolve(string $source): array
    {
        return match (strtolower(trim($source))) {
            'frankfurter' => [
                'label' => 'Frankfurter',
                'url' => 'https://frankfurter.dev/',
            ],
            'fawaz' => [
                'label' => 'Fawaz exchange-api',
                'url' => 'https://github.com/fawazahmed0/exchange-api',
            ],
            'identity', 'legacy_identity' => [
                'label' => 'Identity',
                'url' => null,
            ],
            default => [
                'label' => strtoupper(trim($source)) !== '' ? trim($source) : 'Unknown',
                'url' => null,
            ],
        };
    }
}
