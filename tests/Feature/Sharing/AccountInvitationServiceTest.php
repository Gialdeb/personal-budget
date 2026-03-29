<?php

use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\BudgetTypeEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\InvitationStatusEnum;
use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Exceptions\CannotInviteToAccountException;
use App\Exceptions\InvalidAccountInvitationException;
use App\Models\Account;
use App\Models\AccountInvitation;
use App\Models\AccountMembership;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Categories\CategoryFoundationService;
use App\Services\Sharing\AccountInvitationService;
use App\Services\Sharing\AccountMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('allows original owner to create an account invitation', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'Shared account']);

    $service = app(AccountInvitationService::class);

    $result = $service->createInvitation(
        $account,
        $owner,
        'wife@gmail.com',
        AccountMembershipRoleEnum::VIEWER->value,
        null,
        now()->addDays(7),
    );

    expect($result['invitation'])->toBeInstanceOf(AccountInvitation::class)
        ->and($result['invitation']->status)->toBe(InvitationStatusEnum::PENDING)
        ->and($result['plain_token'])->not->toBeEmpty();
});

it('prevents non original owner from inviting to the same account', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $other = User::factory()->create(['email' => 'other@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'Shared account']);

    $service = app(AccountInvitationService::class);

    expect(fn () => $service->createInvitation(
        $account,
        $other,
        'wife@gmail.com',
        AccountMembershipRoleEnum::VIEWER->value,
    ))->toThrow(CannotInviteToAccountException::class);
});

it('accepts an account invitation and creates membership', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wife@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'Shared account']);

    $invitationService = app(AccountInvitationService::class);
    $membershipService = app(AccountMembershipService::class);

    $created = $invitationService->createInvitation(
        $account,
        $owner,
        $invitee->email,
        AccountMembershipRoleEnum::EDITOR->value,
        null,
        now()->addDays(7),
    );

    $membership = $membershipService->acceptInvitation(
        $created['invitation']->fresh(),
        $invitee,
        $created['plain_token'],
    );

    expect($membership)->toBeInstanceOf(AccountMembership::class)
        ->and($membership->status)->toBe(AccountMembershipStatusEnum::ACTIVE)
        ->and($membership->role)->toBe(AccountMembershipRoleEnum::EDITOR);

    expect($created['invitation']->fresh()->status)->toBe(InvitationStatusEnum::ACCEPTED);
});

it('bootstraps only used categories and tracked items into the shared account catalog when the invitation is accepted', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'invitee@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'MedioBanca Premier']);

    app(CategoryFoundationService::class)->ensureForUser($owner);

    $billRoot = Category::query()
        ->where('user_id', $owner->id)
        ->where('foundation_key', 'bill')
        ->whereNull('account_id')
        ->firstOrFail();

    $internet = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $billRoot->id,
        'name' => 'Internet casa',
        'slug' => 'internet-casa-owner-bootstrap',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::BILL->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $unusedCategory = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $billRoot->id,
        'name' => 'Categoria personale non usata',
        'slug' => 'categoria-personale-non-usata',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::BILL->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $fastweb = TrackedItem::query()->create([
        'user_id' => $owner->id,
        'uuid' => (string) Str::uuid(),
        'name' => 'Fastweb',
        'slug' => 'fastweb-owner-bootstrap',
        'type' => 'payee',
        'is_active' => true,
        'settings' => [],
    ]);
    $fastweb->compatibleCategories()->sync([$internet->id]);

    $unusedTrackedItem = TrackedItem::query()->create([
        'user_id' => $owner->id,
        'uuid' => (string) Str::uuid(),
        'name' => 'Unused personal reference',
        'slug' => 'unused-personal-reference',
        'type' => 'payee',
        'is_active' => true,
        'settings' => [],
    ]);
    $unusedTrackedItem->compatibleCategories()->sync([$unusedCategory->id]);

    $transaction = Transaction::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'account_id' => $account->id,
        'category_id' => $internet->id,
        'tracked_item_id' => $fastweb->id,
        'transaction_date' => '2026-02-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 29.90,
        'currency' => 'EUR',
        'description' => 'Fastweb febbraio',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'value_date' => '2026-02-15',
    ]);

    $invitationService = app(AccountInvitationService::class);
    $membershipService = app(AccountMembershipService::class);

    $created = $invitationService->createInvitation(
        $account,
        $owner,
        $invitee->email,
        AccountMembershipRoleEnum::EDITOR->value,
        null,
        now()->addDays(7),
    );

    $membershipService->acceptInvitation(
        $created['invitation']->fresh(),
        $invitee,
        $created['plain_token'],
    );

    $sharedInternet = Category::query()
        ->where('account_id', $account->id)
        ->where('name', 'Internet casa')
        ->first();

    $sharedFastweb = TrackedItem::query()
        ->where('account_id', $account->id)
        ->where('name', 'Fastweb')
        ->first();

    expect($sharedInternet)->not->toBeNull()
        ->and($sharedFastweb)->not->toBeNull()
        ->and($transaction->fresh()->category_id)->toBe($sharedInternet?->id)
        ->and($transaction->fresh()->tracked_item_id)->toBe($sharedFastweb?->id)
        ->and($sharedInternet?->parent?->name)->toBe('Bollette')
        ->and(Category::query()->where('account_id', $account->id)->where('name', 'Categoria personale non usata')->count())->toBe(0)
        ->and(TrackedItem::query()->where('account_id', $account->id)->where('name', 'Unused personal reference')->count())->toBe(0);
});

it('reuses existing shared categories and tracked items without duplicates during shared bootstrap', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'invitee@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'MedioBanca Premier']);

    app(CategoryFoundationService::class)->ensureForUser($owner);

    $billRoot = Category::query()
        ->where('user_id', $owner->id)
        ->where('foundation_key', 'bill')
        ->whereNull('account_id')
        ->firstOrFail();

    $internet = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $billRoot->id,
        'name' => 'Internet casa',
        'slug' => 'internet-casa-owner-bootstrap-duplicates',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::BILL->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $fastweb = TrackedItem::query()->create([
        'user_id' => $owner->id,
        'uuid' => (string) Str::uuid(),
        'name' => 'Fastweb',
        'slug' => 'fastweb-owner-bootstrap-duplicates',
        'type' => 'payee',
        'is_active' => true,
        'settings' => [],
    ]);
    $fastweb->compatibleCategories()->sync([$internet->id]);

    $sharedBillRoot = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => null,
        'name' => 'Bollette',
        'slug' => sprintf('shared-%d-root-bill', $account->id),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::BILL->value,
        'sort_order' => 3,
        'is_active' => true,
        'is_selectable' => true,
        'is_system' => true,
    ]);

    $sharedInternet = Category::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'parent_id' => $sharedBillRoot->id,
        'name' => 'Internet casa',
        'slug' => 'shared-internet-casa-existing',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::BILL->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $sharedFastweb = TrackedItem::query()->create([
        'user_id' => $owner->id,
        'account_id' => $account->id,
        'uuid' => (string) Str::uuid(),
        'name' => 'Fastweb',
        'slug' => 'fastweb-owner-bootstrap-duplicates',
        'type' => 'payee',
        'is_active' => true,
        'settings' => [],
    ]);
    $sharedFastweb->compatibleCategories()->sync([$sharedInternet->id]);

    $transaction = Transaction::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'account_id' => $account->id,
        'category_id' => $internet->id,
        'tracked_item_id' => $fastweb->id,
        'transaction_date' => '2026-02-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 29.90,
        'currency' => 'EUR',
        'description' => 'Fastweb febbraio duplicate-safe',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'value_date' => '2026-02-15',
    ]);

    $invitationService = app(AccountInvitationService::class);
    $membershipService = app(AccountMembershipService::class);

    $created = $invitationService->createInvitation(
        $account,
        $owner,
        $invitee->email,
        AccountMembershipRoleEnum::EDITOR->value,
        null,
        now()->addDays(7),
    );

    $membershipService->acceptInvitation(
        $created['invitation']->fresh(),
        $invitee,
        $created['plain_token'],
    );

    expect(Category::query()->where('account_id', $account->id)->where('name', 'Internet casa')->count())->toBe(1)
        ->and(TrackedItem::query()->where('account_id', $account->id)->where('slug', 'fastweb-owner-bootstrap-duplicates')->count())->toBe(1)
        ->and($transaction->fresh()->category_id)->toBe($sharedInternet->id)
        ->and($transaction->fresh()->tracked_item_id)->toBe($sharedFastweb->id);
});

it('bootstraps only recurring entries already linked to the shared account and makes them visible to both owner and invitee', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'invitee@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'MedioBanca Premier']);
    $otherAccount = createTestAccount($owner, ['name' => 'Conto personale separato']);

    ensureRecurringYear($owner);
    ensureRecurringYear($invitee);

    $sharedRecurring = createSharedLifecycleRecurringEntry($owner, $account, 'Fastweb recurring bootstrap');
    $otherRecurring = createSharedLifecycleRecurringEntry($owner, $otherAccount, 'Altro conto personale');

    $invitationService = app(AccountInvitationService::class);
    $membershipService = app(AccountMembershipService::class);

    $created = $invitationService->createInvitation(
        $account,
        $owner,
        $invitee->email,
        AccountMembershipRoleEnum::EDITOR->value,
        null,
        now()->addDays(7),
    );

    $membershipService->acceptInvitation(
        $created['invitation']->fresh(),
        $invitee,
        $created['plain_token'],
    );

    expect(RecurringEntry::query()->where('account_id', $account->id)->count())->toBe(1)
        ->and(RecurringEntry::query()->where('account_id', $otherAccount->id)->count())->toBe(1)
        ->and($sharedRecurring->fresh()->user_id)->toBe($owner->id)
        ->and($sharedRecurring->fresh()->account_id)->toBe($account->id);

    $this->actingAs($owner)
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 2,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntries', fn ($entries) => collect($entries)
                ->contains(fn ($entry) => $entry['uuid'] === $sharedRecurring->uuid && $entry['title'] === 'Fastweb recurring bootstrap'))
        );

    $this->actingAs($invitee)
        ->get(route('recurring-entries.index', [
            'year' => 2026,
            'month' => 2,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recurringEntries', fn ($entries) => collect($entries)
                ->contains(fn ($entry) => $entry['uuid'] === $sharedRecurring->uuid && $entry['title'] === 'Fastweb recurring bootstrap'))
            ->where('recurringEntries', fn ($entries) => collect($entries)
                ->doesntContain(fn ($entry) => $entry['uuid'] === $otherRecurring->uuid || $entry['title'] === 'Altro conto personale'))
        );
});

it('keeps category budgets personal when an account becomes shared and does not materialize unused personal budgets', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'invitee@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'MedioBanca Premier']);

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
        'slug' => 'assicurazione-owner-bootstrap',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $unusedCategory = Category::query()->create([
        'user_id' => $owner->id,
        'parent_id' => $expenseRoot->id,
        'name' => 'Budget personale non condiviso',
        'slug' => 'budget-personale-non-condiviso',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE->value,
        'group_type' => CategoryGroupTypeEnum::EXPENSE->value,
        'sort_order' => 0,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $budget = Budget::query()->create([
        'user_id' => $owner->id,
        'category_id' => $insurance->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 700,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    $unusedBudget = Budget::query()->create([
        'user_id' => $owner->id,
        'category_id' => $unusedCategory->id,
        'year' => 2026,
        'month' => 2,
        'amount' => 120,
        'budget_type' => BudgetTypeEnum::LIMIT->value,
    ]);

    Transaction::query()->create([
        'user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'account_id' => $account->id,
        'category_id' => $insurance->id,
        'transaction_date' => '2026-02-15',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'kind' => TransactionKindEnum::MANUAL->value,
        'amount' => 670,
        'currency' => 'EUR',
        'description' => 'Assicurazione febbraio',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
        'value_date' => '2026-02-15',
    ]);

    $invitationService = app(AccountInvitationService::class);
    $membershipService = app(AccountMembershipService::class);

    $created = $invitationService->createInvitation(
        $account,
        $owner,
        $invitee->email,
        AccountMembershipRoleEnum::EDITOR->value,
        null,
        now()->addDays(7),
    );

    $membershipService->acceptInvitation(
        $created['invitation']->fresh(),
        $invitee,
        $created['plain_token'],
    );

    $sharedInsurance = Category::query()
        ->where('account_id', $account->id)
        ->where('name', 'Assicurazione')
        ->first();

    expect($sharedInsurance)->not->toBeNull()
        ->and((int) $budget->fresh()->user_id)->toBe($owner->id)
        ->and((int) $budget->fresh()->category_id)->toBe($insurance->id)
        ->and((float) $budget->fresh()->amount)->toBe(700.0)
        ->and(Budget::query()->where('category_id', $sharedInsurance?->id)->count())->toBe(0)
        ->and((int) $unusedBudget->fresh()->category_id)->toBe($unusedCategory->id)
        ->and(Category::query()->where('account_id', $account->id)->where('name', 'Budget personale non condiviso')->count())->toBe(0);
});

it('rejects invitation acceptance if email does not match', function () {
    $owner = User::factory()->create(['email' => 'owner@gmail.com']);
    $invitee = User::factory()->create(['email' => 'wrong@gmail.com']);
    $account = createTestAccount($owner, ['name' => 'Shared account']);

    $invitationService = app(AccountInvitationService::class);
    $membershipService = app(AccountMembershipService::class);

    $created = $invitationService->createInvitation(
        $account,
        $owner,
        'wife@gmail.com',
        AccountMembershipRoleEnum::VIEWER->value,
        null,
        now()->addDays(7),
    );

    expect(fn () => $membershipService->acceptInvitation(
        $created['invitation']->fresh(),
        $invitee,
        $created['plain_token'],
    ))->toThrow(InvalidAccountInvitationException::class);
});

function ensureRecurringYear(User $user, int $year = 2026): void
{
    UserYear::query()->firstOrCreate([
        'user_id' => $user->id,
        'year' => $year,
    ], [
        'is_closed' => false,
    ]);
}

function createSharedLifecycleRecurringEntry(User $user, Account $account, string $title): RecurringEntry
{
    return RecurringEntry::query()->create([
        'user_id' => $user->id,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'account_id' => $account->id,
        'title' => $title,
        'description' => 'Recurring shared lifecycle bootstrap',
        'direction' => TransactionDirectionEnum::EXPENSE->value,
        'expected_amount' => 29.90,
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
}
