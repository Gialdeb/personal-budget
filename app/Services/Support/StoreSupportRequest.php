<?php

namespace App\Services\Support;

use App\Mail\SupportRequestSubmittedMail;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class StoreSupportRequest
{
    private const INTERNAL_SUPPORT_ADMIN_ID = 1;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(User $user, array $payload): SupportRequest
    {
        return DB::transaction(function () use ($user, $payload): SupportRequest {
            $supportRequest = SupportRequest::query()->create([
                'user_id' => $user->id,
                'category' => (string) $payload['category'],
                'subject' => (string) $payload['subject'],
                'message' => (string) $payload['message'],
                'locale' => (string) ($payload['locale'] ?? $user->preferredLocale()),
                'source_url' => $payload['source_url'] ?? null,
                'source_route' => $payload['source_route'] ?? null,
                'status' => SupportRequest::STATUS_NEW,
                'meta' => $payload['meta'] ?? null,
            ]);

            $supportRequest->load('user');

            Mail::to($this->internalSupportRecipient())
                ->send(new SupportRequestSubmittedMail($supportRequest));

            return $supportRequest;
        });
    }

    protected function internalSupportRecipient(): User
    {
        $recipient = User::query()->find(self::INTERNAL_SUPPORT_ADMIN_ID);

        if ($recipient === null) {
            throw new RuntimeException('Internal support recipient user #1 is missing.');
        }

        return $recipient;
    }
}
