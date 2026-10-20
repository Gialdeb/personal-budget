<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recurring\StoreCompoundRecurringEntryRequest;
use App\Models\CompoundRecurringComponent;
use App\Models\CompoundRecurringEntry;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Recurring\CompoundRecurringEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompoundRecurringEntryController extends Controller
{
    public function store(StoreCompoundRecurringEntryRequest $request, CompoundRecurringEntryService $service): RedirectResponse
    {
        $compound = $service->create($request->user(), $request->validated());

        return to_route('recurring-entries.show', $compound->uuid);
    }

    public function add(StoreCompoundRecurringEntryRequest $request, CompoundRecurringEntry $compoundRecurringEntry, CompoundRecurringEntryService $service): RedirectResponse
    {
        $service->add($request->user(), $compoundRecurringEntry, $request->validated('components'), $request->validated('reminder_days_before', []));

        return to_route('recurring-entries.show', $compoundRecurringEntry->uuid);
    }

    public function archive(Request $request, CompoundRecurringEntry $compoundRecurringEntry, CompoundRecurringComponent $component, CompoundRecurringEntryService $service): RedirectResponse
    {
        $service->archive($request->user(), $compoundRecurringEntry, $component);

        return to_route('recurring-entries.show', $compoundRecurringEntry->uuid)->with('success', __('compound_recurring.archived'));
    }

    public function show(Request $request, CompoundRecurringEntry $compoundRecurringEntry, AccessibleAccountsQuery $accounts): RedirectResponse
    {
        abort_unless($accounts->canViewAccountId($request->user(), $compoundRecurringEntry->account_id), 404);

        return to_route('recurring-entries.show', ['recurringEntry' => $compoundRecurringEntry->uuid, ...$request->query()]);
    }
}
