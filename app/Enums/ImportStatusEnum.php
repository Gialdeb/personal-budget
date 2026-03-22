<?php

namespace App\Enums;

enum ImportStatusEnum: string
{
    case UPLOADED = 'uploaded';
    case PARSED = 'parsed';
    case NORMALIZED = 'normalized';
    case REVIEW_REQUIRED = 'review_required';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case ROLLED_BACK = 'rolled_back';

    public function translationKey(): string
    {
        return match ($this) {
            self::UPLOADED => 'imports.enums.import_status.uploaded',
            self::PARSED => 'imports.enums.import_status.parsed',
            self::NORMALIZED => 'imports.enums.import_status.normalized',
            self::REVIEW_REQUIRED => 'imports.enums.import_status.review_required',
            self::COMPLETED => 'imports.enums.import_status.completed',
            self::FAILED => 'imports.enums.import_status.failed',
            self::ROLLED_BACK => 'imports.enums.import_status.rolled_back',
        };
    }

    public function label(): string
    {
        return __($this->translationKey());
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}
