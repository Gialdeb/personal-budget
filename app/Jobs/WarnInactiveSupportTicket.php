<?php

namespace App\Jobs;

use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\SupportTicketActivityNotification;
use App\Services\Push\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Throwable;

class WarnInactiveSupportTicket implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $ticketId, public int $adminMessageId) {}

    public function uniqueId(): string
    {
        return 'support-inactivity-'.$this->ticketId.'-'.$this->adminMessageId;
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(PushNotificationService $push): void
    {
        $candidate = SupportRequest::find($this->ticketId);
        if (! $candidate) {
            return;
        }
        DB::transaction(function () use ($candidate, $push): void {
            $user = User::query()->whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
            $ticket = SupportRequest::query()->lockForUpdate()->find($candidate->id);
            if (! $ticket || $ticket->status !== SupportRequest::STATUS_ANSWERED || $ticket->inactivity_warned_at || ! $ticket->last_admin_reply_at || $ticket->last_admin_reply_at->greaterThan(now()->subDays(2)) || (int) $ticket->messages()->where('author_type', 'admin')->max('id') !== $this->adminMessageId) {
                return;
            }
            $locale = $user->preferredLocale();
            if (config('features.push_notifications.enabled') && $user->pushNotificationsEnabled()) {
                $delivery = $push->sendToUser($user, __('support.title', [], $locale), __('support.inactivity_warning', ['subject' => $ticket->subject], $locale), route('support.index', ['ticket' => $ticket->uuid]), ['ticket_uuid' => $ticket->uuid]);
                if (($delivery['sent_count'] ?? 0) === 0 && ($delivery['failed_count'] ?? 0) > ($delivery['invalidated_count'] ?? 0)) {
                    throw new \RuntimeException('Support inactivity push failed; retry required.');
                }
            }
            $notification = new SupportTicketActivityNotification($ticket->uuid, false, $ticket->category, $ticket->subject, true);
            $notification->id = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, $this->uniqueId());
            if (! $user->notifications()->whereKey($notification->id)->exists()) {
                $user->notifyNow($notification);
            }
            $ticket->update(['inactivity_warned_at' => now(), 'auto_close_at' => now()->addDay()]);
            $ticket->messages()->create(['author_type' => 'system', 'body' => 'inactivity_warning']);
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Support inactivity warning failed.', ['ticket_id' => $this->ticketId, 'exception' => $exception?->getMessage()]);
    }
}
