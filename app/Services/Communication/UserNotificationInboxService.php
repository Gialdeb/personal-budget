<?php

namespace App\Services\Communication;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotificationCollection;

class UserNotificationInboxService
{
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function latest(User $user, int $limit = 10): DatabaseNotificationCollection
    {
        return $user->notifications()
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function paginate(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->notifications()
            ->latest()
            ->paginate($perPage);
    }

    public function markAsRead(User $user, string $notificationId): bool
    {
        $notification = $user->unreadNotifications()
            ->where('id', $notificationId)
            ->first();

        if (! $notification) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    public function markAllAsRead(User $user): int
    {
        $notifications = $user->unreadNotifications()->get();
        $count = $notifications->count();

        $notifications->markAsRead();

        return $count;
    }
}
