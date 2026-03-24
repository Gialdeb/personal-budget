<?php

namespace App\Enums;

enum NotificationPreferenceModeEnum: string
{
    case MANDATORY = 'mandatory';
    case USER_CONFIGURABLE = 'user_configurable';
    case ADMIN_CONFIGURABLE = 'admin_configurable';
    case SYSTEM = 'system';
}
