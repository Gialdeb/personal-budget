<?php

namespace App\Http\Resources;

use App\Services\Counterparties\CounterpartyAuthority;
use App\Services\Counterparties\CounterpartyProjectionService;
use App\Support\CurrencyMinorUnits;
use App\Support\MoneyAmount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditDebtItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authority = app(CounterpartyAuthority::class);
        $loaded = $this->resource->relationLoaded('activeCommonObligation');
        $obligation = $loaded ? $this->activeCommonObligation : $authority->itemObligation($this->resource);
        if ($obligation !== null && ($obligation->relation->user_id !== $this->user_id || $obligation->relation->counterparty_id !== $this->counterparty_id || $obligation->relation->currency_code !== $this->currency_code)) {
            $obligation = null;
        }
        if ($obligation === null) {
            $paid = $this->legacyPaidCents();
            $compensated = 0;
            $remaining = $this->legacyRemainingCents();
        } else {
            $paidMinor = $loaded ? CurrencyMinorUnits::sum($obligation->allocationEntries->whereNotNull('payment_id')->pluck('amount_minor')) : $authority->paid($obligation);
            $compensatedMinor = $loaded ? CurrencyMinorUnits::sum($obligation->allocationEntries->whereNotNull('compensation_entry_id')->pluck('amount_minor')) : $authority->compensated($obligation);
            $remainingMinor = $loaded
                ? CurrencyMinorUnits::add(abs(CurrencyMinorUnits::sum($obligation->ledgerEntries->pluck('signed_minor'))), -CurrencyMinorUnits::sum($obligation->allocationEntries->pluck('amount_minor')))
                : app(CounterpartyProjectionService::class)->remaining($obligation);
            $toCents = fn (int $minor): int => MoneyAmount::cents(CurrencyMinorUnits::toDecimal($minor, $this->currency_code));
            $paid = $toCents($paidMinor);
            $compensated = $toCents($compensatedMinor);
            $remaining = $toCents($remainingMinor);
        }
        $relation = $obligation?->relation;
        if ($relation === null) {
            $party = $this->resource->relationLoaded('counterparty') ? $this->counterparty : null;
            $relation = $party?->relationLoaded('activeCommonRelations')
                ? $party->activeCommonRelations->first(fn ($relation): bool => $relation->user_id === $this->user_id && $relation->currency_code === $this->currency_code)
                : $authority->activeRelation($this->user_id, $this->counterparty_id, $this->currency_code);
        }
        $status = $paid + $compensated <= 0 ? 'open' : ($remaining > 0 ? 'partial' : 'settled');

        return [
            'uuid' => $this->uuid,
            'origin' => 'manual',
            'read_only' => false,
            'source_url' => null,
            'type' => $this->type?->value,
            'description' => $this->description,
            'total_amount' => (string) $this->total_amount,
            'compensated_amount' => $obligation === null ? '0.00' : CurrencyMinorUnits::toDecimal($compensatedMinor, $this->currency_code),
            'common_relation_uuid' => $relation?->uuid,
            'paid_amount' => MoneyAmount::decimal($paid),
            'remaining_amount' => MoneyAmount::decimal($remaining),
            'currency_code' => $this->currency_code,
            'status' => $status,
            'due_date' => $this->due_date?->toDateString(),
            'note' => $this->note,
            'counterparty' => $this->whenLoaded('counterparty', fn () => $this->counterparty === null ? null : ['uuid' => $this->counterparty->uuid, 'name' => $this->counterparty->name]),
            'reference' => $this->whenLoaded('reference', fn () => $this->reference === null ? null : [
                'uuid' => $this->reference->uuid,
                'name' => $this->reference->name,
                'label' => $this->reference->name,
            ], $this->reference_id === null ? null : ['id' => $this->reference_id]),
            'account' => $this->whenLoaded('account', fn () => $this->account === null ? null : [
                'value' => $this->account->uuid,
                'uuid' => $this->account->uuid,
                'label' => $this->account->name,
                'name' => $this->account->name,
                'currency_code' => $this->account->currency_code,
            ]),
            'category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'value' => $this->category->uuid,
                'uuid' => $this->category->uuid,
                'label' => $this->category->name,
                'name' => $this->category->name,
            ]),
            'payments_count' => $this->payments_count ?? $this->payments()->count(),
            'payments' => $this->whenLoaded('payments', fn () => CreditDebtPaymentResource::collection($this->payments)->resolve($request), []),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
