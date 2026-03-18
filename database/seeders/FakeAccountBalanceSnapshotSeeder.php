<?php

namespace Database\Seeders;

use App\Enums\AccountBalanceSnapshotSourceTypeEnum;
use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FakeAccountBalanceSnapshotSeeder extends Seeder
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
            ['account' => 'Conto Intesa Personale', 'date' => '2025-03-31', 'balance' => 4285.00],
            ['account' => 'Conto Intesa Personale', 'date' => '2025-06-30', 'balance' => 5170.00],
            ['account' => 'Conto Intesa Personale', 'date' => '2025-09-30', 'balance' => 6035.00],
            ['account' => 'Conto Intesa Personale', 'date' => '2025-12-31', 'balance' => 6890.00],

            ['account' => 'Carta Revolut', 'date' => '2025-03-31', 'balance' => 310.00],
            ['account' => 'Carta Revolut', 'date' => '2025-06-30', 'balance' => 260.00],
            ['account' => 'Carta Revolut', 'date' => '2025-09-30', 'balance' => 340.00],
            ['account' => 'Carta Revolut', 'date' => '2025-12-31', 'balance' => 280.00],

            ['account' => 'Cassa Casa 1', 'date' => '2025-06-30', 'balance' => 190.00],
            ['account' => 'Cassa Casa 1', 'date' => '2025-12-31', 'balance' => 210.00],
        ];

        foreach ($rows as $row) {
            $account = $accounts[$row['account']] ?? null;

            if (! $account) {
                continue;
            }

            AccountBalanceSnapshot::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'snapshot_date' => $row['date'],
                ],
                [
                    'balance' => $row['balance'],
                    'source_type' => AccountBalanceSnapshotSourceTypeEnum::MANUAL,
                    'notes' => 'Snapshot seed 2025',
                ]
            );
        }
    }
}
