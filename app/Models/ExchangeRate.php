<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    protected $table = 'currency_exchange_rates';

    protected $fillable = [
        'rate_date',
        'base_currency_code',
        'quote_currency_code',
        'rate',
        'source',
        'fetched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'rate' => 'decimal:8',
            'fetched_at' => 'immutable_datetime',
        ];
    }

    public function scopeForPairOnDate(
        Builder $query,
        string $baseCurrencyCode,
        string $quoteCurrencyCode,
        CarbonInterface|string $rateDate,
    ): Builder {
        $normalizedDate = $rateDate instanceof CarbonInterface
            ? $rateDate->toDateString()
            : $rateDate;

        return $query
            ->where('base_currency_code', strtoupper($baseCurrencyCode))
            ->where('quote_currency_code', strtoupper($quoteCurrencyCode))
            ->whereDate('rate_date', $normalizedDate);
    }
}
