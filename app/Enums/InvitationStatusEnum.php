<?php

namespace App\Enums;

enum InvitationStatusEnum: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';

    public function labelKey(): string
    {
        return match ($this) {
            self::PENDING => 'enums.invitation_status.pending',
            self::ACCEPTED => 'enums.invitation_status.accepted',
            self::EXPIRED => 'enums.invitation_status.expired',
            self::CANCELLED => 'enums.invitation_status.cancelled',
            self::REJECTED => 'enums.invitation_status.rejected',
        };
    }

    public function label(): string
    {
        return __($this->labelKey());
    }
}
