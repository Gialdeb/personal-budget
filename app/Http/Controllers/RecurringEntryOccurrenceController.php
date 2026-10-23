<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinancialIndexRequest;
use App\Http\Requests\Recurring\AllocateOccurrencePaymentRequest;
use App\Http\Requests\Recurring\ConvertRecurringOccurrenceRequest;
use App\Http\Requests\Recurring\ManageOccurrencePaymentRequest;
use App\Http\Requests\Recurring\UpdateRecurringOccurrenceAmountRequest;
use App\Http\Requests\Recurring\UpdateRecurringOccurrenceRequest;
use App\Models\CounterpartyPayment;
use App\Models\RecurringEntry;
use App\Models\RecurringEntryOccurrence;
use App\Models\Transaction;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Recurring\AllocateOccurrencePaymentService;
use App\Services\Recurring\ManageOccurrencePaymentService;
use App\Services\Recurring\RecurringEntryManagementService;
use App\Services\Recurring\RecurringEntryPostingService;
use App\Services\Recurring\UndoRecurringOccurrenceConversionService;
use App\Services\Recurring\UpdateRecurringOccurrenceAmountService;
use App\Services\Recurring\UpdateRecurringOccurrenceService;
use App\Services\SharedExpenses\SharedExpenseAllocation;
use App\Services\SharedExpenses\SharedExpenseOccurrencePostingService;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
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

    public function manage(ManageOccurrencePaymentRequest $request, RecurringEntry $recurringEntry, RecurringEntryOccurrence $occurrence, ManageOccurrencePaymentService $service): RedirectResponse
    {
        $service->manage($this->accessibleOccurrence($request, $recurringEntry, $occurrence, true), $request->user(), $request->validated());

        return back()->with('success', __('payment_management.updated'));
    }

    public function convert(
        ConvertRecurringOccurrenceRequest $request,
        RecurringEntry $recurringEntry,
        RecurringEntryOccurrence $occurrence
    ): RedirectResponse {
        $ownedOccurrence = $this->accessibleOccurrence($request, $recurringEntry, $occurrence, true);

        if ($request->boolean('return_to_dashboard')) {
            if ($recurringEntry->auto_create_transaction) {
                throw ValidationException::withMessages(['payment' => __('payment_management.automatic_confirmation_only')]);
            }
            if ($this->occurrenceDate($ownedOccurrence)?->isFuture()) {
                throw ValidationException::withMessages(['payment' => __('payment_management.future_confirmation')]);
            }
        }

        if ($this->occurrenceDate($ownedOccurrence)?->isFuture() && ! $request->boolean('confirm_future_date')) {
            throw ValidationException::withMessages([
                'occurrence' => __('transactions.validation.recurring_future_conversion_confirmation_required'),
            ]);
        }

        $paidBy = $request->validated('shared_expense_paid_by') ?? $ownedOccurrence->shared_expense_paid_by ?? $ownedOccurrence->recurringEntry?->shared_expense_paid_by;
        if ($paidBy === SharedExpenseAllocation::PAID_BY_COUNTERPARTY) {
            $this->sharedExpenseOccurrencePostingService->post($ownedOccurrence, $request->user(), $request->validated());

            return to_route('recurring-entries.show', $recurringEntry->uuid)
                ->with('success', __('ui.shared_occurrence_recorded_a6f8d380'));
        }

        $transaction = $this->postingService->post($ownedOccurrence, $request->user(), $request->validated());

        if ($request->boolean('return_to_dashboard')) {
            return back()->with('success', __('ui.payment_confirmed_e61f0af5'));
        }

        return to_route('transactions.show', [
            'year' => $transaction->transaction_date->year,
            'month' => $transaction->transaction_date->month,
            'highlight' => $transaction->uuid,
        ])
            ->with('success', __('transactions.flash.recurring_occurrence_converted', [
                'uuid' => $transaction->uuid,
            ]));
    }

    public function paymentCandidates(FinancialIndexRequest $request, RecurringEntry $recurringEntry, RecurringEntryOccurrence $occurrence): JsonResponse
    {
        $occurrence = $this->accessibleOccurrence($request, $recurringEntry, $occurrence, true);
        abort_unless($recurringEntry->user_id === $request->user()->id && $recurringEntry->shared_expense_counterparty_id === null, 404);
        $query = Transaction::query()->where('user_id', $request->user()->id)
            ->whereIn('account_id', app(AccessibleAccountsQuery::class)->editableIds($request->user()))
            ->where('status', 'confirmed')->whereIn('kind', ['manual', 'scheduled'])
            ->where('direction', $recurringEntry->direction)->where('currency', $recurringEntry->currency)
            ->where('is_transfer', false)->whereNull('recurring_entry_occurrence_id')->whereNull('shared_expense_counterparty_id')
            ->whereNull('refunded_transaction_id')->whereNull('related_transaction_id')
            ->whereDoesntHave('occurrencePaymentAllocations')->whereDoesntHave('creditDebtPayment')->whereDoesntHave('sharedExpenseSettlement')
            ->whereDoesntHave('refundTransaction', fn ($refund) => $refund->where('status', 'confirmed'))
            ->whereNotIn('id', CounterpartyPayment::query()->whereNotNull('transaction_id')->select('transaction_id'))
            ->whereNotIn('id', RecurringEntryOccurrence::query()->whereNotNull('converted_transaction_id')->select('converted_transaction_id'))
            ->whereNotIn('id', RecurringEntryOccurrence::query()->whereNotNull('matched_transaction_id')->select('matched_transaction_id'))
            ->with('account');
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->input('date_to'));
        }
        $rows = $query->orderByDesc('transaction_date')->orderByDesc('id')->simplePaginate(20);

        return response()->json([
            'transactions' => $rows->getCollection()->map(fn (Transaction $transaction): array => [
                'uuid' => $transaction->uuid, 'date' => $transaction->transaction_date->toDateString(),
                'amount' => (float) $transaction->amount, 'description' => $transaction->description,
                'account' => $transaction->account?->name,
            ])->values()->all(),
            'has_more' => $rows->hasMorePages(),
        ]);
    }

    public function allocatePayment(AllocateOccurrencePaymentRequest $request, RecurringEntry $recurringEntry, RecurringEntryOccurrence $occurrence, AllocateOccurrencePaymentService $service): RedirectResponse
    {
        $occurrence = $this->accessibleOccurrence($request, $recurringEntry, $occurrence, true);
        $transaction = Transaction::query()->where('user_id', $request->user()->id)
            ->where('uuid', $request->validated('transaction_uuid'))->firstOrFail();
        $service->allocate($occurrence, $transaction, $request->user(), (string) $request->validated('amount'));

        return to_route('recurring-entries.show', [$recurringEntry->uuid, 'highlight' => $occurrence->uuid]);
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

        if ($requireEdit && $recurringEntry->isArchivedComponent()) {
            throw ValidationException::withMessages(['occurrence' => __('compound_recurring.read_only')]);
        }

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
