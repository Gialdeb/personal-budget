<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\HouseholdMembershipStatusEnum;
use App\Enums\HouseholdRoleEnum;
use App\Enums\HouseholdStatusEnum;
use App\Enums\InvitationStatusEnum;
use App\Enums\MembershipSourceEnum;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('casts household enums correctly', function () {
    $user = User::factory()->create();

    $household = Household::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Family',
        'slug' => 'family',
        'owner_user_id' => $user->id,
        'status' => HouseholdStatusEnum::ACTIVE,
    ]);

    expect($household->status)->toBeInstanceOf(HouseholdStatusEnum::class)
        ->and($household->status)->toBe(HouseholdStatusEnum::ACTIVE);
});

it('creates unique household membership per user and household', function () {
    $user = User::factory()->create();
    $household = Household::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Family',
        'slug' => 'family',
        'owner_user_id' => $user->id,
        'status' => HouseholdStatusEnum::ACTIVE,
    ]);

    HouseholdMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'household_id' => $household->id,
        'user_id' => $user->id,
        'role' => HouseholdRoleEnum::OWNER,
        'status' => HouseholdMembershipStatusEnum::ACTIVE,
        'joined_at' => now(),
    ]);

    expect(HouseholdMembership::query()->count())->toBe(1);
});

it('casts account membership enums correctly', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);

    $membership = AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $user->id,
        'role' => AccountMembershipRoleEnum::OWNER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'granted_by_user_id' => $user->id,
        'source' => MembershipSourceEnum::DIRECT,
        'joined_at' => now(),
    ]);

    expect($membership->role)->toBe(AccountMembershipRoleEnum::OWNER)
        ->and($membership->status)->toBe(AccountMembershipStatusEnum::ACTIVE)
        ->and($membership->source)->toBe(MembershipSourceEnum::DIRECT);
});

it('relates household to memberships and invitations', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();

    $household = Household::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Family',
        'slug' => 'family',
        'owner_user_id' => $owner->id,
        'status' => HouseholdStatusEnum::ACTIVE,
    ]);

    HouseholdMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'household_id' => $household->id,
        'user_id' => $owner->id,
        'role' => HouseholdRoleEnum::OWNER,
        'status' => HouseholdMembershipStatusEnum::ACTIVE,
        'joined_at' => now(),
    ]);

    HouseholdInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'household_id' => $household->id,
        'email' => $invitee->email,
        'role' => HouseholdRoleEnum::MEMBER,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', 'token'),
        'status' => InvitationStatusEnum::PENDING,
    ]);

    expect($household->memberships)->toHaveCount(1)
        ->and($household->invitations)->toHaveCount(1);
});

it('relates account to memberships and invitations', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $account = createTestAccount($owner);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'role' => AccountMembershipRoleEnum::OWNER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::DIRECT,
        'joined_at' => now(),
    ]);

    AccountInvitation::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $account->id,
        'email' => $invitee->email,
        'role' => AccountMembershipRoleEnum::VIEWER,
        'invited_by_user_id' => $owner->id,
        'token_hash' => hash('sha256', 'token'),
        'status' => InvitationStatusEnum::PENDING,
    ]);

    expect($account->memberships)->toHaveCount(1)
        ->and($account->invitations)->toHaveCount(1);
});
