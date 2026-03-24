<?php

namespace App\Services\Automation\Channels;

use App\Contracts\Automation\AutomationAlertChannelInterface;
use App\DTO\Automation\AutomationAlertData;
use Illuminate\Support\Facades\Log;

class LogAutomationAlertChannel implements AutomationAlertChannelInterface
{
    public function send(AutomationAlertData $alert): void
    {
        Log::warning('[AUTOMATION ALERT] '.$alert->title, [
            'type' => $alert->type,
            'pipeline' => $alert->pipeline,
            'message' => $alert->message,
            'context' => $alert->context,
        ]);
    }
}
