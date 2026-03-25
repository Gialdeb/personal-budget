<?php

namespace App\Enums;

enum HouseholdStatusEnum: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';

    public function labelKey(): string
    {
        return match ($this) {
            self::ACTIVE => 'enums.household_status.active',
            self::ARCHIVED => 'enums.household_status.archived',
        };
    }

    public function label(): string
    {
        return __($this->labelKey());
    }
}
