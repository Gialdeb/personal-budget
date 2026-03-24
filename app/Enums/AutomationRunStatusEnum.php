<?php

namespace App\Enums;

enum AutomationRunStatusEnum: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
    case TIMED_OUT = 'timed_out';
}
