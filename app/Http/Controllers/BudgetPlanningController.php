<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetPlanning\CopyBudgetPlanningYearRequest;
use App\Http\Requests\BudgetPlanning\UpdateBudgetCellRequest;
use App\Services\BudgetPlanningService;
use App\Supports\ManagementContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetPlanningController extends Controller
{
    public function __construct(
        protected BudgetPlanningService $budgetPlanningService,
        protected ManagementContextResolver $managementContextResolver
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        $year = $this->managementContextResolver->resolveYearOnly($request, $user);

        $this->managementContextResolver->persist($user, $year, persistMonth: false);

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
}
