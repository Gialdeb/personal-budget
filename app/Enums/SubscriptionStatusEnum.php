<?php

namespace App\Enums;

enum SubscriptionStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    case TRIALING = 'trialing';

    public function translationKey(): string
    {
        return match ($this) {
            self::ACTIVE => 'settings.enums.subscription_status.active',
            self::INACTIVE => 'settings.enums.subscription_status.inactive',
            self::PAST_DUE => 'settings.enums.subscription_status.past_due',
            self::CANCELED => 'settings.enums.subscription_status.canceled',
            self::TRIALING => 'settings.enums.subscription_status.trialing',
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
