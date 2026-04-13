<?php

namespace App\DTO\Currency;

use Carbon\CarbonImmutable;

readonly class ExchangeRateData
{
    public function __construct(
        public string $fromCurrencyCode,
        public string $toCurrencyCode,
        public string $rate,
        public CarbonImmutable $date,
        public string $source,
        public CarbonImmutable $fetchedAt,
        public string $resolvedFrom = 'provider',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from_currency_code' => $this->fromCurrencyCode,
            'to_currency_code' => $this->toCurrencyCode,
            'rate' => $this->rate,
            'date' => $this->date->toDateString(),
            'source' => $this->source,
            'fetched_at' => $this->fetchedAt->toIso8601String(),
            'resolved_from' => $this->resolvedFrom,
        ];
    }
}
