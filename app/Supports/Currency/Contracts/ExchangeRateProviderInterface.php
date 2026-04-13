<?php

namespace App\Supports\Currency\Contracts;

use App\DTO\Currency\ExchangeRateData;
use Carbon\CarbonImmutable;

interface ExchangeRateProviderInterface
{
    public function key(): string;

    public function label(): string;

    public function fetch(
        string $fromCurrencyCode,
        string $toCurrencyCode,
        CarbonImmutable $rateDate,
    ): ExchangeRateData;
}
