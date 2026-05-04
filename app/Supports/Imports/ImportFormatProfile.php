<?php

namespace App\Supports\Imports;

class ImportFormatProfile
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(protected array $settings) {}

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public static function fromSettings(?array $settings): ?self
    {
        if (! self::isValid($settings)) {
            return null;
        }

        return new self($settings);
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public static function isValid(?array $settings): bool
    {
        if (! is_array($settings)) {
            return false;
        }

        $sourceTypes = $settings['source_types'] ?? null;
        $headerRow = $settings['header_row'] ?? null;
        $columns = $settings['columns'] ?? null;

        if (! is_array($sourceTypes) || ! is_int($headerRow) || $headerRow < 1 || ! is_array($columns)) {
            return false;
        }

        $amount = $settings['amount'] ?? [];
        $amountMode = is_array($amount) && is_string($amount['mode'] ?? null)
            ? $amount['mode']
            : 'signed_amount';
        $requiredColumns = in_array($amountMode, ['debit_credit', 'separate_debit_credit'], true)
            ? ['date', 'description']
            : ['date', 'amount', 'description'];

        foreach ($requiredColumns as $requiredColumn) {
            if (! isset($columns[$requiredColumn]) || ! is_string($columns[$requiredColumn]) || trim($columns[$requiredColumn]) === '') {
                return false;
            }
        }

        if (
            in_array($amountMode, ['debit_credit', 'separate_debit_credit'], true)
            && (
                ! isset($amount['debit_column'], $amount['credit_column'])
                || ! is_string($amount['debit_column'])
                || ! is_string($amount['credit_column'])
                || trim($amount['debit_column']) === ''
                || trim($amount['credit_column']) === ''
            )
        ) {
            return false;
        }

        return collect($sourceTypes)
            ->every(fn ($sourceType): bool => in_array($sourceType, ['csv', 'xlsx'], true));
    }

    /**
     * @return array<int, string>
     */
    public function sourceTypes(): array
    {
        return collect($this->settings['source_types'] ?? [])
            ->filter(fn ($sourceType): bool => is_string($sourceType) && in_array($sourceType, ['csv', 'xlsx'], true))
            ->values()
            ->all();
    }

    public function supportsSourceType(string $sourceType): bool
    {
        return in_array($sourceType, $this->sourceTypes(), true);
    }

    public function headerRow(): int
    {
        return max(1, (int) ($this->settings['header_row'] ?? 1));
    }

    public function sheetName(): ?string
    {
        $value = $this->settings['sheet_name'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array<int, int>
     */
    public function skipRows(): array
    {
        return collect($this->settings['skip_rows'] ?? [])
            ->filter(fn ($row): bool => is_int($row) && $row > 0)
            ->values()
            ->all();
    }

    public function column(string $key): ?string
    {
        $value = $this->settings['columns'][$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function timeColumn(): ?string
    {
        $value = $this->settings['date_time']['time_column'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function dateTimeFormat(): ?string
    {
        $value = $this->settings['date_time']['format'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function amountMode(): string
    {
        $mode = $this->settings['amount']['mode'] ?? 'signed_amount';

        return is_string($mode) && in_array($mode, ['signed_amount', 'signed_amount_with_fee_fallback', 'debit_credit', 'separate_debit_credit'], true)
            ? $mode
            : 'signed_amount';
    }

    public function feeColumn(): ?string
    {
        $value = $this->settings['amount']['fee_column'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function completedStateColumn(): ?string
    {
        $value = $this->settings['state']['column'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array<int, string>
     */
    public function completedStateValues(): array
    {
        return collect($this->settings['state']['completed_values'] ?? [])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => mb_strtoupper(trim($value)))
            ->values()
            ->all();
    }

    public function reviewTypeColumn(): ?string
    {
        $value = $this->settings['review_types']['column'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array<int, string>
     */
    public function reviewTypeValues(): array
    {
        return collect($this->settings['review_types']['values'] ?? [])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => mb_strtoupper(trim($value)))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function metadataColumns(): array
    {
        return collect($this->settings['metadata_columns'] ?? [])
            ->filter(fn ($column): bool => is_string($column) && trim($column) !== '')
            ->map(fn (string $column): string => trim($column))
            ->all();
    }

    public function debitColumn(): ?string
    {
        $value = $this->settings['amount']['debit_column'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function creditColumn(): ?string
    {
        $value = $this->settings['amount']['credit_column'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function debitSign(): string
    {
        $sign = $this->settings['amount']['debit_sign'] ?? 'negative';

        return is_string($sign) && in_array($sign, ['negative', 'positive'], true)
            ? $sign
            : 'negative';
    }

    public function dateFormat(): string
    {
        $format = $this->settings['normalization']['date_format'] ?? 'd/m/Y';

        return is_string($format) && trim($format) !== '' ? trim($format) : 'd/m/Y';
    }

    public function collapseDescriptionSpaces(): bool
    {
        return (bool) ($this->settings['normalization']['description_cleanup']['collapse_spaces'] ?? true);
    }

    public function uppercaseDescription(): bool
    {
        return (bool) ($this->settings['normalization']['description_cleanup']['uppercase'] ?? false);
    }
}
