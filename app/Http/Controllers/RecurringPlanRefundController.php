<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recurring\StoreRecurringPlanRefundRequest;
use App\Services\Recurring\RecurringPlanRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecurringPlanRefundController extends Controller
{
    public function preview(Request $request, string $plan, RecurringPlanRefundService $service): JsonResponse
    {
        return response()->json($service->preview($request->user(), $plan));
    }

    public function store(StoreRecurringPlanRefundRequest $request, string $plan, RecurringPlanRefundService $service): RedirectResponse
    {
        $service->refund($request->user(), $plan, $request->validated());

        return back()->with('success', __('recurring_refunds.created'));
    }
}
