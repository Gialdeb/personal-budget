<?php

namespace App\Services\Support;

use App\Jobs\WarnInactiveSupportTicket;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SupportTicketInactivityService
{
    public function process(): void
    {
        SupportRequest::query()->where('status', SupportRequest::STATUS_ANSWERED)
            ->where('last_admin_reply_at', '<=', now()->subDays(2))
            ->chunkById(100, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    if ($ticket->auto_close_at) {
                        $this->closeIfDue($ticket);
                    } else {
                        $messageId = $ticket->messages()->where('author_type', 'admin')->max('id');
                        if ($messageId) {
                            WarnInactiveSupportTicket::dispatch($ticket->id, $messageId);
                        }
                    }
                }
            });
    }

    public function closeIfDue(SupportRequest $ticket): void
    {
        DB::transaction(function () use ($ticket): void {
            User::query()->whereKey($ticket->user_id)->lockForUpdate()->firstOrFail();
            $ticket = SupportRequest::query()->lockForUpdate()->find($ticket->id);
            if (! $ticket || $ticket->status !== SupportRequest::STATUS_ANSWERED || ! $ticket->inactivity_warned_at || ! $ticket->auto_close_at?->isPast()) {
                return;
            }
            $ticket->update(['status' => SupportRequest::STATUS_CLOSED, 'closed_at' => now()]);
            $ticket->messages()->create(['author_type' => 'system', 'body' => 'auto_closed']);
        });
    }
}
