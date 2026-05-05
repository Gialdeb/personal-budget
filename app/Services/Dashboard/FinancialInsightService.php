<?php

namespace App\Services\Dashboard;

class FinancialInsightService
{
    /**
     * @param  array{
     *     period: array{label: string},
     *     totals: array{
     *         net_total_raw: float,
     *         net_vs_previous_raw: float,
     *         net_vs_previous_percentage: float|null,
     *         expense_total_raw: float
     *     },
     *     top_expense_categories: array<int, array{category_name: string, total_amount_raw: float}>
     * }  $recap
     * @return array<int, array{type: string, tone: string, message: string}>
     */
    public function forMonthlyRecap(array $recap): array
    {
        $insights = [];
        $periodLabel = $recap['period']['label'];
        $netTotal = $recap['totals']['net_total_raw'];
        $netDelta = $recap['totals']['net_vs_previous_raw'];
        $netDeltaPercentage = $recap['totals']['net_vs_previous_percentage'];
        $topCategories = $recap['top_expense_categories'];

        if ($netTotal >= 0.01) {
            $insights[] = [
                'type' => 'net_positive',
                'tone' => 'positive',
                'message' => __('dashboard.monthly_recap.insights.net_positive', [
                    'month' => $periodLabel,
                    'amount' => $recap['totals']['net_total'],
                ]),
            ];
        } elseif ($netTotal <= -0.01) {
            $insights[] = [
                'type' => 'net_negative',
                'tone' => 'warning',
                'message' => __('dashboard.monthly_recap.insights.net_negative', [
                    'month' => $periodLabel,
                    'amount' => $recap['totals']['net_total'],
                ]),
            ];
        }

        if (abs($netDelta) >= 0.01) {
            $insights[] = [
                'type' => $netDelta > 0 ? 'net_improved' : 'net_worsened',
                'tone' => $netDelta > 0 ? 'positive' : 'warning',
                'message' => __('dashboard.monthly_recap.insights.net_delta', [
                    'previous_month' => $recap['previous_period']['label'],
                    'direction' => $netDelta > 0
                        ? __('dashboard.monthly_recap.direction.more')
                        : __('dashboard.monthly_recap.direction.less'),
                    'amount' => $recap['totals']['net_vs_previous'],
                    'percentage' => $netDeltaPercentage !== null
                        ? ' ('.($netDeltaPercentage > 0 ? '+' : '').round($netDeltaPercentage).'%'.')'
                        : '',
                ]),
            ];
        }

        if (count($topCategories) >= 2) {
            $insights[] = [
                'type' => 'top_expense_categories',
                'tone' => 'neutral',
                'message' => __('dashboard.monthly_recap.insights.top_expense_categories', [
                    'first' => $topCategories[0]['category_name'],
                    'second' => $topCategories[1]['category_name'],
                ]),
            ];
        } elseif (count($topCategories) === 1) {
            $insights[] = [
                'type' => 'top_expense_category',
                'tone' => 'neutral',
                'message' => __('dashboard.monthly_recap.insights.top_expense_category', [
                    'category' => $topCategories[0]['category_name'],
                ]),
            ];
        } elseif (($recap['totals']['expense_total_raw'] ?? 0.0) <= 0.0 && $netTotal > 0.0) {
            $insights[] = [
                'type' => 'no_expenses',
                'tone' => 'positive',
                'message' => __('dashboard.monthly_recap.insights.no_expenses'),
            ];
        }

        return array_slice($insights, 0, 3);
    }
}
