<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recurring\AllocateOccurrencePaymentRequest;
use App\Http\Requests\Recurring\ConvertRecurringOccurrenceRequest;
use App\Http\Requests\Recurring\UpdateRecurringOccurrenceAmountRequest;
use App\Http\Requests\Recurring\UpdateRecurringOccurrenceRequest;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Recurring\AllocateOccurrencePaymentService;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\UndoRecurringOccurrenceConversionService;
use App\Services\Recurring\UpdateRecurringOccurrenceAmountService;
use App\Services\Recurring\UpdateRecurringOccurrenceService;
use App\Services\SharedExpenses\SharedExpenseAllocation;
use App\Services\SharedExpenses\SharedExpenseOccurrencePostingService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RecurringEntryOccurrenceController extends Controller
{
    public function __construct(
        protected RecurringEntryPostingService $postingService,
        protected SharedExpenseOccurrencePostingService $sharedExpenseOccurrencePostingService,
        protected RecurringEntryManagementService $managementService,
        protected UndoRecurringOccurrenceConversionService $undoConversionService,
        protected UpdateRecurringOccurrenceAmountService $updateAmountService,
        protected UpdateRecurringOccurrenceService $updateOccurrenceService,
        protected AccessibleAccountsQuery $accessibleAccountsQuery
    ) {}

    public function convert(
        ConvertRecurringOccurrenceRequest $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence
    ): RedirectResponse {
        $ownedOccurrence = $this->accessibleOccurrence($request, $recurringEntry, $occurrence, true);

        if ($this->occurrenceDate($ownedOccurrence)?->isFuture() && ! $request->boolean('confirm_future_date')) {
            throw ValidationException::withMessages([
                'occurrence' => __('transactions.validation.recurring_future_conversion_confirmation_required'),
            ]);
        }

        $paidBy = $ownedOccurrence->shared_expense_paid_by ?? $ownedOccurrence->recurringEntry?->shared_expense_paid_by;
        if ($paidBy === SharedExpenseAllocation::PAID_BY_COUNTERPARTY) {
            $this->sharedExpenseOccurrencePostingService->post($ownedOccurrence, $request->user());

            return to_route('recurring-entries.show', $recurringEntry->uuid)
                ->with('success', __('ui.shared_occurrence_recorded_a6f8d380'));
        }

        $transaction = $this->postingService->post($ownedOccurrence, $request->user(), $request->validated());

        return to_route('recurring-entries.show', $recurringEntry->uuid)
            ->with('success', __('transactions.flash.recurring_occurrence_converted', [
                'uuid' => $transaction->uuid,
            ]));
    }

    public function allocatePayment(AllocateOccurrencePaymentRequest $request, RecurringEntry $recurringEntry, RecurringEntryOccurrence $occurrence, AllocateOccurrencePaymentService $service): RedirectResponse
    {
        $occurrence = $this->accessibleOccurrence($request, $recurringEntry, $occurrence, true);
        $transaction = Transaction::query()->where('user_id', $request->user()->id)
            ->where('uuid', $request->validated('transaction_uuid'))->firstOrFail();
        $service->allocate($occurrence, $transaction, $request->user(), (string) $request->validated('amount'));

        return to_route('recurring-entries.show', $recurringEntry->uuid);
    }

    public function skip(
        Request $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence
    ): RedirectResponse {
        $this->managementService->skipOccurrence(
            $this->accessibleOccurrence($request, $recurringEntry, $occurrence, true)
        );

        return back()->with('success', __('ui.occurrence_skipped_b37cbdd0'));
    }

    public function restore(Request $request, RecurringEntry $recurringEntry, RecurringEntryOccurrence $occurrence): RedirectResponse
    {
        $this->managementService->restoreOccurrence(
            $this->accessibleOccurrence($request, $recurringEntry, $occurrence, true)
        );

        return back()->with('success', __('ui.occurrence_restored_e94521df'));
    }

    public function updateAmount(
        UpdateRecurringOccurrenceAmountRequest $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence,
    ): RedirectResponse {
        $this->updateAmountService->update(
            $this->accessibleOccurrence($request, $recurringEntry, $occurrence, true),
            $request->user(),
            (float) $request->validated('amount'),
        );

        if ($request->boolean('return_to_dashboard')) {
            return back()->with('success', __('transactions.flash.recurring_occurrence_amount_updated'));
        }

        return to_route('recurring-entries.show', [
            'recurringEntry' => $recurringEntry->uuid,
            'highlight' => $occurrence->uuid,
        ])->with('success', __('transactions.flash.recurring_occurrence_amount_updated'));
    }

    public function update(
        UpdateRecurringOccurrenceRequest $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence,
    ): RedirectResponse {
        $this->updateOccurrenceService->update(
            $this->accessibleOccurrence($request, $recurringEntry, $occurrence, true),
            $request->user(),
            $request->validated('due_date'),
            $request->validated('account_uuid'),
        );

        return to_route('recurring-entries.show', [
            'recurringEntry' => $recurringEntry->uuid,
            'highlight' => $occurrence->uuid,
        ])->with('success', __('transactions.flash.recurring_occurrence_updated'));
    }

    public function cancel(
        Request $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence
    ): RedirectResponse {
        $this->managementService->cancelOccurrence(
            $this->accessibleOccurrence($request, $recurringEntry, $occurrence, true)
        );

        return to_route('recurring-entries.show', $recurringEntry->uuid)
            ->with('success', __('ui.occurrence_canceled_dde75707'));
    }

    public function undoConversion(
        Request $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence
    ): RedirectResponse {
        $this->undoConversionService->undo(
            $this->accessibleOccurrence($request, $recurringEntry, $occurrence, true)
        );

        return to_route('recurring-entries.show', $recurringEntry->uuid)
            ->with('success', __('transactions.flash.recurring_conversion_undone'));
    }

    protected function accessibleOccurrence(
        Request $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence,
        bool $requireEdit = false,
    ): RecurringEntryOccurrence {
        abort_unless(
            $this->accessibleAccountsQuery->canViewAccountId($request->user(), (int) $recurringEntry->account_id),
            404
        );
        abort_unless($occurrence->recurring_entry_id === $recurringEntry->id, 404);

        if (
            $requireEdit
            && ! $this->accessibleAccountsQuery->canEditAccountId($request->user(), (int) $recurringEntry->account_id)
        ) {
            throw ValidationException::withMessages([
                'occurrence' => __('transactions.validation.account_read_only'),
            ]);
        }

        return $occurrence->fresh(['recurringEntry', 'convertedTransaction']);
    }

    protected function occurrenceDate(RecurringEntryOccurrence $occurrence): ?CarbonInterface
    {
        return $occurrence->due_date ?? $occurrence->expected_date;
    }
}
