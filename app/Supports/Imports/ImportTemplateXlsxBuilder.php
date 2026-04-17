<?php

namespace App\Supports\Imports;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use ZipArchive;

class ImportTemplateXlsxBuilder
{
    public const MOVEMENT_ROWS = 1000;

    /**
     * @return array{path:string, filename:string}
     */
    public function build(User $user, int $activeYear): array
    {
        $previousLocale = App::currentLocale();
        App::setLocale($user->preferredLocale());

        try {
            $path = tempnam(sys_get_temp_dir(), 'imports-template-').'.xlsx';
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $categories = $this->categories($user);
            $accounts = $this->accounts($user);
            $types = collect(ImportColumnMap::allowedTypeLabels())->values();

            $zip->addFromString('[Content_Types].xml', $this->contentTypes());
            $zip->addFromString('_rels/.rels', $this->rootRelationships());
            $zip->addFromString('xl/workbook.xml', $this->workbook());
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
            $zip->addFromString('xl/styles.xml', $this->styles());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->movementsSheet($activeYear, $categories, $accounts, $types));
            $zip->addFromString('xl/worksheets/sheet2.xml', $this->listsSheet($categories, $accounts, $types));
            $zip->addFromString('xl/worksheets/sheet3.xml', $this->instructionsSheet($activeYear));
            $zip->close();

            return [
                'path' => $path,
                'filename' => __('imports.template.filename'),
            ];
        } finally {
            App::setLocale($previousLocale);
        }
    }

    protected function movementsSheet(int $activeYear, Collection $categories, Collection $accounts, Collection $types): string
    {
        $headers = [
            __('imports.template.headers.account'),
            __('imports.template.headers.date'),
            __('imports.template.headers.type'),
            __('imports.template.headers.amount'),
            __('imports.template.headers.detail'),
            __('imports.template.headers.category'),
            __('imports.template.headers.destination_account'),
            __('imports.template.headers.reference'),
            __('imports.template.headers.merchant'),
            __('imports.template.headers.external_reference'),
        ];

        $rows = [$this->row(1, $headers, 2)];
        $rows[] = $this->row(2, [
            $accounts->first() ?: '',
            sprintf('15/03/%d', $activeYear),
            __('imports.template.expense_type'),
            '18,50',
            __('imports.template.expense_detail'),
            $categories->first() ?: '',
            '',
            'RIF-001',
            __('imports.template.default_merchant'),
            'EXT-001',
        ], 0);

        for ($row = 3; $row <= self::MOVEMENT_ROWS + 1; $row++) {
            $rows[] = '<row r="'.$row.'"/>';
        }

        $lastRow = self::MOVEMENT_ROWS + 1;

        return $this->worksheet(
            $this->movementColumnsXml().
            '<sheetData>'.implode('', $rows).'</sheetData>'.
            '<dataValidations count="4">'.
            $this->listValidation('A2:A'.$lastRow, 'Lists!$C$2:$C$'.max(2, $accounts->count() + 1)).
            $this->listValidation('C2:C'.$lastRow, 'Lists!$A$2:$A$'.($types->count() + 1)).
            $this->listValidation('F2:F'.$lastRow, 'Lists!$B$2:$B$'.max(2, $categories->count() + 1)).
            $this->listValidation('G2:G'.$lastRow, 'Lists!$C$2:$C$'.max(2, $accounts->count() + 1)).
            '</dataValidations>'
        );
    }

    protected function listsSheet(Collection $categories, Collection $accounts, Collection $types): string
    {
        $max = max($categories->count(), $accounts->count(), $types->count(), 1);
        $rows = [$this->row(1, [
            __('imports.template.lists.types'),
            __('imports.template.lists.categories'),
            __('imports.template.lists.destination_accounts'),
        ], 2)];

        for ($index = 0; $index < $max; $index++) {
            $rows[] = $this->row($index + 2, [
                $types->get($index, ''),
                $categories->get($index, ''),
                $accounts->get($index, ''),
            ]);
        }

        return $this->worksheet('<sheetData>'.implode('', $rows).'</sheetData>');
    }

    protected function instructionsSheet(int $activeYear): string
    {
        return $this->worksheet('<sheetData>'.
            $this->row(1, [__('imports.template.instructions.title')], 2).
            $this->row(3, [__('imports.template.instructions.year', ['year' => $activeYear])]).
            $this->row(4, [__('imports.template.instructions.date')]).
            $this->row(5, [__('imports.template.instructions.amount')]).
            $this->row(6, [__('imports.template.instructions.dropdowns')]).
            $this->row(7, [__('imports.template.instructions.account')]).
            $this->row(8, [__('imports.template.instructions.destination_account')]).
            '</sheetData>');
    }

    protected function row(int $number, array $values, int $style = 0): string
    {
        $cells = [];

        foreach (array_values($values) as $index => $value) {
            $ref = $this->columnName($index + 1).$number;
            $styleAttribute = $style > 0 ? ' s="'.$style.'"' : '';
            $cells[] = '<c r="'.$ref.'" t="inlineStr"'.$styleAttribute.'><is><t>'.$this->escape((string) $value).'</t></is></c>';
        }

        return '<row r="'.$number.'">'.implode('', $cells).'</row>';
    }

    protected function worksheet(string $innerXml): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$innerXml.'</worksheet>';
    }

    protected function movementColumnsXml(): string
    {
        return '<cols>'.
            $this->columnDefinition(1, 1, 34).
            $this->columnDefinition(2, 2, 14).
            $this->columnDefinition(3, 3, 18).
            $this->columnDefinition(4, 4, 14).
            $this->columnDefinition(5, 10, 24).
            '</cols>';
    }

    protected function columnDefinition(int $min, int $max, int $width): string
    {
        return '<col min="'.$min.'" max="'.$max.'" width="'.$width.'" customWidth="1"/>';
    }

    protected function listValidation(string $range, string $formula): string
    {
        return '<dataValidation type="list" allowBlank="1" showErrorMessage="1" sqref="'.$range.'"><formula1>'.$formula.'</formula1></dataValidation>';
    }

    protected function categories(User $user): Collection
    {
        return Category::query()
            ->ownedBy($user->id)
            ->where('is_active', true)
            ->where('is_selectable', true)
            ->orderBy('name')
            ->pluck('name')
            ->values();
    }

    protected function accounts(User $user): Collection
    {
        return Account::query()
            ->ownedBy($user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'uuid'])
            ->map(fn (Account $account): string => "{$account->name} ({$account->uuid})")
            ->values();
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

    protected function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    }

    protected function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    protected function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Movements" sheetId="1" r:id="rId1"/><sheet name="Lists" sheetId="2" r:id="rId2"/><sheet name="Instructions" sheetId="3" r:id="rId3"/></sheets></workbook>';
    }

    protected function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    protected function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF2F8"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>';
    }
}
