<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicUuidRollout
{
    /**
     * @return array<string, string>
     */
    public static function domainTables(): array
    {
        return [
            'users' => 'users_uuid_unique',
            'user_settings' => 'user_settings_uuid_unique',
            'banks' => 'banks_uuid_unique',
            'account_types' => 'account_types_uuid_unique',
            'scopes' => 'scopes_uuid_unique',
            'categories' => 'categories_uuid_unique',
            'merchants' => 'merchants_uuid_unique',
            'merchant_aliases' => 'merchant_aliases_uuid_unique',
            'accounts' => 'accounts_uuid_unique',
            'account_opening_balances' => 'account_opening_balances_uuid_unique',
            'imports' => 'imports_uuid_unique',
            'import_rows' => 'import_rows_uuid_unique',
            'account_balance_snapshots' => 'account_balance_snapshots_uuid_unique',
            'transactions' => 'transactions_uuid_unique',
            'account_reconciliations' => 'account_reconciliations_uuid_unique',
            'transaction_splits' => 'transaction_splits_uuid_unique',
            'transaction_reviews' => 'transaction_reviews_uuid_unique',
            'transaction_matchers' => 'transaction_matchers_uuid_unique',
            'transaction_training_samples' => 'transaction_training_samples_uuid_unique',
            'recurring_entries' => 'recurring_entries_uuid_unique',
            'recurring_entry_occurrences' => 'recurring_entry_occurrences_uuid_unique',
            'scheduled_entries' => 'scheduled_entries_uuid_unique',
            'budgets' => 'budgets_uuid_unique',
            'user_years' => 'user_years_uuid_unique',
            'tracked_items' => 'tracked_items_uuid_unique',
            'user_banks' => 'user_banks_uuid_unique',
        ];
    }

    public static function backfillTable(string $table): void
    {
        DB::table($table)
            ->select('id')
            ->whereNull('uuid')
            ->orderBy('id')
            ->lazyById(100)
            ->each(function (object $record) use ($table): void {
                DB::table($table)
                    ->where('id', $record->id)
                    ->update([
                        'uuid' => (string) Str::uuid(),
                    ]);
            });
    }

    public static function backfillAll(): void
    {
        foreach (array_keys(self::domainTables()) as $table) {
            self::backfillTable($table);
        }
    }
}
