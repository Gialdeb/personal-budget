<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountReconciliation;
use App\Models\User;
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

        $accounts = Account::where('user_id', $user->id)->get()->keyBy('name');

        $rows = [
            [
                'account' => 'Conto Intesa Personale',
                'date' => '2024-12-31',
                'expected_balance' => 3200.00,
                'actual_balance' => 3188.00,
                'difference_amount' => -12.00,
                'notes' => 'Riconciliazione seed di fine anno 2024',
            ],
            [
                'account' => 'Conto Intesa Personale',
                'date' => '2025-12-31',
                'expected_balance' => 7025.00,
                'actual_balance' => 6992.00,
                'difference_amount' => -33.00,
                'notes' => 'Riconciliazione seed di fine anno 2025',
            ],
            [
                'account' => 'Carta Revolut',
                'date' => '2025-12-31',
                'expected_balance' => 310.00,
                'actual_balance' => 309.00,
                'difference_amount' => -1.00,
                'notes' => 'Riconciliazione seed Carta Revolut 2025',
            ],
        ];

        foreach ($rows as $row) {
            $account = $accounts[$row['account']] ?? null;

            if (! $account) {
                continue;
            }

            AccountReconciliation::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'reconciliation_date' => $row['date'],
                ],
                [
                    'expected_balance' => $row['expected_balance'],
                    'actual_balance' => $row['actual_balance'],
                    'difference_amount' => $row['difference_amount'],
                    'adjustment_transaction_id' => null,
                    'notes' => $row['notes'],
                    'created_by' => $user->id,
                ]
            );
        }
    }
}
