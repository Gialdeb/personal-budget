<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditDebtItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type?->value,
            'description' => $this->description,
            'total_amount' => (string) $this->total_amount,
            'paid_amount' => $this->paidAmount(),
            'remaining_amount' => $this->remainingAmount(),
            'currency_code' => $this->currency_code,
            'status' => $this->status()->value,
            'due_date' => $this->due_date?->toDateString(),
            'note' => $this->note,
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
