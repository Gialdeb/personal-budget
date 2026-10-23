<?php

namespace App\Http\Requests\Recurring;

class PreviewRecurringPromotionRequest extends StoreRecurringEntryRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['tracked_item_uuid' => null, 'tracked_item_id' => null]);

        parent::prepareForValidation();
    }

    public function rules(): array
    {
        return [...parent::rules(), 'recurring_entry_uuid' => ['nullable', 'uuid']];
    }
}
