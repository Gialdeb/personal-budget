<?php

namespace App\Models;

use App\Enums\AccountBalanceSnapshotSourceTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalanceSnapshot extends Model
{
    protected $fillable = [
        'account_id',
        'snapshot_date',
        'balance',
        'source_type',
        'import_id',
        'notes',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'balance' => 'decimal:2',
        'source_type' => AccountBalanceSnapshotSourceTypeEnum::class,
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
