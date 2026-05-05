<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\MonthlyFinancialRecapService;
use App\Services\Dashboard\MonthlyRecapPdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MonthlyRecapController extends Controller
{
    public function __construct(
        protected MonthlyFinancialRecapService $monthlyFinancialRecapService,
        protected MonthlyRecapPdfRenderer $monthlyRecapPdfRenderer,
    ) {}

    public function show(Request $request, int $year, int $month): InertiaResponse
    {
        $filters = $this->validatedFilters($request);

        return Inertia::render('dashboard/MonthlyRecap', [
            'recap' => $this->monthlyFinancialRecapService->forPeriod(
                $request->user(),
                $year,
                $month,
                $filters['account_scope'],
                $filters['account_uuid'],
            ),
        ]);
    }

    public function pdf(Request $request, int $year, int $month): Response
    {
        $filters = $this->validatedFilters($request);
        $recap = $this->monthlyFinancialRecapService->forPeriod(
            $request->user(),
            $year,
            $month,
            $filters['account_scope'],
            $filters['account_uuid'],
        );

        return response($this->monthlyRecapPdfRenderer->render($recap), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->monthlyRecapPdfRenderer->filename($recap).'"',
        ]);
    }

    /**
     * @return array{account_scope: string, account_uuid: string|null}
     */
    protected function validatedFilters(Request $request): array
    {
        /** @var array{account_scope?: string|null, account_uuid?: string|null} $validated */
        $validated = $request->validate([
            'account_scope' => ['nullable', 'string', 'in:all,owned,shared'],
            'account_uuid' => ['nullable', 'uuid'],
        ]);

        return [
            'account_scope' => $validated['account_scope'] ?? 'all',
            'account_uuid' => $validated['account_uuid'] ?? null,
        ];
    }
}
