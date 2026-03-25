<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\InvitationStatusEnum;
use App\Enums\MembershipSourceEnum;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('allows original owner to invite through http endpoint', function () {
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

    $this->actingAs($owner)
        ->postJson(route('sharing.accounts.invitations.store', $account), [
            'email' => 'wife@gmail.com',
            'role' => AccountMembershipRoleEnum::EDITOR->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'wife@gmail.com')
        ->assertJsonPath('data.status', InvitationStatusEnum::PENDING->value);
});

it('prevents non owner from inviting through http endpoint', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $other = User::factory()->create(['email' => 'other@gmail.com']);
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

    $this->actingAs($other)
        ->postJson(route('sharing.accounts.invitations.store', $account), [
            'email' => 'wife@gmail.com',
            'role' => AccountMembershipRoleEnum::EDITOR->value,
        ])
        ->assertForbidden();
});

it('accepts invitation through http endpoint when email matches', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wife@gmail.com']);
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

    $plainToken = Str::random(64);

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => $invitee->email,
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($invitee)
        ->postJson(route('sharing.account-invitations.accept', $invitation), [
            'token' => $plainToken,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', AccountMembershipStatusEnum::ACTIVE->value)
        ->assertJsonPath('data.user.email', $invitee->email);
});

it('allows member to leave through http endpoint', function () {
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

    $this->actingAs($member)
        ->postJson(route('sharing.account-memberships.leave', $membership), [
            'reason' => 'voluntary',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', AccountMembershipStatusEnum::LEFT->value);
});

it('allows original owner to revoke and restore through http endpoints', function () {
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
        'role' => AccountMembershipRoleEnum::VIEWER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::INVITATION,
        'joined_at' => now(),
    ]);

    $this->actingAs($owner)
        ->postJson(route('sharing.account-memberships.revoke', $membership), [
            'reason' => 'revoked',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', AccountMembershipStatusEnum::REVOKED->value);

    $this->actingAs($owner)
        ->postJson(route('sharing.account-memberships.restore', $membership->fresh()))
        ->assertOk()
        ->assertJsonPath('data.status', AccountMembershipStatusEnum::ACTIVE->value);
});
