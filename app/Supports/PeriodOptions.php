<?php

namespace App\Supports;

class PeriodOptions
{
    public static function monthMap(): array
    {
        return [
            1 => 'Gen',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mag',
            6 => 'Giu',
            7 => 'Lug',
            8 => 'Ago',
            9 => 'Set',
            10 => 'Ott',
            11 => 'Nov',
            12 => 'Dic',
        ];
    }

    public static function monthOptions(bool $includeAll = true): array
    {
        $options = [];

        if ($includeAll) {
            $options[] = [
                'value' => null,
                'label' => 'Tutto',
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
