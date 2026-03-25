<?php

namespace App\Enums;

enum CommunicationTemplateModeEnum: string
{
    case SYSTEM = 'system';
    case CUSTOMIZABLE = 'customizable';
    case FREEFORM = 'freeform';
}
