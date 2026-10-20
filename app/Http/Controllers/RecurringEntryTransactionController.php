<?php

namespace App\Http\Controllers;

use App\Enums\TransactionKindEnum;
use App\Http\Requests\Recurring\RefundRecurringTransactionRequest;
use App\Models\Transaction;
use App\Services\Recurring\TransactionRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RecurringEntryTransactionController extends Controller
{
    public function __construct(
        protected TransactionRefundService $refundService
    ) {}

    public function refund(
        RefundRecurringTransactionRequest $request,
        Transaction $transaction
    ): RedirectResponse {
        abort_unless($transaction->user_id === $request->user()->id, 404);
        $transaction->recurringOccurrence?->recurringEntry?->ensureComponentIsMutable();

        if (! in_array($transaction->kind, [
            TransactionKindEnum::MANUAL,
            TransactionKindEnum::SCHEDULED,
        ], true)) {
            throw ValidationException::withMessages([
                'transaction' => __('ui.refunds_are_only_allowed_for_manual_or_scheduled_transactions_56507205'),
            ]);
        }

        $refund = $this->refundService->refund($transaction, $request->validated());

        return back()->with('success', "Rimborso {$refund->uuid} creato correttamente.");
    }
}
