<?php

namespace App\Services\Pdf;

use Illuminate\Support\Str;

class TextPdfDocument
{
    protected const PAGE_WIDTH = 595;

    protected const PAGE_HEIGHT = 842;

    protected const MARGIN_X = 42;

    protected const MARGIN_TOP = 42;

    protected const MARGIN_BOTTOM = 48;

    /**
     * @var list<string>
     */
    protected array $pages = [];

    /**
     * @var list<string>
     */
    protected array $commands = [];

    protected float $cursorY = self::PAGE_HEIGHT - self::MARGIN_TOP;

    public function __construct()
    {
        $this->startPage();
    }

    public function title(string $text): void
    {
        $this->text($text, 22, true);
        $this->space(6);
    }

    public function reportHeader(string $brand, string $title, string $generatedAt, string $subtitle): void
    {
        $this->ensureSpace(86);
        $this->fillRect(0, self::PAGE_HEIGHT - 96, self::PAGE_WIDTH, 96, '0.97 0.985 1');
        $this->fillRect(self::MARGIN_X, self::PAGE_HEIGHT - 66, 4, 34, '0.08 0.18 0.32');
        $this->writeText(self::MARGIN_X + 14, self::PAGE_HEIGHT - 45, $brand, 18, true, '0.08 0.12 0.20');
        $this->writeText(self::MARGIN_X + 14, self::PAGE_HEIGHT - 64, $subtitle, 8, false, '0.39 0.45 0.55');
        $this->writeText(self::PAGE_WIDTH - self::MARGIN_X - 128, self::PAGE_HEIGHT - 45, $generatedAt, 8, false, '0.39 0.45 0.55');
        $this->commands[] = sprintf('0.82 0.87 0.94 RG %.2F %.2F m %.2F %.2F l S', self::MARGIN_X, self::PAGE_HEIGHT - 86, self::PAGE_WIDTH - self::MARGIN_X, self::PAGE_HEIGHT - 86);
        $this->writeText(self::MARGIN_X, self::PAGE_HEIGHT - 122, $title, 24, true, '0.08 0.12 0.20');
        $this->cursorY = self::PAGE_HEIGHT - 148;
    }

    public function subtitle(string $text): void
    {
        $this->text($text, 12);
        $this->space(12);
    }

    public function section(string $text): void
    {
        $this->space(6);
        $this->text($text, 13, true);
        $this->line();
        $this->space(8);
    }

    public function keyValue(string $key, string $value): void
    {
        $valueLines = $this->wrap($value, 72);
        $rowHeight = max(15, count($valueLines) * 11);

        $this->ensureSpace($rowHeight + 4);
        $this->writeText(self::MARGIN_X, $this->cursorY, $key, 9, true);

        $lineY = $this->cursorY;
        foreach ($valueLines as $line) {
            $this->writeText(self::MARGIN_X + 135, $lineY, $line, 9);
            $lineY -= 11;
        }

        $this->cursorY -= $rowHeight;
    }

    /**
     * @param  list<array{label: string, value: string, tone?: string}>  $items
     */
    /**
     * @param  array{title: string, subtitle: string, movements: string, account: string, period: string}  $labels
     * @param  list<array{label: string, value: string, tone?: string}>  $items
     */
    public function summaryPanel(string $scope, string $period, string $movementCount, array $items, array $labels): void
    {
        $panelHeight = 184;
        $headerHeight = 54;
        $this->ensureSpace($panelHeight + 12);
        $top = $this->cursorY;
        $panelY = $top - $panelHeight;

        $this->fillRect(self::MARGIN_X, $panelY, self::PAGE_WIDTH - (self::MARGIN_X * 2), $panelHeight, '0.99 1 1');
        $this->strokeRect(self::MARGIN_X, $panelY, self::PAGE_WIDTH - (self::MARGIN_X * 2), $panelHeight, '0.82 0.87 0.94');
        $this->fillRect(self::MARGIN_X, $top - $headerHeight, self::PAGE_WIDTH - (self::MARGIN_X * 2), $headerHeight, '0.08 0.12 0.20');

        $this->writeText(self::MARGIN_X + 16, $top - 20, $labels['title'], 13, true, '1 1 1');
        $this->writeWrappedText(self::MARGIN_X + 16, $top - 33, $labels['subtitle'], 7, false, '0.82 0.87 0.94', 118, 9, 2);
        $this->fillRect(self::PAGE_WIDTH - self::MARGIN_X - 116, $top - 29, 90, 16, '0.16 0.24 0.38');
        $this->writeText(self::PAGE_WIDTH - self::MARGIN_X - 104, $top - 24, $movementCount.' '.$labels['movements'], 7, true, '1 1 1');

        $bodyOffset = 14;
        $this->writeText(self::MARGIN_X + 16, $top - 62 - $bodyOffset, $labels['account'], 7, true, '0.39 0.45 0.55');
        $this->writeWrappedText(self::MARGIN_X + 16, $top - 78 - $bodyOffset, $scope, 10, true, '0.08 0.12 0.20', 32, 11, 2);
        $this->writeText(self::MARGIN_X + 260, $top - 62 - $bodyOffset, $labels['period'], 7, true, '0.39 0.45 0.55');
        $this->writeText(self::MARGIN_X + 260, $top - 78 - $bodyOffset, $period, 9, false, '0.08 0.12 0.20');
        $this->commands[] = sprintf('0.88 0.91 0.96 RG %.2F %.2F m %.2F %.2F l S', self::MARGIN_X + 16, $top - 106, self::PAGE_WIDTH - self::MARGIN_X - 16, $top - 106);

        $metricY = $top - 128;
        $metricWidth = 92;
        foreach ($items as $index => $item) {
            $x = self::MARGIN_X + 16 + ($index * ($metricWidth + 6));
            $tone = $item['tone'] ?? 'neutral';
            $this->fillRect($x, $metricY - 40, $metricWidth, 44, $this->toneFill($tone));
            $this->strokeRect($x, $metricY - 40, $metricWidth, 44, $this->toneStroke($tone));
            $this->fillRect($x, $metricY - 40, 3, 44, $this->toneAccent($tone));
            $this->writeText($x + 9, $metricY - 10, $item['label'], 6, true, $this->toneText($tone));
            $this->writeText($x + 9, $metricY - 29, $item['value'], 7, true, '0.08 0.12 0.20');
        }

        $this->cursorY = $panelY - 18;
    }

    /**
     * @param  array{opening: string, closing: string, change: string}  $labels
     */
    public function accountPanel(string $name, ?string $subtitle, string $openingBalance, string $closingBalance, string $netChange, array $labels): void
    {
        $this->space(4);
        $height = 86;
        $this->ensureSpace($height + 16);
        $top = $this->cursorY;
        $y = $top - $height;

        $this->fillRect(self::MARGIN_X, $y, self::PAGE_WIDTH - (self::MARGIN_X * 2), $height, '0.985 0.99 1');
        $this->strokeRect(self::MARGIN_X, $y, self::PAGE_WIDTH - (self::MARGIN_X * 2), $height, '0.78 0.84 0.92');
        $this->fillRect(self::MARGIN_X, $top - 24, self::PAGE_WIDTH - (self::MARGIN_X * 2), 24, '0.94 0.97 1');
        $this->fillRect(self::MARGIN_X, $y, 5, $height, '0.08 0.18 0.32');
        $this->writeText(self::MARGIN_X + 16, $top - 16, $name, 14, true, '0.08 0.12 0.20');

        if ($subtitle !== null && trim($subtitle) !== '') {
            $this->writeText(self::MARGIN_X + 16, $top - 43, $subtitle, 8, false, '0.39 0.45 0.55');
        }

        $this->compactMetric(self::PAGE_WIDTH - self::MARGIN_X - 258, $top - 44, $labels['opening'], $openingBalance, 'neutral');
        $this->compactMetric(self::PAGE_WIDTH - self::MARGIN_X - 170, $top - 44, $labels['closing'], $closingBalance, 'neutral');
        $this->compactMetric(self::PAGE_WIDTH - self::MARGIN_X - 82, $top - 44, $labels['change'], $netChange, $this->amountTone($netChange));

        $this->cursorY = $y - 12;
    }

    public function accountClosingRow(string $label, string $value): void
    {
        $this->ensureSpace(24);
        $y = $this->cursorY - 8;
        $this->fillRect(self::MARGIN_X - 6, $y - 8, self::PAGE_WIDTH - (self::MARGIN_X * 2) + 12, 24, '0.98 0.99 1');
        $this->commands[] = sprintf('0.86 0.90 0.96 RG %.2F %.2F m %.2F %.2F l S', self::MARGIN_X - 6, $y + 16, self::PAGE_WIDTH - self::MARGIN_X + 6, $y + 16);
        $this->writeText(self::MARGIN_X, $y, $label, 9, true, '0.08 0.12 0.20');
        $this->writeTextRight(self::PAGE_WIDTH - self::MARGIN_X - 130, $y, 130, $value, 9, true, '0.08 0.12 0.20');
        $this->cursorY -= 30;
    }

    /**
     * @param  list<array{label: string, value: string}>  $items
     */
    public function metricGrid(array $items): void
    {
        $columnWidth = 126;
        $rowHeight = 42;
        $x = self::MARGIN_X;
        $y = $this->cursorY;

        $this->ensureSpace($rowHeight + 8);

        foreach ($items as $index => $item) {
            $column = $index % 4;

            if ($index > 0 && $column === 0) {
                $y -= $rowHeight + 8;
                $this->ensureSpace($rowHeight + 8);
            }

            $cardX = $x + ($column * $columnWidth);
            $this->rect($cardX, $y - $rowHeight + 10, $columnWidth - 8, $rowHeight);
            $this->writeText($cardX + 8, $y - 5, $item['label'], 7, true);
            $this->writeText($cardX + 8, $y - 22, $item['value'], 10, true);
        }

        $this->cursorY = $y - $rowHeight - 12;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  list<int>  $widths
     */
    public function table(array $headers, array $rows, array $widths): void
    {
        $widths = $this->fitTableWidths($widths);
        $this->tableRow($headers, $widths, true);

        foreach ($rows as $index => $row) {
            $this->tableRow($row, $widths, false, $index);
        }
    }

    public function paragraph(string $text): void
    {
        foreach ($this->wrap($text, 95) as $line) {
            $this->text($line, 9);
        }
    }

    public function text(string $text, int $fontSize = 9, bool $bold = false): void
    {
        $this->ensureSpace($fontSize + 8);
        $this->writeText(self::MARGIN_X, $this->cursorY, $text, $fontSize, $bold);
        $this->cursorY -= $fontSize + 5;
    }

    public function space(int $height): void
    {
        $this->cursorY -= $height;
    }

    public function output(): string
    {
        $this->finishPage();

        $objects = [];
        $pageObjectIds = [];

        foreach ($this->pages as $content) {
            $contentObjectId = count($objects) + 1;
            $objects[] = '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream";

            $pageObjectIds[] = count($objects) + 1;
            $objects[] = '<< /Type /Page /Parent 0 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 0 0 R /F2 0 0 R >> >> /Contents '.$contentObjectId.' 0 R >>';
        }

        $pagesObjectId = count($objects) + 1;
        $kids = collect($pageObjectIds)->map(fn (int $id): string => "{$id} 0 R")->join(' ');
        $objects[] = "<< /Type /Pages /Kids [{$kids}] /Count ".count($pageObjectIds).' >>';

        $fontRegularObjectId = count($objects) + 1;
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $fontBoldObjectId = count($objects) + 1;
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $catalogObjectId = count($objects) + 1;
        $objects[] = "<< /Type /Catalog /Pages {$pagesObjectId} 0 R >>";

        foreach ($objects as &$object) {
            $object = str_replace(
                [' /Parent 0 0 R', '/F1 0 0 R', '/F2 0 0 R'],
                [" /Parent {$pagesObjectId} 0 R", "/F1 {$fontRegularObjectId} 0 R", "/F2 {$fontBoldObjectId} 0 R"],
                $object,
            );
        }
        unset($object);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        for ($index = 1; $index <= count($objects); $index++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root {$catalogObjectId} 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
    }

    /**
     * @param  list<string>  $cells
     * @param  list<int>  $widths
     */
    protected function tableRow(array $cells, array $widths, bool $header = false, int $rowIndex = 0): void
    {
        $lineHeight = $header ? 13 : 11;
        $wrapped = [];
        $rowHeight = $lineHeight;

        foreach ($cells as $index => $cell) {
            $lines = $this->wrap($cell, max(7, (int) floor(($widths[$index] ?? 80) / 4.7)));
            $wrapped[] = $lines;
            $rowHeight = max($rowHeight, count($lines) * $lineHeight);
        }

        $this->ensureSpace($rowHeight + 8);
        $tableWidth = array_sum($widths);
        $rowY = $this->cursorY - $rowHeight + ($header ? 1 : 3);

        if ($header) {
            $this->fillRect(self::MARGIN_X - 6, $rowY - 4, $tableWidth + 12, $rowHeight + 12, '0.08 0.12 0.20');
        } elseif ($rowIndex % 2 === 0) {
            $this->fillRect(self::MARGIN_X - 6, $rowY - 4, $tableWidth + 12, $rowHeight + 10, '0.97 0.98 1');
        }

        $x = self::MARGIN_X;
        foreach ($wrapped as $index => $lines) {
            $cellY = $this->cursorY;
            foreach ($lines as $line) {
                $isNumericColumn = $index > 0 && preg_match('/[\d,.-]/', $line) === 1;
                $color = $header
                    ? '1 1 1'
                    : ($index === 3 ? $this->amountTextColor($line) : '0.08 0.12 0.20');

                if ($isNumericColumn) {
                    $this->writeTextRight($x + 3, $cellY, max(10, ($widths[$index] ?? 80) - 8), $line, $header ? 8 : 7, $header, $color);
                } else {
                    $this->writeText($x + 4, $cellY, $line, $header ? 8 : 7, $header, $color);
                }

                $cellY -= $lineHeight;
            }

            $x += $widths[$index] ?? 80;
        }

        if (! $header) {
            $this->commands[] = sprintf('0.92 0.94 0.97 RG %.2F %.2F m %.2F %.2F l S', self::MARGIN_X - 6, $rowY - 4, self::MARGIN_X - 6 + $tableWidth + 12, $rowY - 4);
        }

        $this->cursorY -= $rowHeight + 6;
    }

    /**
     * @return list<string>
     */
    protected function wrap(string $text, int $characters): array
    {
        $normalized = trim($this->normalize($text));

        if ($normalized === '') {
            return ['-'];
        }

        return explode("\n", wordwrap($normalized, $characters, "\n", true));
    }

    /**
     * @param  list<int>  $widths
     * @return list<int>
     */
    protected function fitTableWidths(array $widths): array
    {
        $availableWidth = self::PAGE_WIDTH - (self::MARGIN_X * 2) - 12;
        $tableWidth = array_sum($widths);

        if ($tableWidth <= $availableWidth) {
            return $widths;
        }

        $ratio = $availableWidth / max(1, $tableWidth);

        return collect($widths)
            ->map(fn (int $width): int => max(42, (int) floor($width * $ratio)))
            ->all();
    }

    protected function writeWrappedText(float $x, float $y, string $text, int $fontSize, bool $bold, string $rgb, int $characters, int $lineHeight, ?int $maxLines = null): void
    {
        $lines = $this->wrap($text, $characters);

        if ($maxLines !== null && count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lastIndex = count($lines) - 1;
            $lines[$lastIndex] = rtrim($lines[$lastIndex], ' .').'...';
        }

        foreach ($lines as $line) {
            $this->writeText($x, $y, $line, $fontSize, $bold, $rgb);
            $y -= $lineHeight;
        }
    }

    protected function line(): void
    {
        $y = $this->cursorY + 5;
        $this->commands[] = sprintf('0.78 0.82 0.9 RG %.2F %.2F m %.2F %.2F l S', self::MARGIN_X, $y, self::PAGE_WIDTH - self::MARGIN_X, $y);
    }

    protected function rect(float $x, float $y, float $width, float $height): void
    {
        $this->commands[] = sprintf('0.96 0.98 1 rg %.2F %.2F %.2F %.2F re f', $x, $y, $width, $height);
        $this->commands[] = sprintf('0.82 0.87 0.94 RG %.2F %.2F %.2F %.2F re S', $x, $y, $width, $height);
    }

    protected function fillRect(float $x, float $y, float $width, float $height, string $rgb): void
    {
        $this->commands[] = sprintf('%s rg %.2F %.2F %.2F %.2F re f', $rgb, $x, $y, $width, $height);
    }

    protected function strokeRect(float $x, float $y, float $width, float $height, string $rgb): void
    {
        $this->commands[] = sprintf('%s RG %.2F %.2F %.2F %.2F re S', $rgb, $x, $y, $width, $height);
    }

    protected function writeText(float $x, float $y, string $text, int $fontSize, bool $bold = false, string $rgb = '0.08 0.12 0.20'): void
    {
        $font = $bold ? 'F2' : 'F1';
        $escaped = $this->escape($this->normalize($text));
        $this->commands[] = "BT /{$font} {$fontSize} Tf {$rgb} rg {$x} {$y} Td ({$escaped}) Tj ET";
    }

    protected function writeTextRight(float $x, float $y, float $width, string $text, int $fontSize, bool $bold = false, string $rgb = '0.08 0.12 0.20'): void
    {
        $estimatedWidth = strlen($this->normalize($text)) * $fontSize * 0.52;
        $this->writeText(max($x, $x + $width - $estimatedWidth), $y, $text, $fontSize, $bold, $rgb);
    }

    protected function compactMetric(float $x, float $y, string $label, string $value, string $tone): void
    {
        $this->writeText($x, $y, $label, 6, true, $this->toneText($tone));
        $this->writeText($x, $y - 17, $value, 8, true, '0.08 0.12 0.20');
    }

    protected function toneFill(string $tone): string
    {
        return match ($tone) {
            'income' => '0.93 0.99 0.96',
            'expense' => '1 0.94 0.94',
            'positive' => '0.93 0.99 0.96',
            'negative' => '1 0.94 0.94',
            default => '0.96 0.98 1',
        };
    }

    protected function toneStroke(string $tone): string
    {
        return match ($tone) {
            'income', 'positive' => '0.52 0.84 0.66',
            'expense', 'negative' => '0.96 0.62 0.62',
            default => '0.82 0.87 0.94',
        };
    }

    protected function toneAccent(string $tone): string
    {
        return match ($tone) {
            'income', 'positive' => '0.09 0.64 0.36',
            'expense', 'negative' => '0.86 0.15 0.15',
            default => '0.08 0.18 0.32',
        };
    }

    protected function toneText(string $tone): string
    {
        return match ($tone) {
            'income', 'positive' => '0.05 0.46 0.25',
            'expense', 'negative' => '0.73 0.11 0.11',
            default => '0.39 0.45 0.55',
        };
    }

    protected function amountTextColor(string $amount): string
    {
        return str_contains($amount, '-')
            ? $this->toneText('negative')
            : $this->toneText('positive');
    }

    protected function amountTone(string $amount): string
    {
        if (str_contains($amount, '-')) {
            return 'negative';
        }

        return 'positive';
    }

    protected function ensureSpace(float $height): void
    {
        if ($this->cursorY - $height >= self::MARGIN_BOTTOM) {
            return;
        }

        $this->finishPage();
        $this->startPage();
    }

    protected function startPage(): void
    {
        $this->commands = [];
        $this->cursorY = self::PAGE_HEIGHT - self::MARGIN_TOP;
    }

    protected function finishPage(): void
    {
        if ($this->commands === []) {
            return;
        }

        $this->pages[] = implode("\n", $this->commands);
        $this->commands = [];
    }

    protected function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    protected function normalize(string $text): string
    {
        return Str::ascii($text);
    }
}
