<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin.automation.runs', function (User $user): bool {
    return $user->isAdmin();
});

Broadcast::channel('users.{uuid}.notifications', function (User $user, string $uuid): bool {
    return $user->uuid === $uuid;
});

Broadcast::channel('users.{uuid}.session', function (User $user, string $uuid): bool {
    return $user->uuid === $uuid;
});

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return (int) $user->id === (int) $id;
});
