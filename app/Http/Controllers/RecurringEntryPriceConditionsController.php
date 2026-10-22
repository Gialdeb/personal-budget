<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recurring\UpdateRecurringEntryPriceConditionsRequest;
use App\Models\RecurringEntry;
use Illuminate\Http\RedirectResponse;

class RecurringEntryPriceConditionsController extends Controller
{
    public function update(UpdateRecurringEntryPriceConditionsRequest $request, RecurringEntry $recurringEntry): RedirectResponse
    {
        $conditions = $request->validated();
        if (! $request->has('type')) {
            $conditions = array_filter($conditions, fn ($value): bool => $value !== null && $value !== '');
        }
        $recurringEntry->forceFill(['price_conditions' => $conditions === [] ? null : $conditions])->save();

        return to_route('recurring-entries.show', $recurringEntry->uuid)->with('success', __('recurring_price_conditions.saved'));
    }
}
