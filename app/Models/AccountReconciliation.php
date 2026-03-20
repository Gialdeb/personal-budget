<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountReconciliation extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'account_id',
        'reconciliation_date',
        'expected_balance',
        'actual_balance',
        'difference_amount',
        'adjustment_transaction_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'expected_balance' => 'decimal:2',
        'actual_balance' => 'decimal:2',
        'difference_amount' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function adjustmentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'adjustment_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
