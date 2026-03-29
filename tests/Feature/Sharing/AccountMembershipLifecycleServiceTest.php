<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\BudgetTypeEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\MembershipSourceEnum;
use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Exceptions\CannotLeaveAccountMembershipException;
use App\Exceptions\CannotRestoreAccountMembershipException;
use App\Exceptions\CannotRevokeAccountMembershipException;
use App\Exceptions\CannotUpdateAccountMembershipRoleException;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\User;
use App\Services\Categories\CategoryFoundationService;
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

it('restores a shared membership without duplicating recurring entries already linked to the account', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $account = createTestAccount($owner, ['name' => 'Lifecycle account']);

    createActiveMembership($account, $owner, AccountMembershipRoleEnum::OWNER->value, $owner->id);

    $recurringEntry = RecurringEntry::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'account_id' => $account->id,
        'title' => 'Shared lifecycle recurring',
        'description' => 'Recurring lifecycle entry',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => 49.90,
        'currency' => 'EUR',
        'entry_type' => RecurringEntryTypeEnum::RECURRING->value,
        'status' => RecurringEntryStatusEnum::ACTIVE->value,
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY->value,
        'recurrence_interval' => 1,
        'recurrence_rule' => ['mode' => 'day_of_month', 'day' => 15],
        'start_date' => '2026-02-15',
        'end_mode' => RecurringEndModeEnum::NEVER->value,
        'next_occurrence_date' => '2026-02-15',
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
    ]);

    $membership = createActiveMembership($account, $member, AccountMembershipRoleEnum::EDITOR->value, $owner->id);
    $membership->status = AccountMembershipStatusEnum::REVOKED;
    $membership->revoked_at = now();
    $membership->revoked_by_user_id = $owner->id;
    $membership->save();

    $service = app(AccountMembershipLifecycleService::class);

    $restored = $service->restore($membership->fresh(), $owner);

    expect($restored->status)->toBe(AccountMembershipStatusEnum::ACTIVE)
        ->and(RecurringEntry::query()->where('account_id', $account->id)->count())->toBe(1)
        ->and($recurringEntry->fresh()->id)->toBe($recurringEntry->id)
        ->and($recurringEntry->fresh()->user_id)->toBe($owner->id);
});

it('restores a shared membership by normalizing legacy shared-category budgets back to the personal reference budget', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $account = createTestAccount($owner, ['name' => 'Lifecycle budget account']);

    app(CategoryFoundationService::class)->ensureForUser($owner);

    $expenseRoot = Category::query()
        ->where('user_id', $owner->id)
        ->where('foundation_key', 'expense')
        ->whereNull('account_id')
        ->firstOrFail();

    $insurance = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Assicurazione',
        'slug' => 'assicurazione-lifecycle-budget',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $sharedExpenseRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => null,
        'name' => 'Spese',
        'slug' => sprintf('shared-%d-root-expense', $account->id),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 2,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $sharedInsurance = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => $sharedExpenseRoot->id,
        'name' => 'Assicurazione',
        'slug' => 'shared-assicurazione-lifecycle-budget',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    Budget::query()->create([
        'user_id' => $owner->id,
        'category_id' => $insurance->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 500,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    Budget::query()->create([
        'user_id' => $owner->id,
        'category_id' => $sharedInsurance->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 200,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    createActiveMembership($account, $owner, AccountMembershipRoleEnum::OWNER->value, $owner->id);

    $membership = createActiveMembership($account, $member, AccountMembershipRoleEnum::EDITOR->value, $owner->id);
    $membership->status = AccountMembershipStatusEnum::REVOKED;
    $membership->revoked_at = now();
    $membership->revoked_by_user_id = $owner->id;
    $membership->save();

    $service = app(AccountMembershipLifecycleService::class);

    $service->restore($membership->fresh(), $owner);

    expect(Budget::query()
        ->where('user_id', $owner->id)
        ->where('category_id', $sharedInsurance->id)
        ->where('year', 2026)
        ->where('month', 2)
        ->count())->toBe(0)
        ->and((float) Budget::query()
            ->where('user_id', $owner->id)
            ->where('category_id', $insurance->id)
            ->where('year', 2026)
            ->where('month', 2)
            ->value('amount'))->toBe(700.0)
        ->and(Budget::query()
            ->where('category_id', $insurance->id)
            ->where('year', 2026)
            ->where('month', 2)
            ->count())->toBe(1);
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
