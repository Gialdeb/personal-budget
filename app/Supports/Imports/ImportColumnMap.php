<?php

namespace App\Supports\Imports;

class ImportColumnMap
{
    public const CANONICAL_COLUMNS = [
        'date',
        'type',
        'amount',
        'detail',
        'category',
        'reference',
        'merchant',
        'external_reference',
        'balance',
    ];

    public const ITALIAN_HEADERS = [
        'data' => 'date',
        'tipo' => 'type',
        'importo' => 'amount',
        'dettaglio' => 'detail',
        'categoria' => 'category',
        'riferimento' => 'reference',
        'esercente' => 'merchant',
        'riferimento esterno' => 'external_reference',
        'saldo' => 'balance',
    ];

    public static function normalizeHeader(string $header): ?string
    {
        $header = preg_replace('/\s+/', ' ', trim($header));
        $header = ltrim($header, "\xEF\xBB\xBF");
        $header = mb_strtolower($header);

        return self::ITALIAN_HEADERS[$header] ?? null;
    }

    public static function requiredColumns(): array
    {
        return [
            'date',
            'type',
            'amount',
            'detail',
        ];
    }

    public static function allowedTypeLabels(): array
    {
        return [
            __('imports.enums.type_labels.income'),
            __('imports.enums.type_labels.expense'),
            __('imports.enums.type_labels.bill'),
            __('imports.enums.type_labels.debt'),
            __('imports.enums.type_labels.saving'),
            __('imports.enums.type_labels.transfer'),
        ];
    }

    public static function mapTypeLabelToInternal(string $label): ?string
    {
        return match (trim($label)) {
            __('imports.enums.type_labels.income') => 'income',
            __('imports.enums.type_labels.expense') => 'expense',
            __('imports.enums.type_labels.bill') => 'bill',
            __('imports.enums.type_labels.debt') => 'debt',
            __('imports.enums.type_labels.saving') => 'saving',
            __('imports.enums.type_labels.transfer') => 'transfer',
            default => null,
        };
    }
}
