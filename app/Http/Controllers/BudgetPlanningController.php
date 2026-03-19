<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetPlanning\CopyBudgetPlanningYearRequest;
use App\Http\Requests\BudgetPlanning\UpdateBudgetCellRequest;
use App\Models\User;
use App\Services\BudgetPlanningService;
use App\Services\UserYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetPlanningController extends Controller
{
    public function __construct(
        protected BudgetPlanningService $budgetPlanningService,
        protected UserYearService $userYearService
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        $year = $this->resolveYear($request, $user);

        $this->persistActiveYear($user, $year);

        $data = $this->budgetPlanningService->build($user, $year);

        if ($request->routeIs('budget-planning.data') || $request->expectsJson()) {
            return response()->json($data);
        }

        return Inertia::render('budgets/Planning', [
            'budgetPlanning' => $data,
        ]);
    }

    public function updateCell(UpdateBudgetCellRequest $request): JsonResponse
    {
        return response()->json(
            $this->budgetPlanningService->updateCell(
                $request->user(),
                $request->validated()
            )
        );
    }

    public function copyPreviousYear(CopyBudgetPlanningYearRequest $request): JsonResponse
    {
        $year = $request->integer('year');

        return response()->json([
            'budgetPlanning' => $this->budgetPlanningService->copyPreviousYear(
                $request->user(),
                $year
            ),
            'message' => 'Valori copiati dal '.($year - 1)." al {$year}.",
        ]);
    }

    protected function resolveYear(Request $request, User $user): int
    {
        $availableYears = $this->budgetPlanningService->resolveAvailableYears($user);
        $fallbackYear = $user->settings?->active_year
            ?: (! empty($availableYears) ? max($availableYears) : now()->year);
        $requestedYear = (int) (
            $request->integer('year')
                ?: $user->settings?->active_year
                ?: session('dashboard_year')
                ?: $fallbackYear
        );

        if ($availableYears === []) {
            return $requestedYear;
        }

        return in_array($requestedYear, $availableYears, true)
            ? $requestedYear
            : $fallbackYear;
    }

    protected function persistActiveYear(User $user, int $year): void
    {
        session([
            'dashboard_year' => $year,
        ]);
        $this->userYearService->syncActiveYear($user, $year);
    }
}
