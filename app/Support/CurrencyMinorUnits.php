<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

final class CurrencyMinorUnits
{
    public static function precision(string $currency): int
    {
        $precision = config("currencies.supported.{$currency}.minor_unit");
        if (! is_int($precision) || $precision < 0 || $precision > 6) {
            throw new InvalidArgumentException('Unsupported currency precision.');
        }

        return $precision;
    }

    public static function fromDecimal(string $amount, string $currency): int
    {
        $precision = self::precision($currency);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', trim($amount), $parts)) {
            throw new InvalidArgumentException('Use an exact decimal monetary string.');
        }
        $fraction = $parts[3] ?? '';
        if (trim(substr($fraction, $precision), '0') !== '') {
            throw new InvalidArgumentException('Amount is not exactly representable in this currency.');
        }
        $digits = ltrim($parts[2].str_pad(substr($fraction, 0, $precision), $precision, '0'), '0') ?: '0';
        $maximum = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($maximum) || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            throw new OverflowException('Monetary amount exceeds the integer range.');
        }

        return ($parts[1] === '-' ? -1 : 1) * (int) $digits;
    }

    public static function toDecimal(int $amount, string $currency): string
    {
        $precision = self::precision($currency);
        if ($amount === PHP_INT_MIN) {
            throw new OverflowException('Monetary amount exceeds the supported range.');
        }
        $digits = str_pad((string) abs($amount), $precision + 1, '0', STR_PAD_LEFT);
        $decimal = $precision === 0 ? $digits : substr($digits, 0, -$precision).'.'.substr($digits, -$precision);

        return ($amount < 0 ? '-' : '').$decimal;
    }

    public static function add(int $left, int $right): int
    {
        if (($right > 0 && $left > PHP_INT_MAX - $right) || ($right < 0 && $left < -PHP_INT_MAX - $right)) {
            throw new OverflowException('Monetary sum exceeds the integer range.');
        }

        return $left + $right;
    }

    /** @param iterable<int> $amounts */
    public static function sum(iterable $amounts): int
    {
        $total = 0;
        foreach ($amounts as $amount) {
            $total = self::add($total, $amount);
        }

        return $total;
    }
}
