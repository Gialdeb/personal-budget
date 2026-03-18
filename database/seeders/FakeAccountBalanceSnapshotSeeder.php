<?php

namespace Database\Seeders;

use App\Enums\AccountBalanceSnapshotSourceTypeEnum;
use App\Models\Account;
use App\Models\AccountBalanceSnapshot;
use App\Models\User;
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
            ['account' => 'Conto Intesa Personale', 'date' => '2024-03-31', 'balance' => 2485.00, 'notes' => 'Snapshot seed 2024'],
            ['account' => 'Conto Intesa Personale', 'date' => '2024-06-30', 'balance' => 2810.00, 'notes' => 'Snapshot seed 2024'],
            ['account' => 'Conto Intesa Personale', 'date' => '2024-09-30', 'balance' => 3040.00, 'notes' => 'Snapshot seed 2024'],
            ['account' => 'Conto Intesa Personale', 'date' => '2024-12-31', 'balance' => 3200.00, 'notes' => 'Snapshot seed 2024'],
            ['account' => 'Conto Intesa Personale', 'date' => '2025-03-31', 'balance' => 4325.00, 'notes' => 'Snapshot seed 2025'],
            ['account' => 'Conto Intesa Personale', 'date' => '2025-06-30', 'balance' => 5210.00, 'notes' => 'Snapshot seed 2025'],
            ['account' => 'Conto Intesa Personale', 'date' => '2025-09-30', 'balance' => 6140.00, 'notes' => 'Snapshot seed 2025'],
            ['account' => 'Conto Intesa Personale', 'date' => '2025-12-31', 'balance' => 7025.00, 'notes' => 'Snapshot seed 2025'],

            ['account' => 'Carta Revolut', 'date' => '2024-03-31', 'balance' => 335.00, 'notes' => 'Snapshot seed 2024'],
            ['account' => 'Carta Revolut', 'date' => '2024-06-30', 'balance' => 390.00, 'notes' => 'Snapshot seed 2024'],
            ['account' => 'Carta Revolut', 'date' => '2024-09-30', 'balance' => 425.00, 'notes' => 'Snapshot seed 2024'],
            ['account' => 'Carta Revolut', 'date' => '2024-12-31', 'balance' => 450.00, 'notes' => 'Snapshot seed 2024'],
            ['account' => 'Carta Revolut', 'date' => '2025-03-31', 'balance' => 330.00, 'notes' => 'Snapshot seed 2025'],
            ['account' => 'Carta Revolut', 'date' => '2025-06-30', 'balance' => 290.00, 'notes' => 'Snapshot seed 2025'],
            ['account' => 'Carta Revolut', 'date' => '2025-09-30', 'balance' => 360.00, 'notes' => 'Snapshot seed 2025'],
            ['account' => 'Carta Revolut', 'date' => '2025-12-31', 'balance' => 310.00, 'notes' => 'Snapshot seed 2025'],

            ['account' => 'Cassa Casa 1', 'date' => '2024-03-31', 'balance' => 150.00, 'notes' => 'Snapshot seed 2024'],
            ['account' => 'Cassa Casa 1', 'date' => '2024-06-30', 'balance' => 190.00, 'notes' => 'Snapshot seed 2024'],
            ['account' => 'Cassa Casa 1', 'date' => '2024-09-30', 'balance' => 220.00, 'notes' => 'Snapshot seed 2024'],
            ['account' => 'Cassa Casa 1', 'date' => '2024-12-31', 'balance' => 250.00, 'notes' => 'Snapshot seed 2024'],
            ['account' => 'Cassa Casa 1', 'date' => '2025-03-31', 'balance' => 205.00, 'notes' => 'Snapshot seed 2025'],
            ['account' => 'Cassa Casa 1', 'date' => '2025-06-30', 'balance' => 240.00, 'notes' => 'Snapshot seed 2025'],
            ['account' => 'Cassa Casa 1', 'date' => '2025-09-30', 'balance' => 225.00, 'notes' => 'Snapshot seed 2025'],
            ['account' => 'Cassa Casa 1', 'date' => '2025-12-31', 'balance' => 260.00, 'notes' => 'Snapshot seed 2025'],
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
                    'notes' => $row['notes'],
                ]
            );
        }
    }
}
