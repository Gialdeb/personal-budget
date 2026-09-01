<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\AccountMembershipRoleEnum;
use App\Enums\AccountMembershipStatusEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\MembershipSourceEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\AccountMembership;
use App\Models\AccountType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('returns owned and active shared accounts with access metadata and no duplicates', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $ownedAccount = createTestAccount($user, ['name' => 'Owned account']);
    $sharedAccount = createTestAccount($owner, ['name' => 'Shared account']);
    $inactiveSharedAccount = createTestAccount($otherOwner, ['name' => 'Inactive shared account']);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $ownedAccount->id,
        'user_id' => $user->id,
        'role' => AccountMembershipRoleEnum::OWNER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'granted_by_user_id' => $user->id,
        'source' => MembershipSourceEnum::MIGRATION,
        'joined_at' => now(),
    ]);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $sharedAccount->id,
        'user_id' => $user->id,
        'role' => AccountMembershipRoleEnum::EDITOR,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::DIRECT,
        'joined_at' => now(),
    ]);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $inactiveSharedAccount->id,
        'user_id' => $user->id,
        'role' => AccountMembershipRoleEnum::VIEWER,
        'status' => AccountMembershipStatusEnum::REVOKED,
        'granted_by_user_id' => $otherOwner->id,
        'source' => MembershipSourceEnum::DIRECT,
        'joined_at' => now(),
        'revoked_at' => now(),
    ]);

    $accounts = app(AccessibleAccountsQuery::class)->get($user);

    expect($accounts)->toHaveCount(2)
        ->and($accounts->pluck('id')->unique())->toHaveCount(2)
        ->and($accounts->pluck('name')->all())->toBe(['Owned account', 'Shared account']);

    $owned = $accounts->firstWhere('id', $ownedAccount->id);
    $shared = $accounts->firstWhere('id', $sharedAccount->id);

    expect((bool) $owned?->getAttribute('is_owned'))->toBeTrue()
        ->and((bool) $owned?->getAttribute('is_shared'))->toBeFalse()
        ->and($owned?->getAttribute('membership_role'))->toBe(AccountMembershipRoleEnum::OWNER->value)
        ->and($owned?->getAttribute('membership_status'))->toBe(AccountMembershipStatusEnum::ACTIVE->value)
        ->and((bool) $owned?->getAttribute('can_view'))->toBeTrue()
        ->and((bool) $owned?->getAttribute('can_edit'))->toBeTrue()
        ->and((bool) $shared?->getAttribute('is_owned'))->toBeFalse()
        ->and((bool) $shared?->getAttribute('is_shared'))->toBeTrue()
        ->and($shared?->getAttribute('membership_role'))->toBe(AccountMembershipRoleEnum::EDITOR->value)
        ->and($shared?->getAttribute('membership_status'))->toBe(AccountMembershipStatusEnum::ACTIVE->value)
        ->and((bool) $shared?->getAttribute('can_view'))->toBeTrue()
        ->and((bool) $shared?->getAttribute('can_edit'))->toBeTrue();
});

it('supports owned shared and specific account filters', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    $ownedAccount = createTestAccount($user, ['name' => 'Owned account']);
    $sharedAccount = createTestAccount($owner, ['name' => 'Shared account']);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $sharedAccount->id,
        'user_id' => $user->id,
        'role' => AccountMembershipRoleEnum::VIEWER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::DIRECT,
        'joined_at' => now(),
    ]);

    $query = app(AccessibleAccountsQuery::class);

    expect($query->get($user, 'owned')->pluck('id')->all())->toBe([$ownedAccount->id])
        ->and($query->get($user, 'shared')->pluck('id')->all())->toBe([$sharedAccount->id])
        ->and($query->get($user, 'all', $sharedAccount->uuid)->pluck('id')->all())->toBe([$sharedAccount->id]);
});

it('adds backward compatible accessible account filter metadata to the dashboard payload', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    seedAccessibleAccountsDashboardFixture($user);
    $sharedAccount = createTestAccount($owner, ['name' => 'Shared dashboard account']);

    AccountMembership::query()->create([
        'uuid' => (string) Str::uuid(),
        'account_id' => $sharedAccount->id,
        'user_id' => $user->id,
        'role' => AccountMembershipRoleEnum::VIEWER,
        'status' => AccountMembershipStatusEnum::ACTIVE,
        'granted_by_user_id' => $owner->id,
        'source' => MembershipSourceEnum::DIRECT,
        'joined_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['year' => 2025, 'month' => 3]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.filters.year', 2025)
            ->where('dashboard.filters.month', 3)
            ->where('dashboard.filters.account_scope', 'all')
            ->where('dashboard.filters.account_uuid', null)
            ->where('dashboard.filters.account_scope_options', fn ($options) => collect($options)
                ->pluck('value')
                ->all() === ['all', 'owned', 'shared'])
            ->where('dashboard.filters.account_options', fn ($options) => collect($options)
                ->contains(fn ($option) => $option['value'] === $sharedAccount->uuid
                    && $option['account_type_code'] === 'payment_account'
                    && $option['is_shared'] === true
                    && $option['is_owned'] === false
                    && $option['membership_role'] === AccountMembershipRoleEnum::VIEWER->value
                    && $option['membership_status'] === AccountMembershipStatusEnum::ACTIVE->value))
            ->where('dashboard.overview.income_total_raw', fn ($value) => (float) $value === 1250.0));
});

it('orders accounts by fixed type priority: payment, cash, credit card, then other types', function () {
    $user = User::factory()->create();

    $paymentType = AccountType::query()->create([
        'code' => 'payment_account',
        'name' => 'Conto di pagamento',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);
    $cashType = AccountType::query()->create([
        'code' => 'cash_account',
        'name' => 'Contanti',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);
    $cardType = AccountType::query()->create([
        'code' => 'credit_card',
        'name' => 'Carta di credito',
        'balance_nature' => AccountBalanceNatureEnum::LIABILITY->value,
    ]);
    $savingsType = AccountType::query()->create([
        'code' => 'savings_account',
        'name' => 'Risparmio',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $savings = Account::query()->create(baseAccountAttributes($user, $savingsType, 'Risparmio'));
    $card = Account::query()->create(baseAccountAttributes($user, $cardType, 'Carta'));
    $cash = Account::query()->create(baseAccountAttributes($user, $cashType, 'Contanti'));
    $payment = Account::query()->create(baseAccountAttributes($user, $paymentType, 'Conto principale'));

    $accounts = app(AccessibleAccountsQuery::class)->get($user);

    expect($accounts->pluck('id')->all())->toBe([
        $payment->id,
        $cash->id,
        $card->id,
        $savings->id,
    ]);
});

it('respects the manually saved sort_order within the same account type', function () {
    $user = User::factory()->create();

    $paymentType = AccountType::query()->create([
        'code' => 'payment_account',
        'name' => 'Conto di pagamento',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $first = Account::query()->create([
        ...baseAccountAttributes($user, $paymentType, 'Secondo per nome'),
        'sort_order' => 1,
    ]);
    $second = Account::query()->create([
        ...baseAccountAttributes($user, $paymentType, 'Primo per nome'),
        'sort_order' => 0,
    ]);

    $accounts = app(AccessibleAccountsQuery::class)->get($user);

    expect($accounts->pluck('id')->all())->toBe([$second->id, $first->id]);
});

it('places accounts with an unknown or custom account type after every known type', function () {
    $user = User::factory()->create();

    $customType = AccountType::query()->create([
        'code' => 'exotic_wallet',
        'name' => 'Exotic wallet',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);
    $paymentType = AccountType::query()->create([
        'code' => 'payment_account',
        'name' => 'Conto di pagamento',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);
    $loanType = AccountType::query()->create([
        'code' => 'loan_account',
        'name' => 'Prestito',
        'balance_nature' => AccountBalanceNatureEnum::LIABILITY->value,
    ]);

    $custom = Account::query()->create(baseAccountAttributes($user, $customType, 'Wallet esotico'));
    $payment = Account::query()->create(baseAccountAttributes($user, $paymentType, 'Conto principale'));
    $loan = Account::query()->create(baseAccountAttributes($user, $loanType, 'Prestito auto'));

    $accounts = app(AccessibleAccountsQuery::class)->get($user);

    expect($accounts->pluck('id')->all())->toBe([$payment->id, $loan->id, $custom->id]);
});

it('does not let the default account flag influence the display order', function () {
    $user = User::factory()->create();

    $paymentType = AccountType::query()->create([
        'code' => 'payment_account',
        'name' => 'Conto di pagamento',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $first = Account::query()->create([
        ...baseAccountAttributes($user, $paymentType, 'Conto A'),
        'sort_order' => 0,
    ]);
    $second = Account::query()->create([
        ...baseAccountAttributes($user, $paymentType, 'Conto B'),
        'sort_order' => 1,
        'is_default' => true,
    ]);

    $accounts = app(AccessibleAccountsQuery::class)->get($user);

    expect($accounts->pluck('id')->all())->toBe([$first->id, $second->id]);
});

it('falls back to a deterministic order for accounts without a meaningful sort_order', function () {
    $user = User::factory()->create();

    $paymentType = AccountType::query()->create([
        'code' => 'payment_account',
        'name' => 'Conto di pagamento',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $alpha = Account::query()->create(baseAccountAttributes($user, $paymentType, 'Alpha'));
    $beta = Account::query()->create(baseAccountAttributes($user, $paymentType, 'Beta'));

    $accounts = app(AccessibleAccountsQuery::class)->get($user);

    expect($accounts->pluck('id')->all())->toBe([$alpha->id, $beta->id]);
});

it('applies the same centralized order to editable account lists via getEditable', function () {
    $user = User::factory()->create();

    $paymentType = AccountType::query()->create([
        'code' => 'payment_account',
        'name' => 'Conto di pagamento',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);
    $cardType = AccountType::query()->create([
        'code' => 'credit_card',
        'name' => 'Carta di credito',
        'balance_nature' => AccountBalanceNatureEnum::LIABILITY->value,
    ]);

    $card = Account::query()->create(baseAccountAttributes($user, $cardType, 'Carta'));
    $payment = Account::query()->create(baseAccountAttributes($user, $paymentType, 'Conto principale'));

    $accounts = app(AccessibleAccountsQuery::class)->getEditable($user);

    expect($accounts->pluck('id')->all())->toBe([$payment->id, $card->id]);
});

/**
 * @return array<string, mixed>
 */
function baseAccountAttributes(User $user, AccountType $accountType, string $name): array
{
    return [
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => $name,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance' => 0,
        'current_balance' => 0,
        'is_manual' => true,
        'is_active' => true,
    ];
}

function seedAccessibleAccountsDashboardFixture(User $user): Account
{
    $accountType = AccountType::query()->create([
        'code' => 'checking',
        'name' => 'Checking',
        'balance_nature' => AccountBalanceNatureEnum::ASSET->value,
    ]);

    $account = Account::query()->create([
        'user_id' => $user->id,
        'account_type_id' => $accountType->id,
        'name' => 'Owned dashboard account',
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance' => 500,
        'current_balance' => 1750,
        'is_manual' => true,
        'is_active' => true,
    ]);

    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Salary',
        'slug' => 'salary',
        'direction_type' => CategoryDirectionTypeEnum::INCOME->value,
        'group_type' => CategoryGroupTypeEnum::INCOME->value,
        'is_active' => true,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'transaction_date' => '2025-03-05',
        'direction' => TransactionDirectionEnum::INCOME->value,
        'amount' => 1250,
        'currency' => 'EUR',
        'description' => 'Dashboard income',
        'source_type' => TransactionSourceTypeEnum::MANUAL->value,
        'status' => TransactionStatusEnum::CONFIRMED->value,
    ]);

    return $account;
}
