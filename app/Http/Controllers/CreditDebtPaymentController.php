<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditDebts\StoreCreditDebtPaymentRequest;
use App\Http\Resources\CreditDebtPaymentResource;
use App\Models\CreditDebtItem;
use App\Models\CreditDebtPayment;
use App\Services\CreditDebts\CreditDebtPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CreditDebtPaymentController extends Controller
{
    public function store(StoreCreditDebtPaymentRequest $request, CreditDebtItem $creditDebtItem, CreditDebtPaymentService $service): CreditDebtPaymentResource|RedirectResponse
    {
        Gate::authorize('create', [CreditDebtPayment::class, $creditDebtItem]);

        $payment = $service->create($request->user(), $creditDebtItem, $request->validated());

        if ($request->expectsJson()) {
            return CreditDebtPaymentResource::make($payment);
        }

        return back()->with('success', 'Pagamento registrato.');
    }

    public function destroy(Request $request, CreditDebtItem $creditDebtItem, CreditDebtPayment $payment, CreditDebtPaymentService $service): Response|RedirectResponse
    {
        abort_unless($payment->credit_debt_item_id === $creditDebtItem->id, 404);

        Gate::authorize('delete', $payment);

        $service->delete($request->user(), $payment);

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back()->with('success', 'Pagamento eliminato.');
    }
}
