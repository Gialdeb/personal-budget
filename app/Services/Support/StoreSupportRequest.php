<?php

namespace App\Services\Support;

use App\Jobs\DeliverSupportTicketMessage;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreSupportRequest
{
    public function __construct(private SupportTicketService $tickets) {}

    /** @param array<string, mixed> $payload */
    public function handle(User $user, array $payload): SupportRequest
    {
        return DB::transaction(function () use ($user, $payload): SupportRequest {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $ticket = SupportRequest::create([
                'user_id' => $user->id, 'category' => $payload['category'], 'subject' => $payload['subject'], 'message' => $payload['message'],
                'locale' => $payload['locale'] ?? $user->preferredLocale(), 'source_url' => $payload['source_url'] ?? null,
                'source_route' => $payload['source_route'] ?? null, 'meta' => $payload['meta'] ?? null,
                'status' => SupportRequest::STATUS_NEW, 'app_version' => $this->tickets->appVersion(), 'last_user_message_at' => now(),
            ]);
            $message = $ticket->messages()->create(['author_type' => 'user', 'user_id' => $user->id, 'body' => $payload['message']]);
            DeliverSupportTicketMessage::dispatch($message->id)->afterCommit();

            return $ticket;
        });
    }
}
