<?php

use App\Enums\AccountBalanceNatureEnum;
use App\Enums\BudgetTypeEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\RecurringEndModeEnum;
use App\Enums\RecurringEntryRecurrenceTypeEnum;
use App\Enums\RecurringEntryStatusEnum;
use App\Enums\RecurringEntryTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionSourceTypeEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringEntry;
use App\Models\Scope;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

test('export page renders available datasets and defaults', function () {
    $user = exportUser();

    $this->actingAs($user)
        ->get(route('exports.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Export')
            ->where('exportPage.defaults.dataset', 'transactions')
            ->where('exportPage.defaults.format', 'csv')
            ->where('exportPage.defaults.period_preset', 'this_month')
            ->where('exportPage.datasets.0.key', 'transactions')
            ->where('exportPage.datasets.6.key', 'full_export')
            ->where('exportPage.datasets.6.formats', ['json'])
        );
});

test('transactions csv export is scoped to the authenticated user and filtered by custom period', function () {
    $user = exportUser();
    $otherUser = exportUser();
    $account = exportAccount($user, 'Primary account');
    $category = exportCategory($user, 'Groceries');
    $trackedItem = exportTrackedItem($user, 'Weekly plan');
    $scope = exportScope($user, 'Household');

    $matchingTransaction = Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'scope_id' => $scope->id,
        'category_id' => $category->id,
        'tracked_item_id' => $trackedItem->id,
        'transaction_date' => '2026-02-14',
        'value_date' => '2026-02-14',
        'posted_at' => '2026-02-14 09:15:00',
        'direction' => TransactionDirectionEnum::EXPENSE,
        'kind' => TransactionKindEnum::MANUAL,
        'amount' => '42.50',
        'currency' => 'EUR',
        'description' => 'Groceries run',
        'notes' => 'Organic store',
        'source_type' => TransactionSourceTypeEnum::MANUAL,
        'status' => TransactionStatusEnum::CONFIRMED,
        'reference_code' => 'INV-42',
        'counterparty_name' => 'Local market',
        'balance_after' => '957.50',
        'is_transfer' => false,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'transaction_date' => '2025-12-31',
        'direction' => TransactionDirectionEnum::EXPENSE,
        'kind' => TransactionKindEnum::MANUAL,
        'amount' => '99.00',
        'currency' => 'EUR',
        'description' => 'Outside range',
        'source_type' => TransactionSourceTypeEnum::MANUAL,
        'status' => TransactionStatusEnum::CONFIRMED,
    ]);

    Transaction::query()->create([
        'user_id' => $otherUser->id,
        'account_id' => exportAccount($otherUser, 'Other account')->id,
        'transaction_date' => '2026-02-20',
        'direction' => TransactionDirectionEnum::EXPENSE,
        'kind' => TransactionKindEnum::MANUAL,
        'amount' => '10.00',
        'currency' => 'EUR',
        'description' => 'Other user transaction',
        'source_type' => TransactionSourceTypeEnum::MANUAL,
        'status' => TransactionStatusEnum::CONFIRMED,
    ]);

    $response = $this->actingAs($user)->get(route('exports.download', [
        'dataset' => 'transactions',
        'format' => 'csv',
        'period_preset' => 'custom_range',
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-31',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toContain('transactions_2026-01-01_to_2026-03-31.csv');

    $rows = csvRowsFromResponse($response);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['transaction_uuid'])->toBe($matchingTransaction->uuid)
        ->and($rows[0]['description'])->toBe('Groceries run')
        ->and($rows[0]['amount'])->toBe('42.50')
        ->and($rows[0]['account_name'])->toBe('Primary account')
        ->and($rows[0]['category_name'])->toBe('Groceries')
        ->and($rows[0]['category_path'])->toBe('Groceries')
        ->and($rows[0])->not->toHaveKey('id')
        ->and($rows[0])->not->toHaveKey('account_id')
        ->and($rows[0])->not->toHaveKey('category_id');
});

test('category display matrix is consistent in transaction exports', function (string $locale, string $expectedFoundationName) {
    $user = exportUser();
    $user->forceFill(['locale' => $locale])->save();

    $account = exportAccount($user, 'Category matrix account');

    $foundationCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Alimentari',
        'name_is_custom' => false,
        'slug' => 'alimentari',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'color' => '#22c55e',
        'icon' => 'shopping-bag',
        'sort_order' => 10,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $renamedFoundationCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Insurance',
        'name_is_custom' => true,
        'slug' => 'auto-assicurazione',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'color' => '#22c55e',
        'icon' => 'shield-check',
        'sort_order' => 20,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    $customCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Bottega sotto casa',
        'name_is_custom' => true,
        'slug' => 'bottega-sotto-casa',
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'color' => '#22c55e',
        'icon' => 'store',
        'sort_order' => 30,
        'is_active' => true,
        'is_selectable' => true,
    ]);

    foreach ([
        [$foundationCategory, 'Foundation export matrix row', '2026-03-01'],
        [$renamedFoundationCategory, 'Renamed foundation export matrix row', '2026-03-02'],
        [$customCategory, 'Custom export matrix row', '2026-03-03'],
    ] as [$category, $description, $date]) {
        Transaction::query()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'transaction_date' => $date,
            'value_date' => $date,
            'direction' => TransactionDirectionEnum::EXPENSE,
            'kind' => TransactionKindEnum::MANUAL,
            'amount' => '10.00',
            'currency' => 'EUR',
            'currency_code' => 'EUR',
            'description' => $description,
            'source_type' => TransactionSourceTypeEnum::MANUAL,
            'status' => TransactionStatusEnum::CONFIRMED,
        ]);
    }

    $response = $this->actingAs($user)->get(route('exports.download', [
        'dataset' => 'transactions',
        'format' => 'csv',
        'period_preset' => 'custom_range',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ]));

    $response->assertOk();

    $rows = collect(csvRowsFromResponse($response))->keyBy('description');

    expect($rows['Foundation export matrix row']['category_name'])->toBe($expectedFoundationName)
        ->and($rows['Foundation export matrix row']['category_path'])->toBe($expectedFoundationName)
        ->and($rows['Renamed foundation export matrix row']['category_name'])->toBe('Insurance')
        ->and($rows['Renamed foundation export matrix row']['category_path'])->toBe('Insurance')
        ->and($rows['Custom export matrix row']['category_name'])->toBe('Bottega sotto casa')
        ->and($rows['Custom export matrix row']['category_path'])->toBe('Bottega sotto casa');
})->with([
    'italian locale' => ['it', 'Alimentari'],
    'english locale' => ['en', 'Groceries'],
]);

test('accounts json export includes readable fields for non temporal datasets', function () {
    $user = exportUser();
    $account = exportAccount($user, 'Family account');

    $response = $this->actingAs($user)->get(route('exports.download', [
        'dataset' => 'accounts',
        'format' => 'json',
        'period_preset' => 'all_time',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toContain(sprintf('accounts_%s.json', now(config('app.timezone'))->format('Y-m-d')));

    /** @var array<string, mixed> $payload */
    $payload = json_decode($response->streamedContent(), true);

    expect($payload['metadata']['dataset'])->toBe('accounts')
        ->and($payload['metadata']['period']['preset'])->toBe('all_time')
        ->and($payload['records'])->toHaveCount(1)
        ->and($payload['records'][0]['account_uuid'])->toBe($account->uuid)
        ->and($payload['records'][0]['name'])->toBe('Family account')
        ->and($payload['records'][0]['currency_code'])->toBe('EUR')
        ->and($payload['records'][0]['current_balance'])->toBe('1000.00')
        ->and($payload['records'][0])->not->toHaveKey('id')
        ->and($payload['records'][0])->not->toHaveKey('user_id');
});

test('full export json stays scoped to the authenticated user and avoids numeric identifiers', function () {
    $user = exportUser();
    $otherUser = exportUser();

    $account = exportAccount($user, 'Backup account');
    $category = exportCategory($user, 'Salary');
    $trackedItem = exportTrackedItem($user, 'Payroll');
    $scope = exportScope($user, 'Work');

    Transaction::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'scope_id' => $scope->id,
        'category_id' => $category->id,
        'tracked_item_id' => $trackedItem->id,
        'transaction_date' => '2026-04-01',
        'direction' => TransactionDirectionEnum::INCOME,
        'kind' => TransactionKindEnum::MANUAL,
        'amount' => '2480.00',
        'currency' => 'EUR',
        'description' => 'April salary',
        'source_type' => TransactionSourceTypeEnum::MANUAL,
        'status' => TransactionStatusEnum::CONFIRMED,
    ]);

    RecurringEntry::query()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'scope_id' => $scope->id,
        'category_id' => $category->id,
        'tracked_item_id' => $trackedItem->id,
        'title' => 'Salary recurring',
        'direction' => TransactionDirectionEnum::INCOME,
        'expected_amount' => '2480.00',
        'total_amount' => '2480.00',
        'currency' => 'EUR',
        'entry_type' => RecurringEntryTypeEnum::RECURRING,
        'status' => RecurringEntryStatusEnum::ACTIVE,
        'recurrence_type' => RecurringEntryRecurrenceTypeEnum::MONTHLY,
        'recurrence_interval' => 1,
        'start_date' => '2026-01-01',
        'next_occurrence_date' => '2026-05-01',
        'end_mode' => RecurringEndModeEnum::NEVER,
        'auto_generate_occurrences' => true,
        'auto_create_transaction' => false,
        'is_active' => true,
    ]);

    Budget::query()->create([
        'user_id' => $user->id,
        'scope_id' => $scope->id,
        'category_id' => $category->id,
        'tracked_item_id' => $trackedItem->id,
        'year' => 2026,
        'month' => 4,
        'amount' => '2480.00',
        'budget_type' => BudgetTypeEnum::TARGET,
        'notes' => 'Monthly budget',
    ]);

    exportAccount($otherUser, 'Other user account');
    exportCategory($otherUser, 'Other category');
    exportTrackedItem($otherUser, 'Other tracked item');

    $response = $this->actingAs($user)->get(route('exports.download', [
        'dataset' => 'full_export',
        'format' => 'json',
        'period_preset' => 'all_time',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toContain(sprintf('full-export_%s.json', now(config('app.timezone'))->format('Y-m-d')));

    /** @var array<string, mixed> $payload */
    $payload = json_decode($response->streamedContent(), true);

    expect($payload['metadata']['dataset'])->toBe('full_export')
        ->and($payload['metadata']['datasets_included'])->toContain('transactions', 'budgets', 'recurring_entries')
        ->and($payload['profile']['user_uuid'])->toBe($user->uuid)
        ->and($payload['accounts'])->toHaveCount(1)
        ->and($payload['categories'])->toHaveCount(1)
        ->and($payload['tracked_items'])->toHaveCount(1)
        ->and($payload['transactions'])->toHaveCount(1)
        ->and($payload['recurring_entries'])->toHaveCount(1)
        ->and($payload['budgets'])->toHaveCount(1);

    assertNoNumericIdKeys($payload);
});

function exportUser(): User
{
    $user = User::factory()->create();

    UserSetting::query()->create([
        'user_id' => $user->id,
        'active_year' => 2026,
        'base_currency' => 'EUR',
        'settings' => [
            'dashboard' => [
                'visible_boxes' => [
                    'cashflow' => true,
                ],
            ],
        ],
    ]);

    return $user;
}

function exportScope(User $user, string $name): Scope
{
    return Scope::query()->create([
        'user_id' => $user->id,
        'name' => $name,
        'type' => 'personal',
        'color' => '#0ea5e9',
        'is_active' => true,
    ]);
}

function exportAccount(User $user, string $name): Account
{
    $bank = Bank::query()->create([
        'name' => "{$name} Bank",
        'slug' => str($name)->slug()->append('-bank')->value(),
        'country_code' => 'IT',
        'is_active' => true,
    ]);

    $accountType = AccountType::query()->create([
        'code' => str($name)->slug()->append('-account')->value(),
        'name' => 'Payment account',
        'balance_nature' => AccountBalanceNatureEnum::ASSET,
    ]);

    return Account::query()->create([
        'user_id' => $user->id,
        'bank_id' => $bank->id,
        'account_type_id' => $accountType->id,
        'scope_id' => exportScope($user, "{$name} scope")->id,
        'name' => $name,
        'currency' => 'EUR',
        'currency_code' => 'EUR',
        'opening_balance' => '1000.00',
        'opening_balance_date' => '2026-01-01',
        'current_balance' => '1000.00',
        'is_manual' => true,
        'is_active' => true,
        'is_reported' => true,
        'is_default' => true,
    ]);
}

function exportCategory(User $user, string $name): Category
{
    return Category::query()->create([
        'user_id' => $user->id,
        'name' => $name,
        'slug' => str($name)->slug()->value(),
        'direction_type' => CategoryDirectionTypeEnum::EXPENSE,
        'group_type' => CategoryGroupTypeEnum::EXPENSE,
        'color' => '#22c55e',
        'icon' => 'shopping-bag',
        'sort_order' => 10,
        'is_active' => true,
        'is_selectable' => true,
    ]);
}

function exportTrackedItem(User $user, string $name): TrackedItem
{
    return TrackedItem::query()->create([
        'user_id' => $user->id,
        'name' => $name,
        'slug' => str($name)->slug()->value(),
        'type' => 'reference',
        'is_active' => true,
    ]);
}

function csvRowsFromResponse(TestResponse $response): array
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent()) ?? '';
    $lines = collect(preg_split("/\r\n|\n|\r/", trim($content)))
        ->filter(fn (?string $line): bool => is_string($line) && $line !== '')
        ->values();

    $headers = str_getcsv((string) $lines->shift());

    return $lines
        ->map(function (string $line) use ($headers): array {
            /** @var array<int, string> $values */
            $values = str_getcsv($line);

            return array_combine($headers, $values);
        })
        ->all();
}

function assertNoNumericIdKeys(array $payload): void
{
    $forbiddenKeys = [
        'id',
        'user_id',
        'account_id',
        'category_id',
        'tracked_item_id',
        'parent_id',
        'scope_id',
    ];

    array_walk_recursive($payload, function (mixed $value, string|int $key) use ($forbiddenKeys): void {
        if (is_string($key)) {
            expect($forbiddenKeys)->not->toContain($key);
        }
    });
}
