<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recurring\StoreRecurringEntryManualCostRequest;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryManualCost;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RecurringEntryManualCostController extends Controller
{
    public function store(StoreRecurringEntryManualCostRequest $request, RecurringEntry $recurringEntry): RedirectResponse
    {
        $cost = $recurringEntry->manualCosts()->make();
        $this->save($request, $recurringEntry, $cost);

        return to_route('recurring-entries.show', $recurringEntry->uuid)->with('success', __('recurring_costs.saved'));
    }

    public function update(StoreRecurringEntryManualCostRequest $request, RecurringEntry $recurringEntry, RecurringEntryManualCost $manualCost): RedirectResponse
    {
        $this->save($request, $recurringEntry, $manualCost);

        return to_route('recurring-entries.show', $recurringEntry->uuid)->with('success', __('recurring_costs.saved'));
    }

    public function destroy(StoreRecurringEntryManualCostRequest $request, RecurringEntry $recurringEntry, RecurringEntryManualCost $manualCost): RedirectResponse
    {
        $manualCost->delete();

        return to_route('recurring-entries.show', $recurringEntry->uuid)->with('success', __('recurring_costs.deleted'));
    }

    private function save(StoreRecurringEntryManualCostRequest $request, RecurringEntry $entry, RecurringEntryManualCost $cost): void
    {
        try {
            $cost->fill([...$request->validated(), 'currency' => $entry->currency])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['amount' => __('recurring_costs.duplicate')]);
        }
    }
}
