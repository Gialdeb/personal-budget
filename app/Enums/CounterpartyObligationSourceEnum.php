<?php

namespace App\Enums;

enum CounterpartyObligationSourceEnum: string
{
    case CREDIT_DEBT_ITEM = 'credit_debt_item';
    case SHARED_TRANSACTION = 'shared_transaction_share';
    case SHARED_LEDGER = 'shared_ledger_share';
    case OPENING_BALANCE = 'opening_balance';
}
