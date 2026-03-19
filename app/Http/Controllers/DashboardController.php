<?php

namespace App\Http\Controllers;

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
        protected ManagementContextResolver $managementContextResolver
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();

        ['year' => $year, 'month' => $month] = $this->managementContextResolver->resolveDashboard($request, $user);

        $this->managementContextResolver->persist($user, $year, $month);

        $data = $this->dashboardService->build($user, $year, $month);

        if ($request->routeIs('dashboard.data') || $request->expectsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Dashboard', [
            'dashboard' => $data,
        ]);
    }
}
