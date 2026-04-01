<?php

namespace App\Services\Admin;

use App\Enums\SubscriptionStatusEnum;
use App\Enums\UserStatusEnum;
use App\Models\BillingSubscription;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AdminUserManagementService
{
    /**
     * @param array{
     *     search?: string,
     *     role?: string,
     *     status?: string,
     *     plan?: string
     * } $filters
     */
    public function paginateUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = trim((string) ($filters['role'] ?? 'all'));
        $status = trim((string) ($filters['status'] ?? 'all'));
        $plan = trim((string) ($filters['plan'] ?? 'all'));

        return User::query()
            ->with([
                'roles:id,name',
                'billingSubscription.billingPlan:id,code,name',
            ])
            ->withCount('billingTransactions')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $innerQuery) use ($search): void {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== '' && $role !== 'all', function (Builder $query) use ($role): void {
                $query->role($role);
            })
            ->when($status !== '' && $status !== 'all', function (Builder $query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($plan !== '' && $plan !== 'all', function (Builder $query) use ($plan): void {
                $query->where('plan_code', $plan);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (User $user): array => $this->mapUser($user))
            ->withQueryString();
    }

    /**
     * @return array{
     *     search: string,
     *     role: string,
     *     status: string,
     *     plan: string
     * }
     */
    public function normalizeFilters(array $filters = []): array
    {
        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'role' => trim((string) ($filters['role'] ?? 'all')) ?: 'all',
            'status' => trim((string) ($filters['status'] ?? 'all')) ?: 'all',
            'plan' => trim((string) ($filters['plan'] ?? 'all')) ?: 'all',
        ];
    }

    public function filterOptions(): array
    {
        return [
            'roles' => [
                ['value' => 'all', 'label' => __('admin.users.filters.roles.all')],
                ['value' => 'admin', 'label' => __('admin.users.filters.roles.admin')],
                ['value' => 'staff', 'label' => __('admin.users.filters.roles.staff')],
                ['value' => 'user', 'label' => __('admin.users.filters.roles.user')],
            ],
            'statuses' => [
                ['value' => 'all', 'label' => __('admin.users.filters.statuses.all')],
                ['value' => UserStatusEnum::ACTIVE->value, 'label' => UserStatusEnum::ACTIVE->label()],
                ['value' => UserStatusEnum::SUSPENDED->value, 'label' => UserStatusEnum::SUSPENDED->label()],
                ['value' => UserStatusEnum::BANNED->value, 'label' => UserStatusEnum::BANNED->label()],
            ],
            'plans' => [
                ['value' => 'all', 'label' => __('admin.users.filters.plans.all')],
                ['value' => 'free', 'label' => __('admin.users.filters.plans.free')],
            ],
        ];
    }

    protected function mapUser(User $user): array
    {
        $roles = $user->roles->pluck('name')->values()->all();
        $isAdmin = in_array('admin', $roles, true);
        $supportSummary = $this->supportSummary($user);

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'surname' => $user->surname,
            'full_name' => trim(collect([$user->name, $user->surname])->filter()->implode(' ')),
            'email' => $user->email,
            'roles' => $roles,
            'primary_role' => $roles[0] ?? null,
            'status' => $user->status,
            'status_label' => UserStatusEnum::from($user->status)->label(),
            'plan_code' => $user->plan_code,
            'subscription_status' => $user->subscription_status,
            'subscription_status_label' => SubscriptionStatusEnum::from($user->subscription_status)->label(),
            'support_state' => $supportSummary['state'],
            'support_state_label' => $supportSummary['label'],
            'support_plan_code' => $supportSummary['plan_code'],
            'last_contribution_at' => $supportSummary['last_contribution_at'],
            'support_window_ends_at' => $supportSummary['support_window_ends_at'],
            'next_support_reminder_at' => $supportSummary['next_support_reminder_at'],
            'donations_count' => $supportSummary['donations_count'],
            'is_impersonable' => (bool) $user->is_impersonable,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'can_impersonate' => ! $isAdmin && (bool) $user->is_impersonable,
            'can_ban' => ! $isAdmin,
            'can_suspend' => ! $isAdmin,
            'can_reactivate' => ! $isAdmin && $user->status !== UserStatusEnum::ACTIVE->value,
            'can_manage_roles' => ! $isAdmin,
            'can_delete' => ! $isAdmin,
        ];
    }

    /**
     * @return array{
     *     state: string,
     *     label: string,
     *     plan_code: ?string,
     *     last_contribution_at: ?string,
     *     support_window_ends_at: ?string,
     *     next_support_reminder_at: ?string,
     *     donations_count: int
     * }
     */
    protected function supportSummary(User $user): array
    {
        $subscription = $user->billingSubscription;
        $donationsCount = (int) $user->billing_transactions_count;

        if (! $subscription instanceof BillingSubscription || $donationsCount === 0) {
            return [
                'state' => 'never_donated',
                'label' => __('admin.users.support.states.never_donated'),
                'plan_code' => $subscription?->billingPlan?->code,
                'last_contribution_at' => null,
                'support_window_ends_at' => $subscription?->ends_at?->toIso8601String(),
                'next_support_reminder_at' => $subscription?->next_reminder_at?->toIso8601String(),
                'donations_count' => $donationsCount,
            ];
        }

        $state = match (true) {
            $subscription->reminderIsDue() => 'reminder_due',
            $subscription->hasActiveSupportWindow() => 'support_recent',
            default => 'support_lapsed',
        };

        return [
            'state' => $state,
            'label' => __("admin.users.support.states.{$state}"),
            'plan_code' => $subscription->billingPlan?->code,
            'last_contribution_at' => $subscription->last_paid_at?->toIso8601String(),
            'support_window_ends_at' => $subscription->ends_at?->toIso8601String(),
            'next_support_reminder_at' => $subscription->next_reminder_at?->toIso8601String(),
            'donations_count' => $donationsCount,
        ];
    }
}
