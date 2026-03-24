<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recurring\ConvertRecurringOccurrenceRequest;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\UndoRecurringOccurrenceConversionService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RecurringEntryOccurrenceController extends Controller
{
    public function __construct(
        protected RecurringEntryPostingService $postingService,
        protected RecurringEntryManagementService $managementService,
        protected UndoRecurringOccurrenceConversionService $undoConversionService
    ) {}

    public function convert(
        ConvertRecurringOccurrenceRequest $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence
    ): RedirectResponse {
        $ownedOccurrence = $this->ownedOccurrence($request, $recurringEntry, $occurrence);

        if ($this->occurrenceDate($ownedOccurrence)?->isFuture() && ! $request->boolean('confirm_future_date')) {
            throw ValidationException::withMessages([
                'occurrence' => __('transactions.validation.recurring_future_conversion_confirmation_required'),
            ]);
        }

        $transaction = $this->postingService->post($ownedOccurrence, $request->validated());

        return to_route('recurring-entries.show', $recurringEntry->uuid)
            ->with('success', __('transactions.flash.recurring_occurrence_converted', [
                'uuid' => $transaction->uuid,
            ]));
    }

    public function skip(
        Request $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence
    ): RedirectResponse {
        $this->managementService->skipOccurrence(
            $this->ownedOccurrence($request, $recurringEntry, $occurrence)
        );

        return to_route('recurring-entries.show', $recurringEntry->uuid)
            ->with('success', 'Occorrenza saltata.');
    }

    public function cancel(
        Request $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence
    ): RedirectResponse {
        $this->managementService->cancelOccurrence(
            $this->ownedOccurrence($request, $recurringEntry, $occurrence)
        );

        return to_route('recurring-entries.show', $recurringEntry->uuid)
            ->with('success', 'Occorrenza annullata.');
    }

    public function undoConversion(
        Request $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence
    ): RedirectResponse {
        $this->undoConversionService->undo(
            $this->ownedOccurrence($request, $recurringEntry, $occurrence)
        );

        return to_route('recurring-entries.show', $recurringEntry->uuid)
            ->with('success', __('transactions.flash.recurring_conversion_undone'));
    }

    protected function ownedOccurrence(
        Request $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence
    ): RecurringEntryOccurrence {
        abort_unless($recurringEntry->user_id === $request->user()->id, 404);
        abort_unless($occurrence->recurring_entry_id === $recurringEntry->id, 404);

        return $occurrence->fresh(['recurringEntry', 'convertedTransaction']);
    }

    protected function occurrenceDate(RecurringEntryOccurrence $occurrence): ?CarbonInterface
    {
        return $occurrence->due_date ?? $occurrence->expected_date;
    }
}
