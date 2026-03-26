<?php

namespace App\Listeners;

use App\Models\CommunicationCategory;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Services\Communication\CommunicationDispatchService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;

class DispatchWelcomeAfterEmailVerified
{
    public function __construct(
        protected CommunicationDispatchService $dispatchService,
    ) {}

    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        if ($user->suppressWelcomeAfterVerification) {
            return;
        }

        $categoryExists = CommunicationCategory::query()
            ->where('key', 'user.welcome_after_verification')
            ->where('is_active', true)
            ->exists();

        if (! $categoryExists) {
            return;
        }

        if ($this->alreadyDispatchedForVerification($user)) {
            return;
        }

        $this->dispatchService->dispatchForUserCategory(
            'user.welcome_after_verification',
            $user,
            $user,
        );
    }

    protected function alreadyDispatchedForVerification(User $user): bool
    {
        if (! $user->email_verified_at) {
            return false;
        }

        $verifiedAt = $user->email_verified_at instanceof Carbon
            ? $user->email_verified_at
            : Carbon::parse($user->email_verified_at);

        return OutboundMessage::query()
            ->whereHas('category', fn ($query) => $query->where('key', 'user.welcome_after_verification'))
            ->where('recipient_type', $user->getMorphClass())
            ->where('recipient_id', $user->getKey())
            ->where('context_type', $user->getMorphClass())
            ->where('context_id', $user->getKey())
            ->where('created_at', '>=', $verifiedAt->copy()->subMinute())
            ->exists();
    }
}
