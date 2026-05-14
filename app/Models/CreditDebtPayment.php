<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditDebtPayment extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'user_id',
        'credit_debt_item_id',
        'transaction_id',
        'account_id',
        'amount',
        'currency_code',
        'paid_at',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CreditDebtItem::class, 'credit_debt_item_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForItem(Builder $query, CreditDebtItem $item): Builder
    {
        return $query->where('credit_debt_item_id', $item->id);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('paid_at')->orderByDesc('created_at')->orderByDesc('id');
    }
}
