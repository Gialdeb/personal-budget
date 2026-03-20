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

    public function label(): string
    {
        return match ($this) {
            self::UPLOADED => 'Caricato',
            self::PARSED => 'Analizzato',
            self::NORMALIZED => 'Normalizzato',
            self::REVIEW_REQUIRED => 'Richiede revisione',
            self::COMPLETED => 'Completato',
            self::FAILED => 'Fallito',
            self::ROLLED_BACK => 'Annullato',
        };
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
