<?php

namespace App\Supports;

class PeriodOptions
{
    public static function monthMap(): array
    {
        return [
            1 => __('app.periods.months.short.1'),
            2 => __('app.periods.months.short.2'),
            3 => __('app.periods.months.short.3'),
            4 => __('app.periods.months.short.4'),
            5 => __('app.periods.months.short.5'),
            6 => __('app.periods.months.short.6'),
            7 => __('app.periods.months.short.7'),
            8 => __('app.periods.months.short.8'),
            9 => __('app.periods.months.short.9'),
            10 => __('app.periods.months.short.10'),
            11 => __('app.periods.months.short.11'),
            12 => __('app.periods.months.short.12'),
        ];
    }

    public static function monthOptions(bool $includeAll = true): array
    {
        $options = [];

        if ($includeAll) {
            $options[] = [
                'value' => null,
                'label' => __('app.periods.all'),
            ];
        }

        foreach (self::monthMap() as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }

    public static function yearOptions(array $years): array
    {
        return collect($years)
            ->filter(fn ($year) => is_numeric($year))
            ->map(fn ($year) => [
                'value' => (int) $year,
                'label' => (string) $year,
            ])
            ->values()
            ->all();
    }

    public static function monthLabel(int $month): ?string
    {
        return self::monthMap()[$month] ?? null;
    }

    public static function isValidMonth(?int $month, bool $allowNull = true): bool
    {
        if ($allowNull && $month === null) {
            return true;
        }

        return is_int($month) && $month >= 1 && $month <= 12;
    }

    public static function normalizeMonth(mixed $month): ?int
    {
        if ($month === null || $month === '' || $month === 0 || $month === '0') {
            return null;
        }

        if (is_numeric($month)) {
            $month = (int) $month;

            return ($month >= 1 && $month <= 12) ? $month : null;
        }

        return null;
    }
}
