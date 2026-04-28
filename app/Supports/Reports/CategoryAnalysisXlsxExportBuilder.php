<?php

namespace App\Supports\Reports;

use Illuminate\Support\Str;
use ZipArchive;

class CategoryAnalysisXlsxExportBuilder
{
    /**
     * @param  array<string, mixed>  $report
     * @return array{path: string, filename: string}
     */
    public function build(array $report): array
    {
        $path = tempnam(sys_get_temp_dir(), 'category-analysis-').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->summarySheet($report));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->monthlySheet($report));
        $zip->addFromString('xl/worksheets/sheet3.xml', $this->subcategorySheet($report));
        $zip->addFromString('xl/worksheets/sheet4.xml', $this->trendSheet($report));
        $zip->close();

        return [
            'path' => $path,
            'filename' => $this->filename($report),
        ];
    }

    protected function summarySheet(array $report): string
    {
        $meta = $report['meta'];
        $summary = $report['summary'];
        $comparisons = $report['comparisons'];

        return $this->worksheet(
            '<cols>'.$this->columnDefinition(1, 1, 32).$this->columnDefinition(2, 2, 26).'</cols>'.
            '<sheetData>'.
            $this->row(1, [__('reports.categoryAnalysis.export.title')], 3).
            $this->row(3, [__('reports.categoryAnalysis.category'), $meta['category_label'] ?? '-'], 0).
            $this->row(4, [__('reports.categoryAnalysis.subcategory'), $meta['subcategory_label'] ?? __('reports.categoryAnalysis.allSubcategories')], 0).
            $this->row(5, [__('reports.categoryAnalysis.export.period'), $meta['period_label']], 0).
            $this->row(6, [__('reports.categoryAnalysis.export.scope'), $meta['scope_label']], 0).
            $this->row(8, [__('reports.categoryAnalysis.kpis.totalSpent'), $summary['total_spent_raw']], 1).
            $this->row(9, [__('reports.categoryAnalysis.kpis.averagePeriod'), $summary['average_period_raw']], 1).
            $this->row(10, [__('reports.categoryAnalysis.kpis.bestMonth'), trim(($summary['best_period_label'] ?? '-').' '.($summary['best_period_value'] ?? ''))], 1).
            $this->row(11, [__('reports.categoryAnalysis.kpis.worstMonth'), trim(($summary['worst_period_label'] ?? '-').' '.($summary['worst_period_value'] ?? ''))], 1).
            $this->row(12, [__('reports.categoryAnalysis.budget.label'), $meta['budget']['supported'] ? $meta['budget']['total_raw'] : __('reports.categoryAnalysis.comparisons.unavailable')], 1).
            $this->row(13, [__('reports.categoryAnalysis.kpis.previousPeriod'), $comparisons['previous_period']['delta_raw']], 1).
            $this->row(14, [__('reports.categoryAnalysis.kpis.previousYear'), $comparisons['previous_year']['delta_raw']], 1).
            '</sheetData>'
        );
    }

    protected function monthlySheet(array $report): string
    {
        $rows = [
            $this->row(1, [
                __('reports.categoryAnalysis.table.period'),
                __('reports.categoryAnalysis.table.spent'),
                __('reports.categoryAnalysis.budget.label'),
                __('reports.categoryAnalysis.budget.variance'),
                __('reports.categoryAnalysis.export.previousYear'),
                __('reports.categoryAnalysis.table.deltaPreviousYear'),
                __('reports.categoryAnalysis.table.dominantSubcategory'),
            ], 2),
        ];

        foreach ($report['monthly_rows'] as $index => $row) {
            $rows[] = $this->row($index + 2, [
                $row['label'],
                $row['spent_raw'],
                $row['budget_raw'],
                $row['budget_delta_raw'],
                $row['previous_year_raw'],
                $row['delta_previous_year_raw'],
                $row['dominant_subcategory_label'] ?? '-',
            ], 1);
        }

        return $this->worksheet(
            '<cols>'.$this->columnDefinition(1, 1, 18).$this->columnDefinition(2, 6, 18).$this->columnDefinition(7, 7, 28).'</cols>'.
            '<sheetData>'.implode('', $rows).'</sheetData>'
        );
    }

    protected function subcategorySheet(array $report): string
    {
        $rows = [
            $this->row(1, [
                __('reports.categoryAnalysis.subcategory'),
                __('reports.categoryAnalysis.table.spent'),
                __('reports.categoryAnalysis.export.share'),
            ], 2),
        ];

        foreach ($report['subcategory_breakdown']['nodes'] as $index => $node) {
            $rows[] = $this->row($index + 2, [
                $node['label'],
                $node['value'],
                $node['share_label'],
            ], 1);
        }

        return $this->worksheet(
            '<cols>'.$this->columnDefinition(1, 1, 32).$this->columnDefinition(2, 3, 18).'</cols>'.
            '<sheetData>'.implode('', $rows).'</sheetData>'
        );
    }

    protected function trendSheet(array $report): string
    {
        $series = $report['trend']['series'][0] ?? ['values' => []];
        $labels = $report['trend']['labels'] ?? [];
        $values = $series['values'] ?? [];
        $rows = [
            $this->row(1, [
                __('reports.categoryAnalysis.table.period'),
                __('reports.categoryAnalysis.table.spent'),
            ], 2),
        ];

        foreach ($labels as $index => $label) {
            $rows[] = $this->row($index + 2, [
                $label,
                $values[$index] ?? 0,
            ], 1);
        }

        return $this->worksheet(
            '<cols>'.$this->columnDefinition(1, 1, 18).$this->columnDefinition(2, 2, 18).'</cols>'.
            '<sheetData>'.implode('', $rows).'</sheetData>'
        );
    }

    protected function row(int $number, array $values, int $style = 0): string
    {
        $cells = [];

        foreach (array_values($values) as $index => $value) {
            $ref = $this->columnName($index + 1).$number;
            $styleAttribute = $style > 0 ? ' s="'.$style.'"' : '';

            if (is_int($value) || is_float($value)) {
                $cells[] = '<c r="'.$ref.'"'.$styleAttribute.'><v>'.round((float) $value, 2).'</v></c>';

                continue;
            }

            $cells[] = '<c r="'.$ref.'" t="inlineStr"'.$styleAttribute.'><is><t>'.$this->escape((string) $value).'</t></is></c>';
        }

        return '<row r="'.$number.'">'.implode('', $cells).'</row>';
    }

    protected function worksheet(string $innerXml): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$innerXml.'</worksheet>';
    }

    protected function columnDefinition(int $min, int $max, int $width): string
    {
        return '<col min="'.$min.'" max="'.$max.'" width="'.$width.'" customWidth="1"/>';
    }

    protected function columnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected function filename(array $report): string
    {
        $category = Str::slug((string) ($report['meta']['subcategory_label'] ?? $report['meta']['category_label'] ?? 'categoria'));
        $period = Str::slug((string) ($report['meta']['period_label'] ?? 'periodo'));

        return "analisi-categoria-{$category}-{$period}.xlsx";
    }

    protected function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet4.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    }

    protected function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    protected function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Summary" sheetId="1" r:id="rId1"/><sheet name="Monthly detail" sheetId="2" r:id="rId2"/><sheet name="Subcategories" sheetId="3" r:id="rId3"/><sheet name="Trend" sheetId="4" r:id="rId4"/></sheets></workbook>';
    }

    protected function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet4.xml"/><Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    protected function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF2F8"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEF4444"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFE5E7EB"/></left><right style="thin"><color rgb="FFE5E7EB"/></right><top style="thin"><color rgb="FFE5E7EB"/></top><bottom style="thin"><color rgb="FFE5E7EB"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyNumberFormat="1"/><xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/></cellXfs></styleSheet>';
    }
}
