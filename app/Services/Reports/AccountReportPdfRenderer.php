<?php

namespace App\Services\Reports;

use App\Services\Pdf\TextPdfDocument;

class AccountReportPdfRenderer
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function render(array $report): string
    {
        $pdf = new TextPdfDocument;
        $labels = $this->labels((string) ($report['locale'] ?? app()->getLocale()));

        $pdf->reportHeader('Soamco Budget', $report['meta']['title'], $labels['generated_at'].' '.$report['generated_at'], $labels['header_subtitle']);
        $pdf->summaryPanel(
            $report['meta']['scope_label'],
            $report['meta']['period_label'].' - '.$report['meta']['period_range'],
            (string) $report['meta']['transactions_count'],
            [
                ['label' => $labels['opening_balance'], 'value' => $report['meta']['opening_balance'], 'tone' => 'neutral'],
                ['label' => $labels['closing_balance'], 'value' => $report['meta']['closing_balance'], 'tone' => 'neutral'],
                ['label' => $labels['change'], 'value' => $report['meta']['net_change'], 'tone' => $this->amountTone($report['meta']['net_change'])],
                ['label' => $labels['income'], 'value' => $report['meta']['income'], 'tone' => 'income'],
                ['label' => $labels['expense'], 'value' => $report['meta']['expense'], 'tone' => 'expense'],
            ],
            [
                'title' => $labels['summary'],
                'subtitle' => $labels['summary_subtitle'],
                'movements' => $labels['movements'],
                'account' => $labels['account'],
                'period' => $labels['period'],
            ],
        );

        $isMultiAccountReport = count($report['groups']) > 1;

        if ($isMultiAccountReport) {
            $pdf->section($labels['account_detail']);
        } else {
            $pdf->section($labels['account_movements']);
        }

        $headers = [$labels['date'], $labels['description'], $labels['note'], $labels['amount'], $labels['balance']];
        $widths = [56, 138, 144, 78, 74];

        if ($report['groups'] === []) {
            $pdf->paragraph($labels['empty_report']);

            return $pdf->output();
        }

        foreach ($report['groups'] as $group) {
            $pdf->accountPanel(
                $group['account_name'],
                $group['bank_name'],
                $group['opening_balance'],
                $group['closing_balance'],
                $group['net_change'],
                [
                    'opening' => $labels['opening_short'],
                    'closing' => $labels['closing_short'],
                    'change' => $labels['change'],
                ],
            );

            if ($group['transactions'] === []) {
                $pdf->paragraph($labels['empty_account']);
                $pdf->accountClosingRow($labels['account_closing'], $group['closing_balance']);

                continue;
            }

            $pdf->table(
                $headers,
                collect($group['transactions'])
                    ->map(fn (array $transaction): array => [
                        $transaction['date'],
                        $transaction['title'],
                        $transaction['note'],
                        $transaction['amount'],
                        $transaction['balance'],
                    ])
                    ->all(),
                $widths,
            );
            $pdf->accountClosingRow($labels['account_closing'], $group['closing_balance']);
            $pdf->space(8);
        }

        return $pdf->output();
    }

    protected function amountTone(string $amount): string
    {
        if (str_contains($amount, '-')) {
            return 'negative';
        }

        return 'positive';
    }

    /**
     * @return array<string, string>
     */
    protected function labels(string $locale): array
    {
        if (str_starts_with($locale, 'en')) {
            return [
                'generated_at' => 'Generated on',
                'header_subtitle' => 'Report produced by Soamco Budget',
                'summary' => 'Executive summary',
                'summary_subtitle' => 'Scope and balances for the selected period',
                'movements' => 'movements',
                'account' => 'Account',
                'period' => 'Period',
                'opening_balance' => 'Opening balance',
                'closing_balance' => 'Closing balance',
                'change' => 'Change',
                'income' => 'Income',
                'expense' => 'Expense',
                'account_detail' => 'Account detail',
                'account_movements' => 'Account movements',
                'opening_short' => 'Opening',
                'closing_short' => 'Closing',
                'date' => 'Date',
                'description' => 'Description',
                'note' => 'Note',
                'amount' => 'Amount',
                'balance' => 'Balance',
                'account_closing' => 'Closing balance',
                'empty_account' => 'No movements for this account in the selected period.',
                'empty_report' => 'No movements available in the selected period.',
            ];
        }

        return [
            'generated_at' => 'Generato il',
            'header_subtitle' => 'Report prodotto da Soamco Budget',
            'summary' => 'Riepilogo',
            'summary_subtitle' => 'Perimetro e saldi del periodo selezionato',
            'movements' => 'movimenti',
            'account' => 'Conto',
            'period' => 'Periodo',
            'opening_balance' => 'Saldo iniziale',
            'closing_balance' => 'Saldo finale',
            'change' => 'Variazione',
            'income' => 'Entrate',
            'expense' => 'Uscite',
            'account_detail' => 'Dettaglio per conto',
            'account_movements' => 'Movimenti del conto',
            'opening_short' => 'Iniziale',
            'closing_short' => 'Finale',
            'date' => 'Data',
            'description' => 'Descrizione',
            'note' => 'Nota',
            'amount' => 'Ammontare',
            'balance' => 'Saldo',
            'account_closing' => 'Chiusura conto',
            'empty_account' => 'Nessun movimento per questo conto nel periodo selezionato.',
            'empty_report' => 'Nessun movimento disponibile nel periodo selezionato.',
        ];
    }
}
