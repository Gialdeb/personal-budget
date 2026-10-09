<?php

namespace App\Http\Controllers;

use App\Http\Requests\SharedExpenses\StoreSharedExpenseSettlementRequest;
use App\Models\SharedExpenseSettlement;
use App\Models\TrackedItem;
use App\Services\SharedExpenses\SharedExpenseSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SharedExpenseSettlementController extends Controller
{
    public function store(
        StoreSharedExpenseSettlementRequest $request,
        string $counterparty,
        SharedExpenseSettlementService $sharedExpenseSettlementService,
    ): RedirectResponse {
        $counterpartyModel = TrackedItem::query()
            ->sharedExpenseCounterparties($request->user()->id)
            ->where('uuid', $counterparty)
            ->firstOrFail();

        $sharedExpenseSettlementService->record(
            $request->user(),
            $counterpartyModel,
            $request->validated(),
        );

        return back()->with('success', __('ui.repayment_recorded_73aeae57'));
    }

    public function update(
        StoreSharedExpenseSettlementRequest $request,
        string $settlement,
        SharedExpenseSettlementService $sharedExpenseSettlementService,
    ): RedirectResponse {
        $settlementModel = $this->settlementForUser($request->user()->id, $settlement);
        $sharedExpenseSettlementService->update($request->user(), $settlementModel, $request->validated());

        return back()->with('success', __('ui.repayment_updated_0c2805d9'));
    }

    public function destroy(
        Request $request,
        string $settlement,
        SharedExpenseSettlementService $sharedExpenseSettlementService,
    ): RedirectResponse {
        $settlementModel = $this->settlementForUser($request->user()->id, $settlement);
        $sharedExpenseSettlementService->delete($request->user(), $settlementModel);

        return back()->with('success', __('ui.repayment_deleted_50621f13'));
    }

    private function settlementForUser(int $userId, string $uuid): SharedExpenseSettlement
    {
        return SharedExpenseSettlement::query()->where('user_id', $userId)->where('uuid', $uuid)->firstOrFail();
    }
}
