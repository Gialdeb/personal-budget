<?php

namespace App\Services\Accounts;

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccessibleAccountsQuery
{
    public function query(User|int $user, string $scope = 'all', ?string $accountUuid = null): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;
        $activeMemberships = AccountMembership::query()
            ->where('user_id', $userId)
            ->where('status', AccountMembershipStatusEnum::ACTIVE->value)
            ->select([
                'uuid',
                'account_id',
                'role',
                'status',
            ]);

        $query = Account::query()
            ->leftJoinSub($activeMemberships, 'active_account_membership', function ($join): void {
                $join->on('active_account_membership.account_id', '=', 'accounts.id');
            })
            ->where(function (Builder $accessQuery) use ($userId): void {
                $accessQuery
                    ->where('accounts.user_id', $userId)
                    ->orWhereNotNull('active_account_membership.account_id');
            })
            ->select('accounts.*')
            ->selectRaw(
                'case when accounts.user_id = ? then 1 else 0 end as is_owned',
                [$userId],
            )
            ->selectRaw(
                'case when accounts.user_id <> ? and active_account_membership.account_id is not null then 1 else 0 end as is_shared',
                [$userId],
            )
            ->selectRaw(
                'active_account_membership.uuid as membership_uuid',
            )
            ->selectRaw(
                'active_account_membership.role as membership_role',
            )
            ->selectRaw(
                'active_account_membership.status as membership_status',
            )
            ->selectRaw('1 as can_view')
            ->selectRaw(
                'case when accounts.user_id = ? or active_account_membership.role in (?, ?, ?) then 1 else 0 end as can_edit',
                [
                    $userId,
                    AccountMembershipRoleEnum::OWNER->value,
                    AccountMembershipRoleEnum::MANAGER->value,
                    AccountMembershipRoleEnum::EDITOR->value,
                ],
            );

        $this->applyScopeFilter($query, $userId, $scope);

        if ($accountUuid !== null) {
            $query->where('accounts.uuid', $accountUuid);
        }

        return $query;
    }

    public function editable(User|int $user, string $scope = 'all', ?string $accountUuid = null): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;
        $query = $this->query($user, $scope, $accountUuid);

        $this->applyEditableFilter($query, $userId);

        return $query;
    }

    /**
     * @return array<int, int>
     */
    public function ids(User|int $user, string $scope = 'all', ?string $accountUuid = null): array
    {
        return $this->query($user, $scope, $accountUuid)
            ->pluck('accounts.id')
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function editableIds(User|int $user, string $scope = 'all', ?string $accountUuid = null): array
    {
        return $this->editable($user, $scope, $accountUuid)
            ->pluck('accounts.id')
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function ownerIds(User|int $user, string $scope = 'all', ?string $accountUuid = null): array
    {
        return $this->query($user, $scope, $accountUuid)
            ->pluck('accounts.user_id')
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function editableOwnerIds(User|int $user, string $scope = 'all', ?string $accountUuid = null): array
    {
        return $this->editable($user, $scope, $accountUuid)
            ->pluck('accounts.user_id')
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Account>
     */
    public function get(User|int $user, string $scope = 'all', ?string $accountUuid = null): Collection
    {
        return $this->query($user, $scope, $accountUuid)
            ->with([
                'bank:id,uuid,name',
                'userBank.bank:id,uuid,name',
            ])
            ->orderByDesc(DB::raw('is_owned'))
            ->orderBy('accounts.name')
            ->get();
    }

    public function canViewAccountId(User|int $user, int $accountId): bool
    {
        return $this->query($user)
            ->where('accounts.id', $accountId)
            ->exists();
    }

    public function canEditAccountId(User|int $user, int $accountId): bool
    {
        return $this->editable($user)
            ->where('accounts.id', $accountId)
            ->exists();
    }

    public function findAccessibleAccount(User|int $user, int $accountId, bool $requireEdit = false): ?Account
    {
        $query = $requireEdit ? $this->editable($user) : $this->query($user);

        return $query
            ->where('accounts.id', $accountId)
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dashboardFilterOptions(User|int $user): array
    {
        return $this->get($user)
            ->map(function (Account $account): array {
                $bankName = $account->userBank?->name ?? $account->bank?->name;

                return [
                    'value' => $account->uuid,
                    'label' => $account->name,
                    'bank_name' => $bankName,
                    'is_owned' => (bool) $account->getAttribute('is_owned'),
                    'is_shared' => (bool) $account->getAttribute('is_shared'),
                    'membership_role' => $account->getAttribute('membership_role'),
                    'membership_status' => $account->getAttribute('membership_status'),
                    'can_view' => (bool) $account->getAttribute('can_view'),
                    'can_edit' => (bool) $account->getAttribute('can_edit'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function dashboardScopeOptions(): array
    {
        return [
            [
                'value' => 'all',
                'label' => __('dashboard.filters.account_access_scopes.all'),
            ],
            [
                'value' => 'owned',
                'label' => __('dashboard.filters.account_access_scopes.owned'),
            ],
            [
                'value' => 'shared',
                'label' => __('dashboard.filters.account_access_scopes.shared'),
            ],
        ];
    }

    protected function applyScopeFilter(Builder $query, int $userId, string $scope): void
    {
        match ($scope) {
            'owned' => $query->where('accounts.user_id', $userId),
            'shared' => $query
                ->where('accounts.user_id', '!=', $userId)
                ->whereNotNull('active_account_membership.account_id'),
            default => null,
        };
    }

    protected function applyEditableFilter(Builder $query, int $userId): void
    {
        $query->where(function (Builder $editableQuery) use ($userId): void {
            $editableQuery
                ->where('accounts.user_id', $userId)
                ->orWhereIn('active_account_membership.role', [
                    AccountMembershipRoleEnum::OWNER->value,
                    AccountMembershipRoleEnum::MANAGER->value,
                    AccountMembershipRoleEnum::EDITOR->value,
                ]);
        });
    }
}
