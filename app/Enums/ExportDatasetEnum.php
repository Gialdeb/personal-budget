<?php

namespace App\Enums;

enum ExportDatasetEnum: string
{
    case TRANSACTIONS = 'transactions';
    case ACCOUNTS = 'accounts';
    case CATEGORIES = 'categories';
    case TRACKED_ITEMS = 'tracked_items';
    case RECURRING_ENTRIES = 'recurring_entries';
    case BUDGETS = 'budgets';
    case FULL_EXPORT = 'full_export';

    public function supportsPeriod(): bool
    {
        return match ($this) {
            self::TRANSACTIONS,
            self::RECURRING_ENTRIES,
            self::BUDGETS => true,
            self::ACCOUNTS,
            self::CATEGORIES,
            self::TRACKED_ITEMS,
            self::FULL_EXPORT => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public function availableFormatValues(): array
    {
        return array_map(
            fn (ExportFormatEnum $format): string => $format->value,
            $this->availableFormats()
        );
    }

    /**
     * @return array<int, ExportFormatEnum>
     */
    public function availableFormats(): array
    {
        return match ($this) {
            self::FULL_EXPORT => [ExportFormatEnum::JSON],
            default => [ExportFormatEnum::CSV, ExportFormatEnum::JSON],
        };
    }

    public function defaultFormat(): ExportFormatEnum
    {
        return $this->availableFormats()[0];
    }

    public function filePrefix(): string
    {
        return match ($this) {
            self::FULL_EXPORT => 'full-export',
            default => $this->value,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
