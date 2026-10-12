<?php

namespace App\Services\Support;

use App\Jobs\DeliverSupportTicketMessage;
use App\Models\ChangelogRelease;
use App\Models\SupportRequest;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function appVersion(): ?string
    {
        return ChangelogRelease::query()->where('is_published', true)->ordered()->value('version_label');
    }

    public function reply(User $actor, SupportRequest $ticket, string $body, bool $admin = false): void
    {
        DB::transaction(function () use ($actor, $ticket, $body, $admin): void {
            User::query()->whereKey($ticket->user_id)->lockForUpdate()->firstOrFail();
            $ticket = SupportRequest::query()->lockForUpdate()->findOrFail($ticket->id);
            abort_unless($admin ? $actor->hasRole('admin') : $ticket->user_id === $actor->id, 404);
            if ($ticket->status !== ($admin ? SupportRequest::STATUS_NEW : SupportRequest::STATUS_ANSWERED)) {
                throw ValidationException::withMessages(['body' => __('support.wait_turn')]);
            }
            $message = $ticket->messages()->create(['author_type' => $admin ? 'admin' : 'user', 'user_id' => $admin ? null : $actor->id, 'admin_id' => $admin ? $actor->id : null, 'body' => $body]);
            $ticket->update(['inactivity_warned_at' => null, 'auto_close_at' => null, 'status' => $admin ? SupportRequest::STATUS_ANSWERED : SupportRequest::STATUS_NEW, $admin ? 'last_admin_reply_at' : 'last_user_message_at' => now()]);
            DeliverSupportTicketMessage::dispatch($message->id)->afterCommit();
        });
    }

    public function changeStatus(User $actor, SupportRequest $ticket, string $status): void
    {
        abort_unless($actor->hasRole('admin'), 403);
        DB::transaction(function () use ($ticket, $status, $actor): void {
            User::query()->whereKey($ticket->user_id)->lockForUpdate()->firstOrFail();
            $ticket = SupportRequest::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($status === SupportRequest::STATUS_ANSWERED) {
                throw ValidationException::withMessages(['status' => __('support.reply_to_answer')]);
            }
            if ($ticket->status === $status) {
                return;
            }
            if ($status !== SupportRequest::STATUS_CLOSED && $ticket->status !== SupportRequest::STATUS_CLOSED) {
                throw ValidationException::withMessages(['status' => __('support.reply_to_answer')]);
            }
            $ticket->update(['inactivity_warned_at' => null, 'auto_close_at' => null, 'status' => $status, 'closed_at' => $status === SupportRequest::STATUS_CLOSED ? now() : null]);
            $ticket->messages()->create(['author_type' => 'system', 'admin_id' => $actor->id, 'body' => $status === SupportRequest::STATUS_CLOSED ? 'closed' : 'reopened']);
        });
    }

    /** @return array<string, mixed> */
    public function userPayload(SupportRequest $ticket): array
    {
        return ['uuid' => $ticket->uuid, 'category' => $ticket->category, 'subject' => $ticket->subject,
            'auto_close_at' => $ticket->auto_close_at?->toIso8601String(), 'status' => $ticket->status, 'app_version' => $ticket->app_version, 'created_at' => $ticket->created_at->toIso8601String(), 'closed_at' => $ticket->closed_at?->toIso8601String(),
            'messages' => $ticket->messages->map(fn (SupportTicketMessage $message): array => ['uuid' => $message->uuid, 'author_type' => $message->author_type, 'body' => $message->body, 'created_at' => $message->created_at->toIso8601String()])->all()];
    }
}
