<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use App\Supports\DashboardPeriodResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();

        ['year' => $year, 'month' => $month] = DashboardPeriodResolver::resolve($request, $user);

        DashboardPeriodResolver::persist($user, $year, $month);

        $data = $this->dashboardService->build($user, $year, $month);

        if ($request->routeIs('dashboard.data') || $request->expectsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Dashboard', [
            'dashboard' => $data,
        ]);
    }
}
