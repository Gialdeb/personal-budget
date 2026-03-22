<?php

namespace App\Services\Admin;

use App\Enums\UserStatusEnum;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Validation\ValidationException;

class AdminUserActionService
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function banUser(User $admin, User $target, ?string $reason = null): User
    {
        $this->guardAdminTarget($target);

        $target->forceFill([
            'status' => UserStatusEnum::BANNED->value,
            'status_reason' => $reason,
            'status_changed_at' => now(),
        ])->save();

        $this->auditLogService->userBanned($admin, $target, $reason);

        return $target->fresh();
    }

    public function suspendUser(User $admin, User $target, ?string $reason = null): User
    {
        $this->guardAdminTarget($target);

        $target->forceFill([
            'status' => UserStatusEnum::SUSPENDED->value,
            'status_reason' => $reason,
            'status_changed_at' => now(),
        ])->save();

        $this->auditLogService->userSuspended($admin, $target, $reason);

        return $target->fresh();
    }

    public function reactivateUser(User $admin, User $target): User
    {
        $this->guardAdminTarget($target);

        $target->forceFill([
            'status' => UserStatusEnum::ACTIVE->value,
            'status_reason' => null,
            'status_changed_at' => now(),
        ])->save();

        $this->auditLogService->userReactivated($admin, $target);

        return $target->fresh();
    }

    public function syncRoles(User $admin, User $target, array $roles): User
    {
        $this->guardAdminTarget($target);

        $allowedRoles = ['user', 'staff'];

        $roles = collect($roles)
            ->filter(fn ($role) => in_array($role, $allowedRoles, true))
            ->unique()
            ->values()
            ->all();

        if ($roles === []) {
            throw ValidationException::withMessages([
                'roles' => __('admin.users.validation.roles_required'),
            ]);
        }

        $oldRoles = $target->getRoleNames()->values()->all();

        $target->syncRoles($roles);

        $this->auditLogService->rolesSynced(
            $admin,
            $target->fresh('roles'),
            $oldRoles,
            $target->getRoleNames()->values()->all(),
        );

        return $target->fresh('roles');
    }

    protected function guardAdminTarget(User $target): void
    {
        if ($target->hasRole('admin')) {
            throw ValidationException::withMessages([
                'user' => __('admin.users.validation.admin_target_forbidden'),
            ]);
        }
    }
}
