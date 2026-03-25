<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\InvitationStatusEnum;
use App\Models\AccountInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('returns registration_required for a valid invitation when no user exists for the email', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'registration-required-token-12345';

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

    $this->getJson(route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken)
        ->assertOk()
        ->assertJsonPath('data.state', 'registration_required')
        ->assertJsonPath('data.email', 'newuser@gmail.com')
        ->assertJsonPath('data.requires_registration', true)
        ->assertJsonPath('data.requires_login', false)
        ->assertJsonPath('data.can_accept', false);
});

it('returns login_required for a valid invitation when user exists but is not authenticated', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'login-required-token-login-12345';

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

    $this->getJson(route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken)
        ->assertOk()
        ->assertJsonPath('data.state', 'login_required')
        ->assertJsonPath('data.requires_registration', false)
        ->assertJsonPath('data.requires_login', true)
        ->assertJsonPath('data.can_accept', false);
});

it('returns ready_to_accept for a valid invitation when authenticated user email matches', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'ready-to-accept-token-123456789';

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

    $this->actingAs($invitee)
        ->getJson(route('account-invitations.onboarding.show', $invitation).'?token='.$plainToken)
        ->assertOk()
        ->assertJsonPath('data.state', 'ready_to_accept')
        ->assertJsonPath('data.requires_registration', false)
        ->assertJsonPath('data.requires_login', false)
        ->assertJsonPath('data.can_accept', true);
});
it('registers a new user from a valid account invitation', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'register-from-invitation-token-123';

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

    $this->postJson(route('account-invitations.register', $invitation), [
        'token' => $plainToken,
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'newuser@gmail.com')
        ->assertJsonPath('data.membership.status', 'active')
        ->assertJsonPath('data.membership.role', 'editor');

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'newuser@gmail.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();

    expect($invitation->fresh()->status)->toBe(InvitationStatusEnum::ACCEPTED);
});
it('accepts a valid account invitation for an authenticated existing user', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner);

    $plainToken = 'accept-authenticated-token-12345';

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

    $this->actingAs($invitee)
        ->postJson(route('account-invitations.accept-authenticated', $invitation), [
            'token' => $plainToken,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.role', 'viewer')
        ->assertJsonPath('data.user.email', 'wife@gmail.com');

    expect($invitation->fresh()->status)->toBe(InvitationStatusEnum::ACCEPTED);
});
