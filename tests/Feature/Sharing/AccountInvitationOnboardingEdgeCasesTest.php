<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\InvitationStatusEnum;
use App\Models\AccountInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('returns invalid for onboarding with wrong token', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'correct-token-correct-token-12345';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'newuser@gmail.com',
        'role' => AccountMembershipRoleEnum::EDITOR,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->getJson(route('account-invitations.onboarding.show', $invitation).'?token=wrong-token-wrong-token-12345')
        ->assertOk()
        ->assertJsonPath('data.state', 'invalid');
});

it('returns expired for onboarding when invitation is expired', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'expired-token-expired-token-12345';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'newuser@gmail.com',
        'role' => AccountMembershipRoleEnum::EDITOR,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->subMinute(),
    ]);

    $this->getJson(route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken)
        ->assertOk()
        ->assertJsonPath('data.state', 'expired');
});

it('returns already_processed for onboarding when invitation is no longer pending', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'accepted-token-accepted-token-1234';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'newuser@gmail.com',
        'role' => AccountMembershipRoleEnum::EDITOR,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::ACCEPTED,
        'expires_at' => now()->addDays(7),
        'accepted_at' => now(),
    ]);

    $this->getJson(route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken)
        ->assertOk()
        ->assertJsonPath('data.state', 'already_processed');
});

it('returns email_mismatch for onboarding when authenticated user has different email', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $wrongUser = User::factory()->create(['email' => 'other@gmail.com']);
    User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'email-mismatch-token-1234567890';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'wife@gmail.com',
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($wrongUser)
        ->getJson(route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken)
        ->assertOk()
        ->assertJsonPath('data.state', 'email_mismatch');
});
it('returns 422 when registering from invitation but user already exists', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    User::factory()->create(['email' => 'existing@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'existing-user-register-token-1234';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'existing@gmail.com',
        'role' => AccountMembershipRoleEnum::EDITOR,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->postJson(route('account-invitations.register', $invitation), [
        'token' => $plainToken,
        'first_name' => 'Existing',
        'last_name' => 'User',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This invitation cannot be used for registration.');
});
it('returns 422 when authenticated user tries to accept invitation with mismatched email', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $wrongUser = User::factory()->create(['email' => 'other@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'authenticated-mismatch-token-123';

    $invitation = AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'household_id' => $account->household_id,
        'email' => 'wife@gmail.com',
        'role' => AccountMembershipRoleEnum::VIEWER,
        'permissions' => null,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => InvitationStatusEnum::PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($wrongUser)
        ->postJson(route('account-invitations.accept-authenticated', $invitation), [
            'token' => $plainToken,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invitation email does not match authenticated user.');
});
