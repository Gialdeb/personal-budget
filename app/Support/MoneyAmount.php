<?php

namespace App\Support;

use InvalidArgumentException;

final class MoneyAmount
{
    public static function cents(string $amount): int
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', trim($amount), $parts)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        $cents = ((int) $parts[2] * 100) + (int) str_pad($parts[3] ?? '', 2, '0');

        return $parts[1] === '-' ? -$cents : $cents;
    }

    /** Round part * active / total without overflowing the intermediate product. */
    public static function proportional(int $part, int $active, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }
        $quotient = 0;
        $remainder = 0;
        foreach (str_split(decbin($active)) as $bit) {
            $next = $remainder * 2 + ($bit === '1' ? abs($part) : 0);
            $quotient = $quotient * 2 + intdiv($next, $total);
            $remainder = $next % $total;
        }
        if ($remainder >= intdiv($total, 2) + $total % 2) {
            $quotient++;
        }

        return $part < 0 ? -$quotient : $quotient;
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
}
