<?php

namespace App\Services\Dashboard;

use App\Services\Pdf\TextPdfDocument;
use Illuminate\Support\Str;

class MonthlyRecapPdfRenderer
{
    /**
     * @param  array<string, mixed>  $recap
     */
    public function render(array $recap): string
    {
        $pdf = new TextPdfDocument;
        $labels = $this->labels();
        $period = $recap['period'];
        $totals = $recap['totals'];

        $pdf->reportHeader(
            'Soamco Budget',
            $labels['title'].' - '.$period['label'].' '.$period['year'],
            $labels['generated_at'].' '.now()->format('d/m/Y H:i'),
            $labels['subtitle'],
        );

        $pdf->summaryPanel(
            $this->scopeLabel($recap),
            $period['starts_at'].' - '.$period['ends_at'],
            (string) $totals['transactions_count'],
            [
                ['label' => $labels['starting_balance'], 'value' => $totals['starting_balance_total'], 'tone' => 'neutral'],
                ['label' => $labels['ending_balance'], 'value' => $totals['ending_balance_total'], 'tone' => 'neutral'],
                ['label' => $labels['income'], 'value' => $totals['income_total'], 'tone' => 'income'],
                ['label' => $labels['expenses'], 'value' => $totals['expense_total'], 'tone' => 'expense'],
                ['label' => $labels['net'], 'value' => $totals['net_total'], 'tone' => $totals['net_total_raw'] >= 0 ? 'positive' : 'negative'],
            ],
            [
                'title' => $labels['summary'],
                'subtitle' => $labels['summary_subtitle'],
                'movements' => $labels['movements'],
                'account' => $labels['scope'],
                'period' => $labels['period'],
            ],
        );

        if (! $recap['available']) {
            $pdf->paragraph($labels['empty']);

            return $pdf->output();
        }

        $pdf->section($labels['comparison']);
        $pdf->keyValue($labels['previous_month'], $recap['previous_period']['label'].' '.$recap['previous_period']['year']);
        $pdf->keyValue($labels['net_vs_previous'], $totals['net_vs_previous'].$this->percentageSuffix($totals['net_vs_previous_percentage']));
        $pdf->keyValue($labels['income_share'], $totals['income_share'].'%');
        $pdf->keyValue($labels['expense_share'], $totals['expense_share'].'%');

        if ($recap['insights'] !== []) {
            $pdf->section($labels['insights']);

            foreach ($recap['insights'] as $insight) {
                $pdf->paragraph('- '.$insight['message']);
                $pdf->space(4);
            }
        }

        if ($recap['top_expense_categories'] !== []) {
            $pdf->section($labels['top_categories']);
            $pdf->table(
                [$labels['category'], $labels['movements_short'], $labels['share'], $labels['amount']],
                collect($recap['top_expense_categories'])
                    ->map(fn (array $category): array => [
                        (string) $category['category_name'],
                        (string) $category['transactions_count'],
                        ((string) $category['share']).'%',
                        (string) $category['total_amount'],
                    ])
                    ->all(),
                [240, 70, 70, 110],
            );
        }

        if ($recap['top_movements'] !== []) {
            $pdf->section($labels['top_movements']);
            $pdf->table(
                [$labels['date'], $labels['description'], $labels['amount']],
                collect($recap['top_movements'])
                    ->map(fn (array $movement): array => [
                        (string) $movement['date'],
                        (string) $movement['description'],
                        (string) $movement['amount'],
                    ])
                    ->all(),
                [70, 300, 120],
            );
        }

        return $pdf->output();
    }

    /**
     * @param  array<string, mixed>  $recap
     */
    public function filename(array $recap): string
    {
        return sprintf(
            'soamco-budget-recap-%s-%s.pdf',
            $recap['period']['key'],
            Str::slug((string) $recap['scope']['account_scope']),
        );
    }

    /**
     * @param  array<string, mixed>  $recap
     */
    protected function scopeLabel(array $recap): string
    {
        return match ($recap['scope']['account_scope'] ?? 'all') {
            'owned' => __('dashboard.filters.account_access_scopes.owned'),
            'shared' => __('dashboard.filters.account_access_scopes.shared'),
            default => __('dashboard.filters.account_access_scopes.all'),
        };
    }

    protected function percentageSuffix(float|int|null $percentage): string
    {
        if ($percentage === null) {
            return '';
        }

        return ' ('.($percentage > 0 ? '+' : '').round($percentage).'%'.')';
    }

    /**
     * @return array<string, string>
     */
    protected function labels(): array
    {
        return [
            'title' => __('dashboard.monthly_recap.document.title'),
            'subtitle' => __('dashboard.monthly_recap.document.subtitle'),
            'generated_at' => __('dashboard.monthly_recap.document.generated_at'),
            'summary' => __('dashboard.monthly_recap.document.summary'),
            'summary_subtitle' => __('dashboard.monthly_recap.document.summary_subtitle'),
            'movements' => __('dashboard.monthly_recap.document.movements'),
            'scope' => __('dashboard.monthly_recap.document.scope'),
            'period' => __('dashboard.monthly_recap.document.period'),
            'starting_balance' => __('dashboard.monthly_recap.document.starting_balance'),
            'ending_balance' => __('dashboard.monthly_recap.document.ending_balance'),
            'income' => __('dashboard.monthly_recap.document.income'),
            'expenses' => __('dashboard.monthly_recap.document.expenses'),
            'net' => __('dashboard.monthly_recap.document.net'),
            'comparison' => __('dashboard.monthly_recap.document.comparison'),
            'previous_month' => __('dashboard.monthly_recap.document.previous_month'),
            'net_vs_previous' => __('dashboard.monthly_recap.document.net_vs_previous'),
            'income_share' => __('dashboard.monthly_recap.document.income_share'),
            'expense_share' => __('dashboard.monthly_recap.document.expense_share'),
            'insights' => __('dashboard.monthly_recap.document.insights'),
            'top_categories' => __('dashboard.monthly_recap.document.top_categories'),
            'top_movements' => __('dashboard.monthly_recap.document.top_movements'),
            'category' => __('dashboard.monthly_recap.document.category'),
            'movements_short' => __('dashboard.monthly_recap.document.movements_short'),
            'share' => __('dashboard.monthly_recap.document.share'),
            'date' => __('dashboard.monthly_recap.document.date'),
            'description' => __('dashboard.monthly_recap.document.description'),
            'amount' => __('dashboard.monthly_recap.document.amount'),
            'empty' => __('dashboard.monthly_recap.document.empty'),
        ];
    }
}
