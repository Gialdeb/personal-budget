<?php

namespace App\Http\Controllers;

use App\Services\Transactions\TransactionNavigationService;
use App\Supports\ManagementContextResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionsController extends Controller
{
    public function __construct(
        protected ManagementContextResolver $managementContextResolver,
        protected TransactionNavigationService $transactionNavigationService
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();
        ['year' => $year] = $this->managementContextResolver->resolveDashboard($request, $user);
        $month = $this->transactionNavigationService->resolveLandingMonth(
            $user,
            $year,
            session('dashboard_month')
        );

        return redirect()->route('transactions.show', [
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        ['year' => $year, 'month' => $month] = $this->managementContextResolver->resolveTransactions($request, $user);

        if ((int) $request->route('year') !== $year || (int) $request->route('month') !== $month) {
            return redirect()->route('transactions.show', [
                'year' => $year,
                'month' => $month,
            ]);
        }

        $this->managementContextResolver->persist($user, $year, $month);

        $navigation = $this->transactionNavigationService->build($user, $year, $month);
        $period = CarbonImmutable::create($year, $month, 1)->locale('it');

        return Inertia::render('transactions/Show', [
            'transactionsPage' => [
                'year' => $year,
                'month' => $month,
                'month_label' => $period->translatedFormat('F'),
                'period_label' => $period->translatedFormat('F Y'),
                'records_count' => $navigation['summary']['records_count'],
                'last_recorded_at' => $navigation['summary']['last_recorded_at'],
            ],
        ]);
    }
}
