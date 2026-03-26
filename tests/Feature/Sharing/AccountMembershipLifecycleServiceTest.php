<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\MembershipSourceEnum;
use App\Exceptions\CannotLeaveAccountMembershipException;
use App\Exceptions\CannotRestoreAccountMembershipException;
use App\Exceptions\CannotRevokeAccountMembershipException;
use App\Exceptions\CannotUpdateAccountMembershipRoleException;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\User;
use App\Services\Sharing\AccountMembershipLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createActiveMembership(Account $account, User $user, string $role, int $grantedByUserId): AccountMembership
{
    return AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $user->id,
        'household_id' => $account->household_id,
        'role' => $role,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $grantedByUserId,
        'source' => MembershipSourceEnum::DIRECT,
        'joined_at' => now(),
    ]);
}

it('allows a non-owner active member to leave their own membership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $account = createTestAccount($owner, ['name' => 'Lifecycle account']);

    createActiveMembership($account, $owner, AccountMembershipRoleEnum::OWNER->value, $owner->id);
    $membership = createActiveMembership($account, $member, AccountMembershipRoleEnum::EDITOR->value, $owner->id);

    $service = app(AccountMembershipLifecycleService::class);

    $updated = $service->leave($membership, $member, 'voluntary');

    expect($updated->status)->toBe(AccountMembershipStatusEnum::LEFT)
        ->and($updated->left_reason)->toBe('voluntary')
        ->and($updated->left_at)->not->toBeNull();
});

it('prevents the last active owner from leaving the account', function () {
    $owner = User::factory()->create();
    $account = createTestAccount($owner, ['name' => 'Lifecycle account']);

    $membership = createActiveMembership($account, $owner, AccountMembershipRoleEnum::OWNER->value, $owner->id);

    $service = app(AccountMembershipLifecycleService::class);

    expect(fn () => $service->leave($membership, $owner))
        ->toThrow(CannotLeaveAccountMembershipException::class);
});

it('allows original owner to revoke an active non-owner membership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $account = createTestAccount($owner, ['name' => 'Lifecycle account']);

    createActiveMembership($account, $owner, AccountMembershipRoleEnum::OWNER->value, $owner->id);
    $membership = createActiveMembership($account, $member, AccountMembershipRoleEnum::VIEWER->value, $owner->id);

    $service = app(AccountMembershipLifecycleService::class);

    $updated = $service->revoke($membership, $owner, 'access revoked');

    expect($updated->status)->toBe(AccountMembershipStatusEnum::REVOKED)
        ->and($updated->revoked_by_user_id)->toBe($owner->id)
        ->and($updated->revoked_at)->not->toBeNull();
});

it('prevents a non original owner from revoking a membership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();

    $account = createTestAccount($owner, ['name' => 'Lifecycle account']);

    createActiveMembership($account, $owner, AccountMembershipRoleEnum::OWNER->value, $owner->id);
    $membership = createActiveMembership($account, $member, AccountMembershipRoleEnum::VIEWER->value, $owner->id);

    $service = app(AccountMembershipLifecycleService::class);

    expect(fn () => $service->revoke($membership, $other))
        ->toThrow(CannotRevokeAccountMembershipException::class);
});

it('restores a revoked or left membership by the original owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $account = createTestAccount($owner, ['name' => 'Lifecycle account']);

    createActiveMembership($account, $owner, AccountMembershipRoleEnum::OWNER->value, $owner->id);

    $membership = createActiveMembership($account, $member, AccountMembershipRoleEnum::EDITOR->value, $owner->id);
    $membership->status = AccountMembershipStatusEnum::REVOKED;
    $membership->revoked_at = now();
    $membership->revoked_by_user_id = $owner->id;
    $membership->save();

    $service = app(AccountMembershipLifecycleService::class);

    $restored = $service->restore($membership->fresh(), $owner);

    expect($restored->status)->toBe(AccountMembershipStatusEnum::ACTIVE)
        ->and($restored->restored_by_user_id)->toBe($owner->id)
        ->and($restored->restored_at)->not->toBeNull()
        ->and($restored->revoked_at)->toBeNull();
});

it('prevents restoring an already active membership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $account = createTestAccount($owner, ['name' => 'Lifecycle account']);

    createActiveMembership($account, $owner, AccountMembershipRoleEnum::OWNER->value, $owner->id);
    $membership = createActiveMembership($account, $member, AccountMembershipRoleEnum::EDITOR->value, $owner->id);

    $service = app(AccountMembershipLifecycleService::class);

    expect(fn () => $service->restore($membership, $owner))
        ->toThrow(CannotRestoreAccountMembershipException::class);
});

it('allows original owner to change an active member from viewer to editor without replacing the membership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $account = createTestAccount($owner, ['name' => 'Lifecycle account']);

    createActiveMembership($account, $owner, AccountMembershipRoleEnum::OWNER->value, $owner->id);
    $membership = createActiveMembership($account, $member, AccountMembershipRoleEnum::VIEWER->value, $owner->id);

    $service = app(AccountMembershipLifecycleService::class);

    $updated = $service->updateRole($membership, $owner, AccountMembershipRoleEnum::EDITOR->value);

    expect($updated->id)->toBe($membership->id)
        ->and($updated->account_id)->toBe($membership->account_id)
        ->and($updated->user_id)->toBe($membership->user_id)
        ->and($updated->joined_at?->toISOString())->toBe($membership->joined_at?->toISOString())
        ->and($updated->role)->toBe(AccountMembershipRoleEnum::EDITOR)
        ->and($updated->status)->toBe(AccountMembershipStatusEnum::ACTIVE);
});

it('allows original owner to change an active member from editor to viewer without deleting any existing linkage', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $account = createTestAccount($owner, ['name' => 'Lifecycle account']);

    createActiveMembership($account, $owner, AccountMembershipRoleEnum::OWNER->value, $owner->id);
    $membership = createActiveMembership($account, $member, AccountMembershipRoleEnum::EDITOR->value, $owner->id);

    $service = app(AccountMembershipLifecycleService::class);

    $updated = $service->updateRole($membership, $owner, AccountMembershipRoleEnum::VIEWER->value);

    expect($updated->id)->toBe($membership->id)
        ->and($updated->account_id)->toBe($membership->account_id)
        ->and($updated->user_id)->toBe($membership->user_id)
        ->and($updated->role)->toBe(AccountMembershipRoleEnum::VIEWER)
        ->and($updated->status)->toBe(AccountMembershipStatusEnum::ACTIVE);
});

it('prevents non owners from changing the access level of a membership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();

    $account = createTestAccount($owner, ['name' => 'Lifecycle account']);

    createActiveMembership($account, $owner, AccountMembershipRoleEnum::OWNER->value, $owner->id);
    $membership = createActiveMembership($account, $member, AccountMembershipRoleEnum::VIEWER->value, $owner->id);

    $service = app(AccountMembershipLifecycleService::class);

    expect(fn () => $service->updateRole($membership, $other, AccountMembershipRoleEnum::EDITOR->value))
        ->toThrow(CannotUpdateAccountMembershipRoleException::class);
});
