<?php

namespace App\Models;

use App\Enums\CreditDebtStatusEnum;
use App\Enums\CreditDebtTypeEnum;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditDebtItem extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'user_id',
        'reference_id',
        'account_id',
        'category_id',
        'type',
        'description',
        'total_amount',
        'currency_code',
        'due_date',
        'note',
    ];

    protected $casts = [
        'type' => CreditDebtTypeEnum::class,
        'total_amount' => 'decimal:2',
        'due_date' => 'date',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reference(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class, 'reference_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CreditDebtPayment::class);
    }

    public function paidAmount(): string
    {
        return number_format((float) $this->payments()->sum('amount'), 2, '.', '');
    }

    public function remainingAmount(): string
    {
        return number_format(max(0, (float) $this->total_amount - (float) $this->paidAmount()), 2, '.', '');
    }

    public function status(): CreditDebtStatusEnum
    {
        $paidAmount = (float) $this->paidAmount();
        $totalAmount = (float) $this->total_amount;

        if ($paidAmount <= 0.0) {
            return CreditDebtStatusEnum::OPEN;
        }

        if ($paidAmount < $totalAmount) {
            return CreditDebtStatusEnum::PARTIAL;
        }

        return CreditDebtStatusEnum::SETTLED;
    }

    public function isSettled(): bool
    {
        return $this->status() === CreditDebtStatusEnum::SETTLED;
    }

    public function isPartial(): bool
    {
        return $this->status() === CreditDebtStatusEnum::PARTIAL;
    }

    public function canBeDeleted(): bool
    {
        return ! $this->payments()->exists();
    }

    public function canAcceptPayment(float|string $amount): bool
    {
        return (float) $amount > 0.0 && (float) $amount <= (float) $this->remainingAmount();
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('type', CreditDebtTypeEnum::CREDIT->value);
    }

    public function scopeDebts(Builder $query): Builder
    {
        return $query->where('type', CreditDebtTypeEnum::DEBIT->value);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereDoesntHave('payments');
    }

    public function scopePartial(Builder $query): Builder
    {
        return $query
            ->withSum('payments as paid_amount_sum', 'amount')
            ->having('paid_amount_sum', '>', 0)
            ->havingRaw('paid_amount_sum < total_amount');
    }

    public function scopeSettled(Builder $query): Builder
    {
        return $query
            ->withSum('payments as paid_amount_sum', 'amount')
            ->havingRaw('paid_amount_sum = total_amount');
    }

    public function scopeDueBefore(Builder $query, string $date): Builder
    {
        return $query->whereDate('due_date', '<=', $date);
    }

    public function scopeDueBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('due_date', [$from, $to]);
    }
}
