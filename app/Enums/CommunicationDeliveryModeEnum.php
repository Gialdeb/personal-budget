<?php

namespace App\Enums;

enum CommunicationDeliveryModeEnum: string
{
    case TRANSACTIONAL = 'transactional';
    case CAMPAIGN = 'campaign';
    case MANUAL = 'manual';
    case SYSTEM = 'system';
}
