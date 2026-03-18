<?php

namespace App\Models;

use App\Enums\ScheduledEntryStatusEnum;
use App\Enums\TransactionDirectionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledEntry extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'scope_id',
        'category_id',
        'merchant_id',
        'title',
        'description',
        'direction',
        'expected_amount',
        'currency',
        'scheduled_date',
        'status',
        'matched_transaction_id',
        'notes',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'scheduled_date' => 'date',
        'direction' => TransactionDirectionEnum::class,
        'status' => ScheduledEntryStatusEnum::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function matchedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'matched_transaction_id');
    }
}
