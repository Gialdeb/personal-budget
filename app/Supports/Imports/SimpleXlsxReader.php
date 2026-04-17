<?php

namespace App\Supports\Imports;

use Carbon\CarbonImmutable;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class SimpleXlsxReader
{
    /**
     * @return array{headers: array<int, string>, records: array<int, array<string, string|null>>}
     */
    public function readFirstSheet(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException(__('imports.validation.file_unreadable'));
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml') ?: '';
        $stylesXml = $zip->getFromName('xl/styles.xml') ?: '';
        $zip->close();

        if ($sheetXml === '') {
            throw new RuntimeException(__('imports.validation.xlsx_missing_movements_sheet'));
        }

        $sharedStrings = $this->sharedStrings($sharedStringsXml);
        $dateStyles = $this->dateStyles($stylesXml);
        $xml = simplexml_load_string($sheetXml);

        if ($xml === false) {
            throw new RuntimeException(__('imports.validation.file_unreadable'));
        }

        $rows = [];

        $namespace = $xml->getNamespaces(true)[''] ?? null;
        $sheet = $namespace ? $xml->children($namespace) : $xml;

        foreach ($sheet->sheetData->row as $row) {
            $values = [];
            $row->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rowCells = $row->xpath('main:c') ?: $row->xpath('c') ?: [];

            foreach ($rowCells as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference);
                $values[$this->columnIndex((string) $column)] = $this->cellValue(
                    $cell,
                    $sharedStrings,
                    $dateStyles,
                );
            }

            ksort($values);
            $rows[] = $values;
        }

        $headers = collect(array_shift($rows) ?? [])
            ->map(fn ($header): string => trim((string) $header))
            ->all();

        return [
            'headers' => $headers,
            'records' => collect($rows)
                ->map(function (array $row) use ($headers): array {
                    $mapped = [];

                    foreach ($headers as $index => $header) {
                        if ($header !== '') {
                            $mapped[$header] = $row[$index] ?? null;
                        }
                    }

                    return $mapped;
                })
                ->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function sharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $strings = simplexml_load_string($xml);

        if ($strings === false) {
            return [];
        }

        $namespace = $strings->getNamespaces(true)[''] ?? null;
        $items = $namespace ? $strings->children($namespace)->si : $strings->si;
        $itemsList = [];

        foreach ($items as $item) {
            $itemsList[] = $item;
        }

        return collect($itemsList)
            ->map(function (SimpleXMLElement $item): string {
                $item->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

                return collect($item->xpath('.//main:t') ?: $item->xpath('.//t') ?: [])
                    ->map(fn (SimpleXMLElement $text): string => (string) $text)
                    ->implode('');
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, bool>
     */
    protected function dateStyles(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $styles = simplexml_load_string($xml);

        if ($styles === false) {
            return [];
        }

        $styles->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $customDateFormats = [];

        foreach ($styles->xpath('.//main:numFmts/main:numFmt') ?: [] as $format) {
            $formatId = (int) ($format['numFmtId'] ?? 0);
            $formatCode = mb_strtolower((string) ($format['formatCode'] ?? ''));

            if ($formatId > 0 && $this->isDateFormatCode($formatCode)) {
                $customDateFormats[$formatId] = true;
            }
        }

        $dateStyles = [];
        $builtinDateFormats = array_fill_keys([14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47], true);
        $styleIndex = 0;

        foreach ($styles->xpath('.//main:cellXfs/main:xf') ?: [] as $xf) {
            $numFmtId = (int) ($xf['numFmtId'] ?? 0);

            if (isset($builtinDateFormats[$numFmtId]) || isset($customDateFormats[$numFmtId])) {
                $dateStyles[$styleIndex] = true;
            }

            $styleIndex++;
        }

        return $dateStyles;
    }

    protected function isDateFormatCode(string $formatCode): bool
    {
        $normalized = preg_replace('/"[^"]*"|\[[^]]+]|\\\\.|_.|\\*.?/', '', $formatCode) ?? '';

        return preg_match('/[ymdhis]/', $normalized) === 1;
    }

    protected function cellValue(SimpleXMLElement $cell, array $sharedStrings, array $dateStyles): ?string
    {
        $type = (string) $cell['t'];
        $namespace = $cell->getNamespaces(true)[''] ?? null;
        $children = $namespace ? $cell->children($namespace) : $cell;
        $styleIndex = isset($cell['s']) ? (int) $cell['s'] : null;

        if ($type === 'inlineStr') {
            return $this->extractInlineStringValue($children->is ?? null);
        }

        $value = trim((string) ($children->v ?? ''));

        if ($value === '') {
            return null;
        }

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? null;
        }

        if ($type === '' && $styleIndex !== null && isset($dateStyles[$styleIndex])) {
            return $this->excelSerialToDate($value);
        }

        return $value;
    }

    protected function extractInlineStringValue(?SimpleXMLElement $inlineString): ?string
    {
        if (! $inlineString instanceof SimpleXMLElement) {
            return null;
        }

        $inlineString->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $value = collect(
            $inlineString->xpath('.//main:t') ?: $inlineString->xpath('.//t') ?: []
        )
            ->map(fn (SimpleXMLElement $text): string => (string) $text)
            ->implode('');

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    protected function excelSerialToDate(string $value): ?string
    {
        if (! is_numeric($value)) {
            return $value;
        }

        $serial = (float) $value;

        if ($serial <= 0) {
            return $value;
        }

        $days = (int) floor($serial);

        if ($days < 1) {
            return $value;
        }

        return CarbonImmutable::create(1899, 12, 30, 0, 0, 0, 'UTC')
            ->addDays($days)
            ->format('d/m/Y');
    }

    protected function columnIndex(string $column): int
    {
        $index = 0;

        foreach (str_split($column) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
