<?php

namespace App\Services\Communication;

use App\Enums\NotificationAudienceEnum;
use App\Models\NotificationTopic;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class NotificationRecipientResolver
{
    /**
     * @param  User|iterable<User>|null  $target
     * @return Collection<int, User>
     */
    public function resolveRecipients(NotificationTopic $topic, User|iterable|null $target = null): Collection
    {
        return match ($topic->audience) {
            NotificationAudienceEnum::USER => $this->resolveUserRecipients($target),
            NotificationAudienceEnum::ADMIN => $this->resolveAdminRecipients(),
            NotificationAudienceEnum::BOTH => $this->resolveBothRecipients($target),
        };
    }

    /**
     * @param  User|iterable<User>|null  $target
     * @return Collection<int, User>
     */
    protected function resolveUserRecipients(User|iterable|null $target = null): Collection
    {
        if ($target instanceof User) {
            return collect([$target]);
        }

        if (is_iterable($target)) {
            return collect($target)->filter(fn ($user) => $user instanceof User)->values();
        }

        return collect();
    }

    /**
     * @return Collection<int, User>
     */
    protected function resolveAdminRecipients(): Collection
    {
        $sampleUser = new User;

        if (method_exists($sampleUser, 'hasRole')) {
            return User::role('admin')->get();
        }

        if (Schema::hasColumn('users', 'is_admin')) {
            return User::query()
                ->where('is_admin', true)
                ->get();
        }

        return collect();
    }

    /**
     * @return Collection<int, User>
     */
    protected function resolveBothRecipients(User|iterable|null $target = null): Collection
    {
        return $this->resolveUserRecipients($target)
            ->merge($this->resolveAdminRecipients())
            ->unique('id')
            ->values();
    }
}
