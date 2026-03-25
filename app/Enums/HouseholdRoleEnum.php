<?php

namespace App\Enums;

enum HouseholdRoleEnum: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    public function labelKey(): string
    {
        return match ($this) {
            self::OWNER => 'enums.household_role.owner',
            self::ADMIN => 'enums.household_role.admin',
            self::MEMBER => 'enums.household_role.member',
            self::VIEWER => 'enums.household_role.viewer',
        };
    }

    public function label(): string
    {
        return __($this->labelKey());
    }
}
