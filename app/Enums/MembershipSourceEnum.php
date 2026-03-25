<?php

namespace App\Enums;

enum MembershipSourceEnum: string
{
    case MIGRATION = 'migration';
    case DIRECT = 'direct';
    case HOUSEHOLD = 'household';
    case INVITATION = 'invitation';

    public function labelKey(): string
    {
        return match ($this) {
            self::MIGRATION => 'enums.membership_source.migration',
            self::DIRECT => 'enums.membership_source.direct',
            self::HOUSEHOLD => 'enums.membership_source.household',
            self::INVITATION => 'enums.membership_source.invitation',
        };
    }

    public function label(): string
    {
        return __($this->labelKey());
    }
}
