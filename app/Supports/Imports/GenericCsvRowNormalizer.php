<?php

namespace App\Supports\Imports;

use App\Enums\CategoryGroupTypeEnum;
use App\Enums\ImportRowStatusEnum;
use App\Models\Category;
use App\Supports\CategoryHierarchy;
use Carbon\Carbon;
use Illuminate\Support\Str;

class GenericCsvRowNormalizer
{
    public function normalize(array $rawRow, int $routeYear, ?int $accountId, int $userId): array
    {
        $errors = [];
        $warnings = [];
        $hasYearMismatch = false;

        $date = $this->normalizeDate($rawRow['date'] ?? null, $errors, $routeYear, $hasYearMismatch);
        $type = $this->normalizeType($rawRow['type'] ?? null, $errors);
        $amount = $this->normalizeAmount($rawRow['amount'] ?? null, $errors);
        $detail = $this->normalizeText($rawRow['detail'] ?? null);

        if ($detail === null) {
            $errors[] = __('imports.validation.required_detail');
        }

        $category = $this->normalizeText($rawRow['category'] ?? null);
        $reference = $this->normalizeText($rawRow['reference'] ?? null);
        $merchant = $this->normalizeText($rawRow['merchant'] ?? null);
        $externalReference = $this->normalizeText($rawRow['external_reference'] ?? null);
        $balance = $this->normalizeAmount($rawRow['balance'] ?? null, $warnings, false);

        $normalized = [
            'date' => $date,
            'type' => $type,
            'amount' => $amount,
            'detail' => $detail,
            'category' => $category,
            'reference' => $reference,
            'merchant' => $merchant,
            'external_reference' => $externalReference,
            'balance' => $balance,
        ];

        $status = $this->resolveStatus($normalized, $errors, $warnings, $userId, $hasYearMismatch);

        return [
            'normalized_payload' => $normalized,
            'errors' => $errors,
            'warnings' => $warnings,
            'status' => $status,
            'fingerprint' => empty($errors)
                ? ImportFingerprintGenerator::make($normalized, $userId, $accountId)
                : null,
        ];
    }

    protected function normalizeDate(?string $value, array &$errors, ?int $routeYear = null, bool &$hasYearMismatch = false): ?string
    {
        $value = $this->normalizeText($value);

        if ($value === null) {
            $errors[] = __('imports.validation.required_date');

            return null;
        }

        try {
            $date = Carbon::createFromFormat('d/m/Y', $value);

            if ($routeYear !== null && (int) $date->year !== $routeYear) {
                $hasYearMismatch = true;
                $errors[] = __('imports.validation.invalid_year', [
                    'year' => $date->year,
                    'route_year' => $routeYear,
                ]);

                return $date->format('Y-m-d');
            }

            return $date->format('Y-m-d');
        } catch (\Throwable) {
            $errors[] = __('imports.validation.invalid_date', ['value' => $value]);

            return null;
        }
    }

    protected function normalizeType(?string $value, array &$errors): ?string
    {
        $value = $this->normalizeText($value);

        if ($value === null) {
            $errors[] = __('imports.validation.required_type');

            return null;
        }

        $internalValues = ['income', 'expense', 'bill', 'debt', 'saving', 'transfer'];

        if (in_array($value, $internalValues, true)) {
            return $value;
        }

        $mapped = ImportColumnMap::mapTypeLabelToInternal($value);

        if ($mapped === null) {
            $errors[] = __('imports.validation.invalid_type', ['value' => $value]);

            return null;
        }

        return $mapped;
    }

    protected function normalizeAmount(?string $value, array &$messages, bool $required = true): ?string
    {
        $value = $this->normalizeText($value);

        if ($value === null) {
            if ($required) {
                $messages[] = __('imports.validation.required_amount');
            }

            return null;
        }

        $normalized = $this->normalizeLocalizedNumber($value);

        if ($normalized === null || ! is_numeric($normalized)) {
            $messages[] = __('imports.validation.invalid_amount', ['value' => $value]);

            return null;
        }

        $amount = (float) $normalized;

        if ($required && $amount <= 0) {
            $messages[] = __('imports.validation.amount_positive', ['value' => $value]);

            return null;
        }

        return number_format($amount, 2, '.', '');
    }

    protected function normalizeLocalizedNumber(string $value): ?string
    {
        $sanitized = preg_replace('/[\s\x{00A0}\']+/u', '', trim($value)) ?? '';

        if ($sanitized === '') {
            return null;
        }

        if (! preg_match('/^[+-]?[0-9.,]+$/', $sanitized)) {
            return null;
        }

        $sign = '';

        if (in_array($sanitized[0], ['+', '-'], true)) {
            $sign = $sanitized[0];
            $sanitized = substr($sanitized, 1);
        }

        if ($sanitized === '') {
            return null;
        }

        $commaCount = substr_count($sanitized, ',');
        $dotCount = substr_count($sanitized, '.');

        if ($commaCount > 0 && $dotCount > 0) {
            $decimalSeparator = strrpos($sanitized, ',') > strrpos($sanitized, '.')
                ? ','
                : '.';
            $thousandsSeparator = $decimalSeparator === ',' ? '.' : ',';

            return $this->normalizeWithSeparators(
                value: $sanitized,
                decimalSeparator: $decimalSeparator,
                thousandsSeparator: $thousandsSeparator,
                sign: $sign,
            );
        }

        if ($commaCount > 0) {
            return $this->normalizeSingleSeparatorNumber($sanitized, ',', $sign);
        }

        if ($dotCount > 0) {
            return $this->normalizeSingleSeparatorNumber($sanitized, '.', $sign);
        }

        return preg_match('/^\d+$/', $sanitized) === 1
            ? $sign.$sanitized
            : null;
    }

    protected function normalizeSingleSeparatorNumber(string $value, string $separator, string $sign): ?string
    {
        $parts = explode($separator, $value);

        if (count($parts) === 2) {
            [$integerPart, $fractionalOrGroupedPart] = $parts;

            if ($integerPart === '' || $fractionalOrGroupedPart === '') {
                return null;
            }

            if (strlen($fractionalOrGroupedPart) <= 2) {
                return $sign.$integerPart.'.'.$fractionalOrGroupedPart;
            }

            if (strlen($fractionalOrGroupedPart) === 3 && preg_match('/^\d+$/', $integerPart) === 1) {
                return $sign.$integerPart.$fractionalOrGroupedPart;
            }

            return null;
        }

        $lastPart = end($parts);

        if ($lastPart === false || $lastPart === '') {
            return null;
        }

        $allNumeric = collect($parts)->every(
            static fn (string $part): bool => $part !== '' && preg_match('/^\d+$/', $part) === 1,
        );

        if (! $allNumeric) {
            return null;
        }

        $prefixParts = array_slice($parts, 0, -1);
        $validThousandsGrouping = $prefixParts !== []
            && strlen($parts[0]) >= 1
            && collect(array_slice($parts, 1))->every(
                static fn (string $part): bool => strlen($part) === 3,
            );

        if (strlen($lastPart) <= 2 && $validThousandsGrouping) {
            return $sign.implode('', $prefixParts).'.'.$lastPart;
        }

        if (strlen($lastPart) === 3 && $validThousandsGrouping) {
            return $sign.implode('', $parts);
        }

        return null;
    }

    protected function normalizeWithSeparators(
        string $value,
        string $decimalSeparator,
        string $thousandsSeparator,
        string $sign,
    ): ?string {
        $parts = explode($decimalSeparator, $value);

        if (count($parts) !== 2) {
            return null;
        }

        [$integerPart, $fractionalPart] = $parts;

        if ($integerPart === '' || $fractionalPart === '' || strlen($fractionalPart) > 2) {
            return null;
        }

        $groupedIntegerPart = explode($thousandsSeparator, $integerPart);
        $validThousandsGrouping = count($groupedIntegerPart) === 1
            || (
                strlen($groupedIntegerPart[0]) >= 1
                && collect(array_slice($groupedIntegerPart, 1))->every(
                    static fn (string $part): bool => strlen($part) === 3,
                )
            );

        if (! $validThousandsGrouping) {
            return null;
        }

        $plainIntegerPart = implode('', $groupedIntegerPart);

        if (
            preg_match('/^\d+$/', $plainIntegerPart) !== 1
            || preg_match('/^\d+$/', $fractionalPart) !== 1
        ) {
            return null;
        }

        return $sign.$plainIntegerPart.'.'.$fractionalPart;
    }

    protected function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);

        return $value !== '' ? $value : null;
    }

    protected function resolveStatus(array $normalized, array $errors, array &$warnings, int $userId, bool $hasYearMismatch = false): string
    {
        if (! empty($errors)) {
            if ($hasYearMismatch) {
                return ImportRowStatusEnum::BLOCKED_YEAR->value;
            }

            return ImportRowStatusEnum::INVALID->value;
        }

        if (($normalized['type'] ?? null) === 'transfer') {
            return ImportRowStatusEnum::NEEDS_REVIEW->value;
        }

        if (($normalized['category'] ?? null) === null) {
            return ImportRowStatusEnum::NEEDS_REVIEW->value;
        }

        if (! $this->categoryExists($userId, (string) $normalized['category'])) {
            $warnings[] = __('imports.validation.category_unknown_review');

            return ImportRowStatusEnum::NEEDS_REVIEW->value;
        }

        return ImportRowStatusEnum::READY->value;
    }

    protected function categoryExists(int $userId, string $label): bool
    {
        $categories = Category::query()
            ->ownedBy($userId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('group_type')
                    ->orWhere('group_type', '!=', CategoryGroupTypeEnum::TRANSFER->value);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'uuid', 'parent_id', 'name', 'slug', 'group_type', 'direction_type', 'sort_order', 'is_active', 'is_selectable']);

        $normalizedLabel = mb_strtolower(trim($label));
        $slug = Str::slug($label);

        return collect(CategoryHierarchy::buildFlat($categories))
            ->contains(fn (array $category): bool => (bool) $category['is_selectable']
                && (mb_strtolower((string) $category['name']) === $normalizedLabel
                || mb_strtolower((string) $category['full_path']) === $normalizedLabel
                || (string) $category['slug'] === $slug));
    }
}
