<?php

namespace App\Contracts\Automation;

use App\DTO\Automation\AutomationAlertData;

interface AutomationAlertChannelInterface
{
    public function send(AutomationAlertData $alert): void;
}
