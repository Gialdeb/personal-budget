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

it('returns 422 when owner tries to invite themselves through http endpoint', function () {
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
            'email' => 'owner@gmail.com',
            'role' => AccountMembershipRoleEnum::EDITOR->value,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'You cannot invite yourself to the same account.');
});

it('returns 422 for duplicate pending invitation through http endpoint', function () {
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
        ->assertCreated();

    $this->actingAs($owner)
        ->postJson(route('sharing.accounts.invitations.store', $account), [
            'email' => 'wife@gmail.com',
            'role' => AccountMembershipRoleEnum::EDITOR->value,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'There is already a pending invitation for this email on this account.');
});

it('returns 422 when accepting invitation with invalid token', function () {
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

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => $invitee->email,
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', 'correct-token'),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($invitee)
        ->postJson(route('sharing.account-invitations.accept', $invitation), [
            'token' => 'wrong-token-wrong-token-wrong-123',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invitation token is invalid.');
});

it('returns 422 when accepting an expired invitation', function () {
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

    $plainToken = 'expired-token-expired-token-12345';

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
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($invitee)
        ->postJson(route('sharing.account-invitations.accept', $invitation), [
            'token' => $plainToken,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invitation has expired.');
});

it('returns 403 when another user tries to accept invitation with different email', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wife@gmail.com']);
    $wrongUser = User::factory()->create(['email' => 'other@gmail.com']);
    $account = createTestAccount($owner);

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => $invitee->email,
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', 'valid-token-valid-token-valid-123'),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($wrongUser)
        ->postJson(route('sharing.account-invitations.accept', $invitation), [
            'token' => 'valid-token-valid-token-valid-123',
        ])
        ->assertForbidden();
});

it('returns 422 when the last owner tries to leave through http endpoint', function () {
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

    $this->actingAs($owner)
        ->postJson(route('sharing.account-memberships.leave', $membership), [
            'reason' => 'trying to leave',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The last active owner cannot leave the account.');
});
it('returns 422 when trying to revoke the last active owner through http endpoint', function () {
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

    $this->actingAs($owner)
        ->postJson(route('sharing.account-memberships.revoke', $membership), [
            'reason' => 'trying revoke',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The last active owner cannot be revoked from the account.');
});

it('returns 422 when inviting a user who already has active membership', function () {
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

    AccountMembership::query()->create([
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

    $this->actingAs($owner)
        ->postJson(route('sharing.accounts.invitations.store', $account), [
            'email' => $member->email,
            'role' => AccountMembershipRoleEnum::EDITOR->value,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The invited user already has access to this account.');
});

it('returns 422 when accepting an invitation that is not pending', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'already-accepted-token-accepted-123';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => $invitee->email,
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::ACCEPTED,
        'expires_at' => now()->addDays(7),
        'accepted_by_user_id' => $invitee->id,
        'accepted_at' => now(),
    ]);

    $this->actingAs($invitee)
        ->postJson(route('sharing.account-invitations.accept', $invitation), [
            'token' => $plainToken,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invitation is not pending.');
});

it('returns 403 when user tries to leave another users membership', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $member = User::factory()->create(['email' => 'wife@gmail.com']);
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

    $this->actingAs($other)
        ->postJson(route('sharing.account-memberships.leave', $membership), [
            'reason' => 'not my membership',
        ])
        ->assertForbidden();
});

it('returns 403 when non original owner tries to revoke a membership', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $member = User::factory()->create(['email' => 'wife@gmail.com']);
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

    $this->actingAs($other)
        ->postJson(route('sharing.account-memberships.revoke', $membership), [
            'reason' => 'not allowed',
        ])
        ->assertForbidden();
});

it('returns 403 when non original owner tries to restore a membership', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $member = User::factory()->create(['email' => 'wife@gmail.com']);
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

    $membership = AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $member->id,
        'household_id' => $account->household_id,
        'role' => AccountMembershipRoleEnum::EDITOR,
        'status' => AccountMembershipStatusEnum::LEFT,
        'permissions' => null,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::INVITATION,
        'joined_at' => now()->subDay(),
        'left_at' => now(),
        'left_reason' => 'voluntary',
    ]);

    $this->actingAs($other)
        ->postJson(route('sharing.account-memberships.restore', $membership))
        ->assertForbidden();
});

it('returns 422 when trying to restore an already active membership', function () {
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

    $this->actingAs($owner)
        ->postJson(route('sharing.account-memberships.restore', $membership))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only left or revoked memberships can be restored.');
});
