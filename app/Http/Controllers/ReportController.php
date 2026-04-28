<?php

namespace App\Http\Controllers;

use App\Services\Reports\AccountVisionReportService;
use App\Services\Reports\CategoryAnalysisReportService;
use App\Services\Reports\CategoryBreakdownReportService;
use App\Services\Reports\PeriodOverviewReportService;
use App\Supports\ManagementContextResolver;
use App\Supports\Reports\CategoryAnalysisPdfExportBuilder;
use App\Supports\Reports\CategoryAnalysisXlsxExportBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        protected PeriodOverviewReportService $periodOverviewReportService,
        protected CategoryBreakdownReportService $categoryBreakdownReportService,
        protected CategoryAnalysisReportService $categoryAnalysisReportService,
        protected AccountVisionReportService $accountVisionReportService,
        protected ManagementContextResolver $managementContextResolver,
    ) {}

    public function index(Request $request): Response
    {
        $context = $this->reportContext($request);
        $this->managementContextResolver->persist(
            $request->user(),
            $context['year'],
            $context['month'],
        );

        return Inertia::render('reports/Index', [
            'reportContext' => $context,
            'reportSections' => $this->reportSections(),
        ]);
    }

    public function kpis(Request $request): Response
    {
        $defaultContext = $this->reportContext($request);
        $reportOverview = $this->periodOverviewReportService->build(
            $request->user(),
            $defaultContext['year'],
            $defaultContext['month'],
            $this->validatedKpiFilters($request),
        );

        $context = [
            'year' => $reportOverview['filters']['year'],
            'month' => $reportOverview['filters']['month'],
        ];

        $this->managementContextResolver->persist(
            $request->user(),
            $context['year'],
            $context['month'],
        );

        return Inertia::render('reports/Overview', [
            'reportContext' => $context,
            'reportSections' => $this->reportSections(),
            'activeReportSection' => $this->activeSection('kpis'),
            'reportOverview' => $reportOverview,
        ]);
    }

    public function trend(): RedirectResponse
    {
        return redirect()->route('reports.kpis');
    }

    public function categories(Request $request): Response
    {
        $defaultContext = $this->reportContext($request);
        $reportCategories = $this->categoryBreakdownReportService->build(
            $request->user(),
            $defaultContext['year'],
            $defaultContext['month'],
            $this->validatedCategoryFilters($request),
        );

        $context = [
            'year' => $reportCategories['filters']['year'],
            'month' => $reportCategories['filters']['month'],
        ];

        $this->managementContextResolver->persist(
            $request->user(),
            $context['year'],
            $context['month'],
        );

        return Inertia::render('reports/Categories', [
            'reportContext' => $context,
            'reportSections' => $this->reportSections(),
            'activeReportSection' => $this->activeSection('categories'),
            'reportCategories' => $reportCategories,
        ]);
    }

    public function categoryAnalysis(Request $request): Response
    {
        $defaultContext = $this->reportContext($request);
        $reportCategoryAnalysis = $this->categoryAnalysisReportService->build(
            $request->user(),
            $defaultContext['year'],
            $defaultContext['month'],
            $this->validatedCategoryAnalysisFilters($request),
        );

        $context = [
            'year' => $reportCategoryAnalysis['filters']['year'],
            'month' => $reportCategoryAnalysis['filters']['month'],
        ];

        $this->managementContextResolver->persist(
            $request->user(),
            $context['year'],
            $context['month'],
        );

        return Inertia::render('reports/CategoryAnalysis', [
            'reportContext' => $context,
            'reportSections' => $this->reportSections(),
            'activeReportSection' => $this->activeSection('category_analysis'),
            'reportCategoryAnalysis' => $reportCategoryAnalysis,
        ]);
    }

    public function exportCategoryAnalysis(Request $request, CategoryAnalysisXlsxExportBuilder $builder): BinaryFileResponse
    {
        $defaultContext = $this->reportContext($request);
        $reportCategoryAnalysis = $this->categoryAnalysisReportService->build(
            $request->user(),
            $defaultContext['year'],
            $defaultContext['month'],
            $this->validatedCategoryAnalysisFilters($request),
        );
        $export = $builder->build($reportCategoryAnalysis);

        return response()
            ->download($export['path'], $export['filename'], [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function exportCategoryAnalysisPdf(Request $request, CategoryAnalysisPdfExportBuilder $builder): BinaryFileResponse
    {
        $defaultContext = $this->reportContext($request);
        $reportCategoryAnalysis = $this->categoryAnalysisReportService->build(
            $request->user(),
            $defaultContext['year'],
            $defaultContext['month'],
            $this->validatedCategoryAnalysisFilters($request),
        );
        $export = $builder->build($reportCategoryAnalysis);

        return response()
            ->download($export['path'], $export['filename'], [
                'Content-Type' => 'application/pdf',
            ])
            ->deleteFileAfterSend(true);
    }

    public function accounts(Request $request): Response
    {
        $defaultContext = $this->reportContext($request);
        $reportAccounts = $this->accountVisionReportService->build(
            $request->user(),
            $defaultContext['year'],
            $defaultContext['month'],
            $this->validatedAccountFilters($request),
        );

        $context = [
            'year' => $reportAccounts['filters']['year'],
            'month' => $reportAccounts['filters']['month'],
        ];

        $this->managementContextResolver->persist(
            $request->user(),
            $context['year'],
            $context['month'],
        );

        return Inertia::render('reports/Accounts', [
            'reportContext' => $context,
            'reportSections' => $this->reportSections(),
            'activeReportSection' => $this->activeSection('accounts'),
            'reportAccounts' => $reportAccounts,
        ]);
    }

    protected function renderSection(Request $request, string $sectionKey): Response
    {
        $sections = $this->reportSections();
        $activeSection = collect($sections)->firstWhere('key', $sectionKey);

        abort_if($activeSection === null, 404);

        $context = $this->reportContext($request);
        $this->managementContextResolver->persist(
            $request->user(),
            $context['year'],
            $context['month'],
        );

        return Inertia::render('reports/Section', [
            'reportContext' => $context,
            'reportSections' => $sections,
            'activeReportSection' => $activeSection,
        ]);
    }

    /**
     * @return array<int, array{key: string, title: string, summary: string, status: string, href: string}>
     */
    protected function reportSections(): array
    {
        return collect([
            [
                'key' => 'kpis',
                'title' => __('reports.modules.kpi.title'),
                'summary' => __('reports.modules.kpi.description'),
                'status' => __('reports.modules.status.available'),
                'href' => route('reports.kpis'),
            ],
            [
                'key' => 'categories',
                'title' => __('reports.modules.categories.title'),
                'summary' => __('reports.modules.categories.description'),
                'status' => __('reports.modules.status.available'),
                'href' => route('reports.categories'),
            ],
            [
                'key' => 'category_analysis',
                'title' => __('reports.modules.categoryAnalysis.title'),
                'summary' => __('reports.modules.categoryAnalysis.description'),
                'status' => __('reports.modules.status.available'),
                'href' => route('reports.category-analysis'),
            ],
            [
                'key' => 'accounts',
                'title' => __('reports.modules.accounts.title'),
                'summary' => __('reports.modules.accounts.description'),
                'status' => __('reports.modules.status.available'),
                'href' => route('reports.accounts'),
            ],
        ])
            ->filter(fn (array $section): bool => (bool) config("features.reports.sections.{$section['key']}"))
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, title: string, summary: string, status: string, href: string}
     */
    protected function activeSection(string $sectionKey): array
    {
        $activeSection = collect($this->reportSections())->firstWhere('key', $sectionKey);

        abort_if($activeSection === null, 404);

        return $activeSection;
    }

    /**
     * @return array{year: int, month: int|null}
     */
    protected function reportContext(Request $request): array
    {
        $user = $request->user();
        ['year' => $year, 'month' => $month] = $this->managementContextResolver->resolveDashboard($request, $user);

        return [
            'year' => $year,
            'month' => $month,
        ];
    }

    /**
     * @return array{year?: int|null, month?: int|null, period?: string|null, account_uuid?: string|null}
     */
    protected function validatedKpiFilters(Request $request): array
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

    /**
     * @return array{
     *     year?: int|null,
     *     month?: int|null,
     *     period?: string|null,
     *     account_uuid?: string|null,
     *     focus?: string|null,
     *     exclude_internal?: bool|null
     * }
     */
    protected function validatedCategoryFilters(Request $request): array
    {
        /** @var array{
         *     year?: int|null,
         *     month?: int|null,
         *     period?: string|null,
         *     account_uuid?: string|null,
         *     focus?: string|null,
         *     exclude_internal?: bool|null
         * } $validated
         */
        $validated = $request->validate([
            'year' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'period' => ['nullable', 'string', 'in:annual,monthly,last_3_months,last_6_months,ytd'],
            'account_uuid' => ['nullable', 'uuid'],
            'focus' => ['nullable', 'string', 'in:all,income,expense,saving'],
            'exclude_internal' => ['nullable', 'boolean'],
        ]);

        return $validated;
    }

    /**
     * @return array{
     *     year?: int|null,
     *     month?: int|null,
     *     period?: string|null,
     *     account_uuid?: string|null,
     *     category_uuid?: string|null,
     *     subcategory_uuid?: string|null
     * }
     */
    protected function validatedCategoryAnalysisFilters(Request $request): array
    {
        /** @var array{
         *     year?: int|null,
         *     month?: int|null,
         *     period?: string|null,
         *     account_uuid?: string|null,
         *     category_uuid?: string|null,
         *     subcategory_uuid?: string|null
         * } $validated
         */
        $validated = $request->validate([
            'year' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'period' => ['nullable', 'string', 'in:annual,monthly,last_3_months,last_6_months,ytd'],
            'account_uuid' => ['nullable', 'uuid'],
            'category_uuid' => ['nullable', 'uuid'],
            'subcategory_uuid' => ['nullable', 'uuid'],
        ]);

        return $validated;
    }

    /**
     * @return array{year?: int|null, month?: int|null, period?: string|null, account_uuid?: string|null}
     */
    protected function validatedAccountFilters(Request $request): array
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
