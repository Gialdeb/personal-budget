<?php

namespace App\Services\Audit;

use App\Models\User;

class AuditLogService
{
    public function roleAssigned(?User $causer, User $target, string $role): void
    {
        activity('users')
            ->createdAt(now()->addSecond())
            ->causedBy($causer)
            ->performedOn($target)
            ->withProperties([
                'target_user_id' => $target->id,
                'target_user_email' => $target->email,
                'role' => $role,
            ])
            ->log('user.role_assigned');
    }

    public function roleRemoved(?User $causer, User $target, string $role): void
    {
        activity('users')
            ->createdAt(now()->addSecond())
            ->causedBy($causer)
            ->performedOn($target)
            ->withProperties([
                'target_user_id' => $target->id,
                'target_user_email' => $target->email,
                'role' => $role,
            ])
            ->log('user.role_removed');
    }

    public function impersonationStarted(User $causer, User $target): void
    {
        activity('admin')
            ->createdAt(now()->addSecond())
            ->causedBy($causer)
            ->performedOn($target)
            ->withProperties([
                'target_user_id' => $target->id,
                'target_user_email' => $target->email,
            ])
            ->log('user.impersonation_started');
    }

    public function impersonationStopped(User $causer, User $target): void
    {
        activity('admin')
            ->createdAt(now()->addSecond())
            ->causedBy($causer)
            ->performedOn($target)
            ->withProperties([
                'target_user_id' => $target->id,
                'target_user_email' => $target->email,
            ])
            ->log('user.impersonation_stopped');
    }

    public function userBanned(User $causer, User $target, ?string $reason = null): void
    {
        activity('users')
            ->createdAt(now()->addSecond())
            ->causedBy($causer)
            ->performedOn($target)
            ->withProperties([
                'target_user_id' => $target->id,
                'target_user_email' => $target->email,
                'reason' => $reason,
                'status' => 'banned',
            ])
            ->log('user.banned');
    }

    public function userSuspended(User $causer, User $target, ?string $reason = null): void
    {
        activity('users')
            ->createdAt(now()->addSecond())
            ->causedBy($causer)
            ->performedOn($target)
            ->withProperties([
                'target_user_id' => $target->id,
                'target_user_email' => $target->email,
                'reason' => $reason,
                'status' => 'suspended',
            ])
            ->log('user.suspended');
    }

    public function userReactivated(User $causer, User $target): void
    {
        activity('users')
            ->createdAt(now()->addSecond())
            ->causedBy($causer)
            ->performedOn($target)
            ->withProperties([
                'target_user_id' => $target->id,
                'target_user_email' => $target->email,
                'status' => 'active',
            ])
            ->log('user.reactivated');
    }

    public function rolesSynced(User $causer, User $target, array $oldRoles, array $newRoles): void
    {
        activity('users')
            ->createdAt(now()->addSecond())
            ->causedBy($causer)
            ->performedOn($target)
            ->withProperties([
                'target_user_id' => $target->id,
                'target_user_email' => $target->email,
                'old_roles' => array_values($oldRoles),
                'new_roles' => array_values($newRoles),
            ])
            ->log('user.roles_synced');
    }
}
