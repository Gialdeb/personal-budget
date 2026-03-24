<?php

namespace App\Enums;

enum NotificationAudienceEnum: string
{
    case USER = 'user';
    case ADMIN = 'admin';
    case BOTH = 'both';
}
