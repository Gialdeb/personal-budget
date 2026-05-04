<?php

namespace App\Supports\Imports;

use Carbon\Carbon;

class ImportFormatRowMapper
{
    /**
     * @param  array<string, string|null>  $record
     * @return array<string, mixed>
     */
    public function map(array $record, ImportFormatProfile $profile): array
    {
        $amount = $this->resolveAmount($record, $profile);
        $warnings = $amount['warnings'];
        $state = $this->value($record, $profile->completedStateColumn());
        $reviewType = $this->value($record, $profile->reviewTypeColumn());

        if (
            $state !== null
            && $profile->completedStateValues() !== []
            && ! in_array(mb_strtoupper($state), $profile->completedStateValues(), true)
        ) {
            $warnings[] = __('imports.validation.import_state_not_completed', ['state' => $state]);
        }

        if (
            $reviewType !== null
            && $profile->reviewTypeValues() !== []
            && in_array(mb_strtoupper($reviewType), $profile->reviewTypeValues(), true)
        ) {
            $warnings[] = __('imports.validation.import_type_requires_review', ['type' => $reviewType]);
        }

        return [
            'account' => $this->value($record, $profile->column('account')),
            'date' => $this->dateValue($record, $profile),
            'value_date' => $this->normalizeDate($this->value($record, $profile->column('value_date')), $profile),
            'type' => $this->resolveType($record, $profile, $amount['signed']),
            'amount' => $amount['absolute'],
            'detail' => $this->normalizeDescription($this->value($record, $profile->column('description')), $profile),
            'category' => $this->value($record, $profile->column('category')),
            'destination_account' => $this->value($record, $profile->column('destination_account')),
            'reference' => $this->value($record, $profile->column('reference')),
            'merchant' => $this->value($record, $profile->column('merchant')),
            'external_reference' => $this->value($record, $profile->column('external_reference')),
            'balance' => $this->value($record, $profile->column('balance')),
            'currency' => $this->normalizeCurrency($this->value($record, $profile->column('currency'))),
            'import_metadata' => $this->metadata($record, $profile),
            '_mapping_warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, string|null>  $record
     * @return array{absolute: string|null, signed: float|null, warnings: array<int, string>}
     */
    protected function resolveAmount(array $record, ImportFormatProfile $profile): array
    {
        if (in_array($profile->amountMode(), ['debit_credit', 'separate_debit_credit'], true)) {
            $debit = $this->numericValue($this->value($record, $profile->debitColumn()));
            $credit = $this->numericValue($this->value($record, $profile->creditColumn()));
            $hasDebit = $debit !== null && abs($debit) > 0;
            $hasCredit = $credit !== null && abs($credit) > 0;

            if ($hasDebit && $hasCredit) {
                return [
                    'absolute' => null,
                    'signed' => null,
                    'warnings' => [__('imports.validation.amount_debit_credit_conflict')],
                ];
            }

            if ($hasDebit) {
                $signed = $profile->debitSign() === 'negative' ? -abs($debit) : abs($debit);

                return [
                    'absolute' => $this->formatAmount(abs($signed)),
                    'signed' => $signed,
                    'warnings' => [],
                ];
            }

            if ($hasCredit) {
                return [
                    'absolute' => $this->formatAmount(abs($credit)),
                    'signed' => abs($credit),
                    'warnings' => [],
                ];
            }

            return [
                'absolute' => null,
                'signed' => null,
                'warnings' => [__('imports.validation.amount_debit_credit_missing')],
            ];
        }

        $signed = $this->numericValue($this->value($record, $profile->column('amount')));

        if ($profile->amountMode() === 'signed_amount_with_fee_fallback') {
            $fee = $this->numericValue($this->value($record, $profile->feeColumn()));

            if ($signed !== null && abs($signed) > 0) {
                return [
                    'absolute' => $this->formatAmount(abs($signed)),
                    'signed' => $signed,
                    'warnings' => [],
                ];
            }

            if ($fee !== null && abs($fee) > 0) {
                return [
                    'absolute' => $this->formatAmount(abs($fee)),
                    'signed' => -abs($fee),
                    'warnings' => [],
                ];
            }
        }

        return [
            'absolute' => $signed === null ? null : $this->formatAmount(abs($signed)),
            'signed' => $signed,
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, string|null>  $record
     */
    protected function resolveType(array $record, ImportFormatProfile $profile, ?float $signedAmount): ?string
    {
        $mappedType = $this->value($record, $profile->column('type'));

        if ($mappedType !== null) {
            return $mappedType;
        }

        if ($signedAmount === null) {
            return null;
        }

        return $signedAmount < 0 ? 'expense' : 'income';
    }

    /**
     * @param  array<string, string|null>  $record
     */
    protected function value(array $record, ?string $column): ?string
    {
        if ($column === null) {
            return null;
        }

        $value = $record[$column] ?? null;

        if ($value === null) {
            $value = $this->valueByNormalizedColumn($record, $column);

            if ($value === null) {
                return null;
            }
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, string|null>  $record
     */
    protected function valueByNormalizedColumn(array $record, string $column): ?string
    {
        $expected = mb_strtolower(trim(ltrim($column, "\xEF\xBB\xBF")));

        foreach ($record as $key => $value) {
            if (mb_strtolower(trim(ltrim((string) $key, "\xEF\xBB\xBF"))) === $expected) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $record
     */
    protected function dateValue(array $record, ImportFormatProfile $profile): ?string
    {
        $date = $this->value($record, $profile->column('date'));
        $time = $this->value($record, $profile->timeColumn());
        $dateTimeFormat = $profile->dateTimeFormat();

        if ($date !== null && $time !== null && $dateTimeFormat !== null) {
            try {
                return Carbon::createFromFormat($dateTimeFormat, trim($date.' '.$time))->format('d/m/Y');
            } catch (\Throwable) {
                return $this->normalizeDate($date, $profile);
            }
        }

        return $this->normalizeDate($date, $profile);
    }

    protected function normalizeDate(?string $value, ImportFormatProfile $profile): ?string
    {
        if ($value === null || $profile->dateFormat() === 'd/m/Y') {
            return $value;
        }

        try {
            return Carbon::createFromFormat($profile->dateFormat(), $value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    protected function normalizeDescription(?string $value, ImportFormatProfile $profile): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($profile->collapseDescriptionSpaces()) {
            $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        }

        if ($profile->uppercaseDescription()) {
            $value = mb_strtoupper($value);
        }

        return trim($value) !== '' ? trim($value) : null;
    }

    protected function normalizeCurrency(?string $value): ?string
    {
        return $value === null ? null : mb_strtoupper($value);
    }

    /**
     * @param  array<string, string|null>  $record
     * @return array<string, string>
     */
    protected function metadata(array $record, ImportFormatProfile $profile): array
    {
        return collect($profile->metadataColumns())
            ->mapWithKeys(function (string $column, string $key) use ($record): array {
                $value = $this->value($record, $column);

                return $value === null ? [] : [$key => $value];
            })
            ->all();
    }

    protected function numericValue(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/[\s\x{00A0}\']+/u', '', trim($value)) ?? '';

        if ($value === '') {
            return null;
        }

        $sign = 1;

        if (str_starts_with($value, '-')) {
            $sign = -1;
            $value = substr($value, 1);
        } elseif (str_starts_with($value, '+')) {
            $value = substr($value, 1);
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        $decimalSeparator = match (true) {
            $lastComma !== false && $lastDot !== false => $lastComma > $lastDot ? ',' : '.',
            $lastComma !== false => ',',
            $lastDot !== false => '.',
            default => null,
        };

        if ($decimalSeparator !== null) {
            $thousandsSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($thousandsSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);
        }

        return is_numeric($value) ? $sign * (float) $value : null;
    }

    protected function formatAmount(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
