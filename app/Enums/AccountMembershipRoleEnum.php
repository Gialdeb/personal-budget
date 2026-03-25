<?php

namespace App\Enums;

enum AccountMembershipRoleEnum: string
{
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';

    public function labelKey(): string
    {
        return match ($this) {
            self::OWNER => 'enums.account_membership_role.owner',
            self::MANAGER => 'enums.account_membership_role.manager',
            self::EDITOR => 'enums.account_membership_role.editor',
            self::VIEWER => 'enums.account_membership_role.viewer',
        };
    }

    public function label(): string
    {
        return __($this->labelKey());
    }
}
