<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditDebtPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'amount' => (string) $this->amount,
            'currency_code' => $this->currency_code,
            'paid_at' => $this->paid_at?->toDateString(),
            'note' => $this->note,
            'account' => $this->whenLoaded('account', fn () => [
                'value' => $this->account->uuid,
                'uuid' => $this->account->uuid,
                'label' => $this->account->name,
                'name' => $this->account->name,
                'currency_code' => $this->account->currency_code,
            ]),
            'transaction_uuid' => $this->whenLoaded('transaction', fn () => $this->transaction?->uuid),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
