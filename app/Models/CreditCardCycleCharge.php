<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCardCycleCharge extends Model
{
    protected $fillable = [
        'credit_card_account_id',
        'linked_payment_account_id',
        'payment_transaction_id',
        'card_settlement_transaction_id',
        'cycle_start_date',
        'cycle_end_date',
        'payment_due_date',
        'statement_closing_day',
        'payment_day',
        'balance_at_cycle_end',
        'charged_amount',
        'processed_at',
        'meta',
    ];

    protected $casts = [
        'cycle_start_date' => 'date',
        'cycle_end_date' => 'date',
        'payment_due_date' => 'date',
        'balance_at_cycle_end' => 'decimal:2',
        'charged_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function creditCardAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'credit_card_account_id');
    }

    public function linkedPaymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'linked_payment_account_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'payment_transaction_id');
    }

    public function cardSettlementTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'card_settlement_transaction_id');
    }
}
