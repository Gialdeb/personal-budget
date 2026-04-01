<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Notifications\TelegramAdminAlertService;
use Illuminate\Auth\Events\Verified;

class SendVerifiedUserTelegramAlert
{
    public function __construct(
        protected TelegramAdminAlertService $telegramAdminAlertService,
    ) {}

    public function handle(Verified $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->telegramAdminAlertService->sendVerifiedUserAlert($event->user);
    }
}
