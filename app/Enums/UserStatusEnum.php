<?php

namespace App\Enums;

enum UserStatusEnum: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case BANNED = 'banned';

    public function translationKey(): string
    {
        return match ($this) {
            self::ACTIVE => 'settings.enums.user_status.active',
            self::SUSPENDED => 'settings.enums.user_status.suspended',
            self::BANNED => 'settings.enums.user_status.banned',
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
}
