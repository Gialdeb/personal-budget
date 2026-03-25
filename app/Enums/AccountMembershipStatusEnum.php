<?php

namespace App\Enums;

enum AccountMembershipStatusEnum: string
{
    case ACTIVE = 'active';
    case LEFT = 'left';
    case REVOKED = 'revoked';

    public function labelKey(): string
    {
        return match ($this) {
            self::ACTIVE => 'enums.account_membership_status.active',
            self::LEFT => 'enums.account_membership_status.left',
            self::REVOKED => 'enums.account_membership_status.revoked',
        };
    }

    public function label(): string
    {
        return __($this->labelKey());
    }
}
