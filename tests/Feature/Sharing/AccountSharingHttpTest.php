<?php

use App\Actions\Sharing\InviteUserToAccountAction;
use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\InvitationStatusEnum;
use App\Enums\MembershipSourceEnum;
use App\Http\Controllers\Sharing\AccountSharingController;
use App\Http\Requests\Sharing\InviteUserToAccountRequest;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\User;
use Database\Seeders\CommunicationCategorySeeder;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\NotificationTopicSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(NotificationTopicSeeder::class);
    $this->seed(CommunicationTemplateSeeder::class);
    $this->seed(CommunicationCategorySeeder::class);
});

it('allows original owner to submit an invitation through the sharing controller', function () {
    App::setLocale('it');

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

    $this->actingAs($owner);

    $request = InviteUserToAccountRequest::create(
        route('sharing.accounts.invitations.store', $account),
        'POST',
        [
            'email' => 'taylor@laravel.com',
            'role' => AccountMembershipRoleEnum::EDITOR->value,
        ],
    );
    $request->setUserResolver(fn () => $owner);

    $response = app(AccountSharingController::class)->invite(
        $request,
        $account,
        app(InviteUserToAccountAction::class),
    );

    expect($response->getStatusCode())->toBe(201);

    $payload = $response->getData(true);

    expect($payload['message'])->toBe(__('accounts.sharing.invite_created'))
        ->and($payload['data']['email'])->toBe('taylor@laravel.com')
        ->and($payload['data']['status'])->toBe(InvitationStatusEnum::PENDING->value)
        ->and($payload['data']['role_label'])->toBe(__('enums.account_membership_role.editor'));
});

it('prevents non owner from submitting an invitation through the sharing controller', function () {
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

    $this->actingAs($other);

    $request = InviteUserToAccountRequest::create(
        route('sharing.accounts.invitations.store', $account),
        'POST',
        [
            'email' => 'taylor@laravel.com',
            'role' => AccountMembershipRoleEnum::EDITOR->value,
        ],
    );
    $request->setUserResolver(fn () => $other);

    expect(fn () => app(AccountSharingController::class)->invite(
        $request,
        $account,
        app(InviteUserToAccountAction::class),
    ))->toThrow(AuthorizationException::class);
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

it('allows original owner to change a member access level from viewer to editor through http endpoint', function () {
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
        ->patchJson(route('sharing.account-memberships.update-role', $membership), [
            'role' => AccountMembershipRoleEnum::EDITOR->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.role', AccountMembershipRoleEnum::EDITOR->value)
        ->assertJsonPath('data.status', AccountMembershipStatusEnum::ACTIVE->value);

    expect($membership->fresh()->role)->toBe(AccountMembershipRoleEnum::EDITOR);
});

it('allows original owner to change a member access level from editor to viewer through http endpoint', function () {
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
        ->patchJson(route('sharing.account-memberships.update-role', $membership), [
            'role' => AccountMembershipRoleEnum::VIEWER->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.role', AccountMembershipRoleEnum::VIEWER->value)
        ->assertJsonPath('data.status', AccountMembershipStatusEnum::ACTIVE->value);

    expect($membership->fresh()->role)->toBe(AccountMembershipRoleEnum::VIEWER);
});

it('prevents non owners from changing a member access level through http endpoint', function () {
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
        ->patchJson(route('sharing.account-memberships.update-role', $membership), [
            'role' => AccountMembershipRoleEnum::EDITOR->value,
        ])
        ->assertForbidden();

    expect($membership->fresh()->role)->toBe(AccountMembershipRoleEnum::VIEWER);
});
