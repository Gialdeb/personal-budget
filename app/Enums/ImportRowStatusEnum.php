<?php

namespace App\Enums;

enum ImportRowStatusEnum: string
{
    case PARSED = 'parsed';
    case READY = 'ready';
    case NEEDS_REVIEW = 'needs_review';
    case INVALID = 'invalid';
    case BLOCKED_YEAR = 'blocked_year';
    case DUPLICATE_CANDIDATE = 'duplicate_candidate';
    case ALREADY_IMPORTED = 'already_imported';
    case IMPORTED = 'imported';
    case SKIPPED = 'skipped';
    case ROLLED_BACK = 'rolled_back';

    public function label(): string
    {
        return match ($this) {
            self::PARSED => 'Analizzata',
            self::READY => 'Pronta',
            self::NEEDS_REVIEW => 'Da rivedere',
            self::INVALID => 'Non valida',
            self::BLOCKED_YEAR => 'Anno non disponibile',
            self::DUPLICATE_CANDIDATE => 'Possibile duplicato',
            self::ALREADY_IMPORTED => 'Già importata',
            self::IMPORTED => 'Importata',
            self::SKIPPED => 'Saltata',
            self::ROLLED_BACK => 'Annullata',
        };
    }
}
