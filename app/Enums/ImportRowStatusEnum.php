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

    public function translationKey(): string
    {
        return match ($this) {
            self::PARSED => 'imports.enums.row_status.parsed',
            self::READY => 'imports.enums.row_status.ready',
            self::NEEDS_REVIEW => 'imports.enums.row_status.needs_review',
            self::INVALID => 'imports.enums.row_status.invalid',
            self::BLOCKED_YEAR => 'imports.enums.row_status.blocked_year',
            self::DUPLICATE_CANDIDATE => 'imports.enums.row_status.duplicate_candidate',
            self::ALREADY_IMPORTED => 'imports.enums.row_status.already_imported',
            self::IMPORTED => 'imports.enums.row_status.imported',
            self::SKIPPED => 'imports.enums.row_status.skipped',
            self::ROLLED_BACK => 'imports.enums.row_status.rolled_back',
        };
    }

    public function label(): string
    {
        return __($this->translationKey());
    }
}
