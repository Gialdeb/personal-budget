<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\AccountReportExportService;
use App\Services\Reports\AccountReportPdfRenderer;
use App\Supports\ManagementContextResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AccountReportExportController extends Controller
{
    public function __construct(
        protected AccountReportExportService $accountReportExportService,
        protected AccountReportPdfRenderer $accountReportPdfRenderer,
        protected ManagementContextResolver $managementContextResolver,
    ) {}

    public function __invoke(Request $request): Response
    {
        ['year' => $year, 'month' => $month] = $this->managementContextResolver->resolveDashboard($request, $request->user());
        $filters = $this->validatedFilters($request);
        $report = $this->accountReportExportService->build($request->user(), $year, $month, $filters);

        return response($this->accountReportPdfRenderer->render($report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$report['filename'].'"',
        ]);
    }

    /**
     * @return array{year?: int|null, month?: int|null, period?: string|null, account_uuid?: string|null}
     */
    protected function validatedFilters(Request $request): array
    {
        /** @var array{year?: int|null, month?: int|null, period?: string|null, account_uuid?: string|null} $validated */
        $validated = $request->validate([
            'year' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'period' => ['nullable', 'string', 'in:annual,monthly,last_3_months,last_6_months,ytd'],
            'account_uuid' => ['nullable', 'uuid'],
        ]);

        return $validated;
    }
}
