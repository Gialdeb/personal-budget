<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        //        User::factory()->create([
        //            'name' => 'Test User',
        //            'email' => 'test@example.com',
        //        ]);
        $this->call([
            UserSeeder::class,
            UserSettingsSeeder::class,
            UserYearSeeder::class,
            AccountTypeSeeder::class,
            DefaultBankSeeder::class,
            DefaultScopeSeeder::class,
            DefaultCategorySeeder::class,
            DefaultMerchantSeeder::class,
            DefaultAccountSeeder::class,
            FakeAccountOpeningBalanceSeeder::class,
            FakeBudgetSeeder::class,
            FakeTransactionSeeder::class,
            FakeRecurringEntrySeeder::class,
            RecurringEntryOccurrenceSeeder::class,
            FakeScheduledEntrySeeder::class,
            FakeAccountBalanceSnapshotSeeder::class,
            FakeAccountReconciliationSeeder::class,
            MerchantAliasSeeder::class,
            TransactionMatcherSeeder::class,
            FakeTransactionSplitSeeder::class,
            FakeTransactionReviewSeeder::class,
            FakeTransactionTrainingSampleSeeder::class,
        ]);
    }
}
