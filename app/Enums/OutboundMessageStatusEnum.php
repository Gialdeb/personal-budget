<?php

namespace App\Enums;

enum OutboundMessageStatusEnum: string
{
    case DRAFT = 'draft';
    case SKIPPED = 'skipped';
    case QUEUED = 'queued';
    case SENT = 'sent';
    case FAILED = 'failed';
}
