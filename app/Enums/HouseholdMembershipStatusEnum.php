<?php

namespace App\Enums;

enum HouseholdMembershipStatusEnum: string
{
    case ACTIVE = 'active';
    case LEFT = 'left';
    case REVOKED = 'revoked';
    case REJECTED = 'rejected';

    public function labelKey(): string
    {
        return match ($this) {
            self::ACTIVE => 'enums.household_membership_status.active',
            self::LEFT => 'enums.household_membership_status.left',
            self::REVOKED => 'enums.household_membership_status.revoked',
            self::REJECTED => 'enums.household_membership_status.rejected',
        };
    }

    public function label(): string
    {
        return __($this->labelKey());
    }
}
