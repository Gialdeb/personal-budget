<?php

namespace App\Enums;

enum ImportFormatStatusEnum: string
{
    case ACTIVE = 'active';
    case DEPRECATED = 'deprecated';
    case DISABLED = 'disabled';

    public function translationKey(): string
    {
        return match ($this) {
            self::ACTIVE => 'imports.enums.format_status.active',
            self::DEPRECATED => 'imports.enums.format_status.deprecated',
            self::DISABLED => 'imports.enums.format_status.disabled',
        };
    }

    public function label(): string
    {
        return __($this->translationKey());
    }
}
