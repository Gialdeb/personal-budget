<?php

namespace App\Models;

use App\Enums\CreditDebtStatusEnum;
use App\Enums\CreditDebtTypeEnum;
use App\Models\Concerns\HasPublicUuid;
use App\Services\Counterparties\CounterpartyAuthority;
use App\Services\Counterparties\CounterpartyProjectionService;
use App\Services\Counterparties\CounterpartySourceGuard;
use App\Services\Counterparties\ManualCreditDebtShadowService;
use App\Support\CurrencyMinorUnits;
use App\Support\MoneyAmount;
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
        'counterparty_id',
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

    protected static function booted(): void
    {
        static::updating(fn (self $item) => app(CounterpartySourceGuard::class)->assertChange($item));
        static::deleting(fn (self $item) => app(CounterpartySourceGuard::class)->assertChange($item, true));
        static::saved(function (self $item): void {
            if (app(CounterpartyAuthority::class)->activeRelation($item->user_id, $item->counterparty_id, $item->currency_code) !== null) {
                $shadow = app(ManualCreditDebtShadowService::class);
                $item->wasRecentlyCreated ? $shadow->itemCreated($item) : $shadow->itemChanged($item);
            }
        });
        static::deleted(function (self $item): void {
            if (app(CounterpartyAuthority::class)->activeRelation($item->user_id, $item->counterparty_id, $item->currency_code) !== null) {
                app(ManualCreditDebtShadowService::class)->itemChanged($item, true);
            }
        });
    }

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

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(TrackedItem::class, 'counterparty_id');
    }

    public function paidCents(): int
    {
        $authority = app(CounterpartyAuthority::class);
        $obligation = $authority->itemObligation($this);

        return $obligation === null ? $this->legacyPaidCents() : MoneyAmount::cents(CurrencyMinorUnits::toDecimal($authority->paid($obligation), $this->currency_code));
    }

    public function compensatedAmount(): string
    {
        $authority = app(CounterpartyAuthority::class);
        $obligation = $authority->itemObligation($this);

        return $obligation === null ? '0.00' : CurrencyMinorUnits::toDecimal($authority->compensated($obligation), $this->currency_code);
    }

    public function legacyPaidCents(): int
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();

        return $payments->sum(fn (CreditDebtPayment $payment): int => MoneyAmount::cents((string) $payment->amount));
    }

    public function remainingCents(): int
    {
        $obligation = app(CounterpartyAuthority::class)->itemObligation($this);

        return $obligation === null ? $this->legacyRemainingCents() : MoneyAmount::cents(CurrencyMinorUnits::toDecimal(app(CounterpartyProjectionService::class)->remaining($obligation), $this->currency_code));
    }

    public function legacyRemainingCents(): int
    {
        return max(0, MoneyAmount::cents((string) $this->total_amount) - $this->legacyPaidCents());
    }

    public function paidAmount(): string
    {
        return MoneyAmount::decimal($this->paidCents());
    }

    public function remainingAmount(): string
    {
        return MoneyAmount::decimal($this->remainingCents());
    }

    public function status(): CreditDebtStatusEnum
    {
        $paidCents = $this->paidCents() + MoneyAmount::cents($this->compensatedAmount());

        if ($paidCents <= 0) {
            return CreditDebtStatusEnum::OPEN;
        }

        return $this->remainingCents() > 0 ? CreditDebtStatusEnum::PARTIAL : CreditDebtStatusEnum::SETTLED;
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
        $obligation = app(CounterpartyAuthority::class)->itemObligation($this);

        return ! $this->payments()->exists() && ($obligation === null || app(CounterpartyProjectionService::class)->allocatedToObligation($obligation) === 0);
    }

    public function canAcceptPayment(float|string $amount): bool
    {
        return MoneyAmount::cents((string) $amount) > 0 && MoneyAmount::cents((string) $amount) <= $this->remainingCents();
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
