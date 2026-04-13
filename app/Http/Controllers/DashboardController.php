<?php

namespace App\Http\Controllers;

use App\Enums\AccountTypeCodeEnum;
use App\Services\Billing\DashboardSupportPromptService;
use App\Services\Dashboard\DashboardService;
use App\Supports\ManagementContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected ManagementContextResolver $managementContextResolver,
        protected DashboardSupportPromptService $dashboardSupportPromptService,
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();

        ['year' => $year, 'month' => $month] = $this->managementContextResolver->resolveDashboard($request, $user);
        $accountScope = (string) $request->string('account_scope', 'all');
        $accountUuid = $request->filled('account_uuid')
            ? $request->string('account_uuid')->toString()
            : null;

        $this->managementContextResolver->persist($user, $year, $month);

        $data = $this->dashboardService->build($user, $year, $month, $accountScope, $accountUuid);

        if ($request->routeIs('dashboard.data') || $request->expectsJson()) {
            return response()->json($data);
        }

        $hasOperationalAccounts = $user->accounts()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query
                    ->whereNotNull('user_bank_id')
                    ->orWhereNotNull('bank_id')
                    ->orWhere('opening_balance', '!=', 0)
                    ->orWhereHas('accountType', function ($accountTypeQuery): void {
                        $accountTypeQuery->where('code', '!=', AccountTypeCodeEnum::CASH_ACCOUNT->value);
                    });
            })
            ->exists();
        $hasTransactions = $user->transactions()->exists();

        return Inertia::render('Dashboard', [
            'dashboard' => $data,
            'support_prompt' => $this->dashboardSupportPromptService->forUser($user),
            'quick_start' => [
                'show' => ! $hasOperationalAccounts && ! $hasTransactions,
            ],
        ]);
    }
}
