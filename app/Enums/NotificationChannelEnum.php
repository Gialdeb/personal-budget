<?php

namespace App\Enums;

enum NotificationChannelEnum: string
{
    case EMAIL = 'mail';
    case IN_APP = 'database';
    case SMS = 'sms';
}
