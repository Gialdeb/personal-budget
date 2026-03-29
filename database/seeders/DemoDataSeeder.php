<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatabaseSeeder::class,
            NotificationTopicSeeder::class,
            CommunicationTemplateSeeder::class,
            CommunicationCategorySeeder::class,
            DefaultScopeSeeder::class,
            DefaultCategorySeeder::class,
            TrackedItemSeeder::class,
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
