<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\HouseholdMembershipStatusEnum;
use App\Enums\HouseholdRoleEnum;
use App\Enums\MembershipSourceEnum;
use App\Models\AccountMembership;
use App\Models\Household;
use App\Models\HouseholdMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates personal households and owner memberships for existing accounts', function () {
    $user = User::factory()->create([
        'name' => 'Giuseppe',
    ]);
    $account = createTestAccount($user);

    $this->artisan('app:backfill-households-accounts')
        ->assertExitCode(0);

    $household = Household::query()
        ->where('owner_user_id', $user->id)
        ->first();

    expect($household)->not->toBeNull()
        ->and($household->name)->toContain('Giuseppe');

    $householdMembership = HouseholdMembership::query()
        ->where('household_id', $household->id)
        ->where('user_id', $user->id)
        ->first();

    expect($householdMembership)->not->toBeNull()
        ->and($householdMembership->role)->toBe(HouseholdRoleEnum::OWNER)
        ->and($householdMembership->status)->toBe(HouseholdMembershipStatusEnum::ACTIVE);

    $account->refresh();

    expect($account->household_id)->toBe($household->id);

    $accountMembership = AccountMembership::query()
        ->where('account_id', $account->id)
        ->where('user_id', $user->id)
        ->first();

    expect($accountMembership)->not->toBeNull()
        ->and($accountMembership->role)->toBe(AccountMembershipRoleEnum::OWNER)
        ->and($accountMembership->status)->toBe(AccountMembershipStatusEnum::ACTIVE)
        ->and($accountMembership->source)->toBe(MembershipSourceEnum::MIGRATION);
});

it('is idempotent when run multiple times', function () {
    $user = User::factory()->create();
    $account = createTestAccount($user);

    $this->artisan('app:backfill-households-accounts')->assertExitCode(0);
    $this->artisan('app:backfill-households-accounts')->assertExitCode(0);

    expect(Household::query()->where('owner_user_id', $user->id)->count())->toBe(1)
        ->and(AccountMembership::query()->where('account_id', $account->id)->where('user_id', $user->id)->count())->toBe(1)
        ->and(HouseholdMembership::query()->where('user_id', $user->id)->count())->toBe(1);
});
