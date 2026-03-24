<?php

namespace App\Enums;

enum AutomationTriggerTypeEnum: string
{
    case SCHEDULED = 'scheduled';
    case MANUAL = 'manual';
    case RETRY = 'retry';
    case SYSTEM = 'system';
}
