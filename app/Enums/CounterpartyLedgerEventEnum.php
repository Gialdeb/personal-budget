<?php

namespace App\Enums;

enum CounterpartyLedgerEventEnum: string
{
    case OBLIGATION_RECOGNIZED = 'obligation_recognized';
    case OBLIGATION_REVISED = 'obligation_revised';
    case PAYMENT_RECORDED = 'payment_recorded';
    case COMPENSATION = 'compensation';
    case REVERSAL = 'reversal';
}
