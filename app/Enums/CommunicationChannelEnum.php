<?php

namespace App\Enums;

enum CommunicationChannelEnum: string
{
    case MAIL = 'mail';
    case DATABASE = 'database';
    case SMS = 'sms';
    case TELEGRAM = 'telegram';
}
