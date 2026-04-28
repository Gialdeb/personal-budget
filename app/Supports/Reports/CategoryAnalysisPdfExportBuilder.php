<?php

namespace App\Supports\Reports;

use App\Services\Pdf\TextPdfDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class CategoryAnalysisPdfExportBuilder
{
    /**
     * @param  array<string, mixed>  $report
     * @return array{path: string, filename: string}
     */
    public function build(array $report): array
    {
        $path = tempnam(sys_get_temp_dir(), 'category-analysis-').'.pdf';
        file_put_contents($path, $this->render($report));

        return [
            'path' => $path,
            'filename' => $this->filename($report),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function render(array $report): string
    {
        $pdf = new TextPdfDocument;
        $meta = $report['meta'];
        $summary = $report['summary'];
        $budget = $meta['budget'];
        $labels = $this->labels();
        $generatedAt = CarbonImmutable::now(config('app.timezone'));

        $pdf->reportHeader(
            'Soamco Budget',
            __('reports.categoryAnalysis.export.title').' - '.($meta['subcategory_label'] ?? $meta['category_label'] ?? __('reports.categoryAnalysis.fallbackCategory')),
            $labels['generated_at'].' '.$generatedAt->format('d/m/Y H:i'),
            $labels['header_subtitle'],
        );

        $pdf->summaryPanel(
            $meta['scope_label'],
            $meta['period_label'],
            (string) count($report['monthly_rows']),
            [
                ['label' => __('reports.categoryAnalysis.kpis.totalSpent'), 'value' => $summary['total_spent'], 'tone' => 'expense'],
                ['label' => __('reports.categoryAnalysis.kpis.averagePeriod'), 'value' => $summary['average_period'], 'tone' => 'neutral'],
                ['label' => __('reports.categoryAnalysis.kpis.bestMonth'), 'value' => trim(($summary['best_period_label'] ?? '-').' '.($summary['best_period_value'] ?? '')), 'tone' => 'positive'],
                ['label' => __('reports.categoryAnalysis.kpis.worstMonth'), 'value' => trim(($summary['worst_period_label'] ?? '-').' '.($summary['worst_period_value'] ?? '')), 'tone' => 'negative'],
                ['label' => __('reports.categoryAnalysis.budget.label'), 'value' => ($budget['supported'] ?? false) ? (string) $budget['total'] : $labels['not_available'], 'tone' => ($budget['status'] ?? '') === 'over' ? 'negative' : 'neutral'],
            ],
            [
                'title' => $labels['summary'],
                'subtitle' => $meta['insight']['message'],
                'movements' => $labels['periods'],
                'account' => __('reports.categoryAnalysis.category'),
                'period' => __('reports.categoryAnalysis.export.period'),
            ],
        );

        $pdf->section($labels['comparisons']);
        $pdf->keyValue(__('reports.categoryAnalysis.kpis.previousPeriod'), $this->comparisonLabel($report['comparisons']['previous_period']));
        $pdf->keyValue(__('reports.categoryAnalysis.kpis.previousYear'), $this->comparisonLabel($report['comparisons']['previous_year']));
        $pdf->keyValue(__('reports.categoryAnalysis.budget.variance'), ($budget['supported'] ?? false) ? (string) $budget['variance'] : $labels['not_available']);

        $pdf->section($labels['scope']);
        $pdf->keyValue($labels['analysis_scope'], (string) $meta['analysis_scope_label']);
        $pdf->keyValue($labels['actual_scope'], (string) $meta['actual_scope_description']);
        $pdf->keyValue($labels['budget_scope'], (string) $meta['budget_scope_description']);
        $pdf->keyValue($labels['comparison_scope'], (string) $meta['comparison_scope_description']);

        $pdf->section(__('reports.categoryAnalysis.table.title'));
        $pdf->table(
            [
                __('reports.categoryAnalysis.table.period'),
                __('reports.categoryAnalysis.table.spent'),
                __('reports.categoryAnalysis.budget.label'),
                __('reports.categoryAnalysis.budget.variance'),
                __('reports.categoryAnalysis.table.deltaPreviousYear'),
                __('reports.categoryAnalysis.table.dominantSubcategory'),
            ],
            collect($report['monthly_rows'])
                ->map(fn (array $row): array => [
                    $row['label'],
                    $row['spent'],
                    $row['budget'] ?? '-',
                    $row['budget_delta'] ?? '-',
                    $row['delta_previous_year'],
                    $row['dominant_subcategory_label'] ?? '-',
                ])
                ->all(),
            [64, 82, 82, 88, 92, 82],
        );

        if ($report['subcategory_breakdown']['nodes'] !== []) {
            $pdf->section(__('reports.categoryAnalysis.charts.breakdownTitle'));
            $pdf->table(
                [__('reports.categoryAnalysis.subcategory'), __('reports.categoryAnalysis.table.spent'), __('reports.categoryAnalysis.export.share')],
                collect($report['subcategory_breakdown']['nodes'])
                    ->take(12)
                    ->map(fn (array $node): array => [$node['label'], $node['total'], $node['share_label']])
                    ->all(),
                [230, 120, 120],
            );
        }

        return $pdf->output();
    }

    /**
     * @param  array<string, mixed>  $comparison
     */
    protected function comparisonLabel(array $comparison): string
    {
        if (($comparison['available'] ?? false) !== true) {
            return __('reports.categoryAnalysis.comparisons.unavailable');
        }

        return trim(($comparison['delta_percentage_label'] ?? '').' '.$comparison['delta_formatted']);
    }

    protected function filename(array $report): string
    {
        $category = Str::slug((string) ($report['meta']['subcategory_label'] ?? $report['meta']['category_label'] ?? 'categoria'));
        $period = Str::slug((string) ($report['meta']['period_label'] ?? 'periodo'));
        $timestamp = CarbonImmutable::now(config('app.timezone'))->format('Ymd-His');

        return "analisi-categoria-{$category}-{$period}-{$timestamp}.pdf";
    }

    /**
     * @return array<string, string>
     */
    protected function labels(): array
    {
        return [
            'generated_at' => __('reports.categoryAnalysis.export.generatedAt'),
            'header_subtitle' => __('reports.categoryAnalysis.export.headerSubtitle'),
            'summary' => __('reports.categoryAnalysis.export.summary'),
            'periods' => __('reports.categoryAnalysis.export.periods'),
            'comparisons' => __('reports.categoryAnalysis.export.comparisons'),
            'not_available' => __('reports.categoryAnalysis.export.notAvailable'),
            'scope' => __('reports.categoryAnalysis.scope.summaryLabel'),
            'analysis_scope' => __('reports.categoryAnalysis.export.analysisScope'),
            'actual_scope' => __('reports.categoryAnalysis.export.actualScope'),
            'budget_scope' => __('reports.categoryAnalysis.export.budgetScope'),
            'comparison_scope' => __('reports.categoryAnalysis.export.comparisonScope'),
        ];
    }
}
