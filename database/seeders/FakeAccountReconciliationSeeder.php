<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountReconciliation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FakeAccountReconciliationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $account = Account::where('user_id', $user->id)
            ->where('name', 'Conto Intesa Personale')
            ->first();

        if (! $account) {
            return;
        }

        AccountReconciliation::updateOrCreate(
            [
                'account_id' => $account->id,
                'reconciliation_date' => '2025-12-31',
            ],
            [
                'expected_balance' => 6890.00,
                'actual_balance' => 6865.00,
                'difference_amount' => -25.00,
                'adjustment_transaction_id' => null,
                'notes' => 'Riconciliazione seed di fine anno',
                'created_by' => $user->id,
            ]
        );
    }
}
