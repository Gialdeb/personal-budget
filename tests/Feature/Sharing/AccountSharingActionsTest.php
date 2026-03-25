<?php

use App\Actions\Sharing\AcceptAccountInvitationAction;
use App\Actions\Sharing\InviteUserToAccountAction;
use App\Actions\Sharing\LeaveAccountAction;
use App\Actions\Sharing\RestoreAccountMembershipAction;
use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\InvitationStatusEnum;
use App\Enums\MembershipSourceEnum;
use App\Exceptions\CannotInviteToAccountException;
use App\Exceptions\CannotLeaveAccountMembershipException;
use App\Exceptions\CannotRestoreAccountMembershipException;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('invites and accepts account sharing through actions', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wife@gmail.com']);

    $account = createTestAccount($owner, [
        'name' => 'Banca Intesa Shared',
    ]);

    $ownerMembership = AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'household_id' => $account->household_id,
        'role' => AccountMembershipRoleEnum::OWNER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::MIGRATION,
        'joined_at' => now(),
    ]);

    $inviteAction = app(InviteUserToAccountAction::class);

    $result = $inviteAction->execute(
        account: $account,
        inviter: $owner,
        email: $invitee->email,
        role: AccountMembershipRoleEnum::EDITOR->value,
        permissions: null,
        expiresAt: now()->addDays(7),
    );

    expect($result['invitation'])->toBeInstanceOf(AccountInvitation::class)
        ->and($result['invitation']->status)->toBe(InvitationStatusEnum::PENDING)
        ->and($result['plain_token'])->not->toBeEmpty();

    $acceptAction = app(AcceptAccountInvitationAction::class);

    $membership = $acceptAction->execute(
        invitation: $result['invitation']->fresh(),
        user: $invitee,
        plainToken: $result['plain_token'],
    );

    expect($membership->status)->toBe(AccountMembershipStatusEnum::ACTIVE)
        ->and($membership->role)->toBe(AccountMembershipRoleEnum::EDITOR)
        ->and($result['invitation']->fresh()->status)->toBe(InvitationStatusEnum::ACCEPTED);
});

it('blocks duplicate pending invitation through action', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $account = createTestAccount($owner);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'household_id' => $account->household_id,
        'role' => AccountMembershipRoleEnum::OWNER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::MIGRATION,
        'joined_at' => now(),
    ]);

    $action = app(InviteUserToAccountAction::class);

    $action->execute(
        account: $account,
        inviter: $owner,
        email: 'wife@gmail.com',
        role: AccountMembershipRoleEnum::VIEWER->value,
    );

    expect(fn () => $action->execute(
        account: $account,
        inviter: $owner,
        email: 'wife@gmail.com',
        role: AccountMembershipRoleEnum::VIEWER->value,
    ))->toThrow(CannotInviteToAccountException::class);
});

it('allows invited user to leave and owner to restore through actions', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wife@gmail.com']);

    $account = createTestAccount($owner, [
        'name' => 'Family Account',
    ]);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'household_id' => $account->household_id,
        'role' => AccountMembershipRoleEnum::OWNER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::MIGRATION,
        'joined_at' => now(),
    ]);

    $membership = AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $invitee->id,
        'household_id' => $account->household_id,
        'role' => AccountMembershipRoleEnum::EDITOR,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::INVITATION,
        'joined_at' => now(),
    ]);

    $leaveAction = app(LeaveAccountAction::class);

    $leftMembership = $leaveAction->execute(
        membership: $membership,
        actor: $invitee,
        reason: 'relationship ended',
    );

    expect($leftMembership->status)->toBe(AccountMembershipStatusEnum::LEFT);

    $restoreAction = app(RestoreAccountMembershipAction::class);

    $restoredMembership = $restoreAction->execute(
        membership: $membership->fresh(),
        actor: $owner,
    );

    expect($restoredMembership->status)->toBe(AccountMembershipStatusEnum::ACTIVE)
        ->and($restoredMembership->restored_by_user_id)->toBe($owner->id);
});

it('blocks the last owner from leaving through action', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);

    $account = createTestAccount($owner);

    $membership = AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'household_id' => $account->household_id,
        'role' => AccountMembershipRoleEnum::OWNER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::MIGRATION,
        'joined_at' => now(),
    ]);

    $leaveAction = app(LeaveAccountAction::class);

    expect(fn () => $leaveAction->execute(
        membership: $membership,
        actor: $owner,
        reason: 'trying to leave',
    ))->toThrow(CannotLeaveAccountMembershipException::class);
});

it('blocks restore of an already active membership through action', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $member = User::factory()->create(['email' => 'wife@gmail.com']);

    $account = createTestAccount($owner);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'household_id' => $account->household_id,
        'role' => AccountMembershipRoleEnum::OWNER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::MIGRATION,
        'joined_at' => now(),
    ]);

    $membership = AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $member->id,
        'household_id' => $account->household_id,
        'role' => AccountMembershipRoleEnum::EDITOR,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::INVITATION,
        'joined_at' => now(),
    ]);

    $restoreAction = app(RestoreAccountMembershipAction::class);

    expect(fn () => $restoreAction->execute(
        membership: $membership,
        actor: $owner,
    ))->toThrow(CannotRestoreAccountMembershipException::class);
});
