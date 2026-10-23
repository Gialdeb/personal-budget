<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Recurring\AutomaticPaymentVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AutomaticPaymentVerificationController extends Controller
{
    public function __construct(
        protected AutomaticPaymentVerificationService $verificationService,
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
    ) {}

    public function confirm(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeTransaction($request, $transaction);
        $this->verificationService->confirm($transaction);

        return back()->with('success', __('ui.payment_confirmed_e61f0af5'));
    }

    public function problem(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeTransaction($request, $transaction);
        $occurrence = $this->verificationService->reportProblem($transaction, $request->boolean('confirm_rectification'));

        return to_route('recurring-entries.show', $occurrence->recurringEntry->uuid)
            ->with('success', __('ui.posting_removed_the_occurrence_is_awaiting_payment_099900a8'));
    }

    protected function authorizeTransaction(Request $request, Transaction $transaction): void
    {
        abort_unless($this->accessibleAccountsQuery->canEditAccountId($request->user(), (int) $transaction->account_id), 403);
        $entry = $transaction->recurringOccurrence?->recurringEntry;
        abort_unless($entry !== null && $this->accessibleAccountsQuery->canEditAccountId($request->user(), (int) $entry->account_id), 403);
    }
}
