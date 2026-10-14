<?php

namespace App\Enums;

enum CounterpartyPaymentDirectionEnum: string
{
    case RECEIVED = 'received';
    case SENT = 'sent';

    public function balanceSign(): int
    {
        return $this === self::RECEIVED ? -1 : 1;
    }
}
