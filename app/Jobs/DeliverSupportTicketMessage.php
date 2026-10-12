<?php

namespace App\Jobs;

use App\Mail\SupportRequestSubmittedMail;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\SupportTicketActivityNotification;
use App\Services\Push\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Ramsey\Uuid\Uuid;

class DeliverSupportTicketMessage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $messageId) {}

    public function uniqueId(): string
    {
        return 'support-message-'.$this->messageId;
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(PushNotificationService $push): void
    {
        $message = SupportTicketMessage::with('ticket.user')->find($this->messageId);
        if (! $message || ! $message->ticket?->user) {
            return;
        }
        $ticket = $message->ticket;
        if ($message->author_type === 'user') {
            $admins = User::role('admin')->get();
            if ($admins->isEmpty()) {
                throw new \RuntimeException('No support administrator is configured.');
            }
            foreach ($admins as $admin) {
                $this->notifyOnce($admin, true, $ticket->uuid, $ticket->category, $ticket->subject);
            }
            if (! $message->admin_notified_at) {
                $recipient = $admins->firstWhere('id', 1) ?? $admins->first();
                if ($recipient) {
                    Mail::to($recipient)->send(new SupportRequestSubmittedMail($ticket, $message->body));
                }
                $message->update(['admin_notified_at' => now()]);
            }

            return;
        }
        if ($message->author_type !== 'admin') {
            return;
        }
        $this->notifyOnce($ticket->user, false, $ticket->uuid, $ticket->category, $ticket->subject);
        if (! $message->push_sent_at) {
            if (config('features.push_notifications.enabled') && $ticket->user->pushNotificationsEnabled()) {
                $locale = $ticket->user->preferredLocale();
                $delivery = $push->sendToUser($ticket->user, __('support.title', [], $locale), __('support.user_activity', [], $locale), route('support.index', ['ticket' => $ticket->uuid]), ['ticket_uuid' => $ticket->uuid]);
                Log::info('Support ticket push delivery completed.', ['ticket_uuid' => $ticket->uuid, ...$delivery]);
                if (($delivery['sent_count'] ?? 0) === 0 && ($delivery['failed_count'] ?? 0) > ($delivery['invalidated_count'] ?? 0)) {
                    throw new \RuntimeException('Support push delivery failed; retry required.');
                }
            }
            $message->update(['push_sent_at' => now()]);
        }
    }

    protected function notifyOnce(User $user, bool $admin, string $uuid, string $category, string $subject): void
    {
        $id = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'support:'.$this->messageId.':'.$user->id);
        if ($user->notifications()->whereKey($id)->exists()) {
            return;
        }
        $notification = new SupportTicketActivityNotification($uuid, $admin, $category, $subject);
        $notification->id = $id;
        $user->notifyNow($notification);
    }
}
