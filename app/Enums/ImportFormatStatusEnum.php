<?php

namespace App\Enums;

enum ImportFormatStatusEnum: string
{
    case ACTIVE = 'active';
    case DEPRECATED = 'deprecated';
    case DISABLED = 'disabled';
}
