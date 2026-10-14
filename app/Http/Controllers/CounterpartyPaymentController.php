<?php

namespace App\Http\Controllers;

use App\Http\Requests\Counterparties\CounterpartyOperationRequest;
use App\Models\CounterpartyLedgerEntry;
use App\Models\CounterpartyPayment;
use App\Models\CounterpartyRelation;
use App\Services\Counterparties\CounterpartyPaymentService;
use App\Services\Counterparties\CounterpartyViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CounterpartyPaymentController extends Controller
{
    public function show(Request $request, CounterpartyRelation $relation, CounterpartyViewService $view): Response
    {
        return Inertia::render('counterparties/Show', [...$view->show($request->user(), $relation), 'initialScope' => $request->query('scope') === 'shared' ? 'shared' : 'all']);
    }

    public function activate(CounterpartyOperationRequest $request, CounterpartyRelation $relation, CounterpartyPaymentService $service): JsonResponse|RedirectResponse
    {
        return $this->result($request, $relation, $service->activate($request->user(), $relation, $request->validated('operation_key')));
    }

    public function store(CounterpartyOperationRequest $request, CounterpartyRelation $relation, CounterpartyPaymentService $service): JsonResponse|RedirectResponse
    {
        return $this->result($request, $relation, $service->create($request->user(), $relation, $request->validated()));
    }

    public function update(CounterpartyOperationRequest $request, CounterpartyRelation $relation, CounterpartyPayment $payment, CounterpartyPaymentService $service): JsonResponse|RedirectResponse
    {
        return $this->result($request, $relation, $service->revise($request->user(), $relation, $payment, $request->validated()));
    }

    public function allocations(CounterpartyOperationRequest $request, CounterpartyRelation $relation, CounterpartyPayment $payment, CounterpartyPaymentService $service): JsonResponse|RedirectResponse
    {
        return $this->result($request, $relation, $service->reallocate($request->user(), $relation, $payment, $request->validated()));
    }

    public function destroy(CounterpartyOperationRequest $request, CounterpartyRelation $relation, CounterpartyPayment $payment, CounterpartyPaymentService $service): JsonResponse|RedirectResponse
    {
        return $this->result($request, $relation, $service->cancel($request->user(), $relation, $payment, $request->validated()));
    }

    public function returnMoney(CounterpartyOperationRequest $request, CounterpartyRelation $relation, CounterpartyPayment $payment, CounterpartyPaymentService $service): JsonResponse|RedirectResponse
    {
        return $this->result($request, $relation, $service->returnMoney($request->user(), $relation, $payment, $request->validated()));
    }

    public function compensate(CounterpartyOperationRequest $request, CounterpartyRelation $relation, CounterpartyPaymentService $service): JsonResponse|RedirectResponse
    {
        return $this->result($request, $relation, $service->compensate($request->user(), $relation, $request->validated()));
    }

    public function reverseCompensation(CounterpartyOperationRequest $request, CounterpartyRelation $relation, CounterpartyLedgerEntry $entry, CounterpartyPaymentService $service): JsonResponse|RedirectResponse
    {
        return $this->result($request, $relation, $service->reverseCompensation($request->user(), $relation, $entry, $request->validated()));
    }

    private function result(Request $request, CounterpartyRelation $relation, array $result): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json($result) : to_route('counterparties.show', $relation);
    }
}
