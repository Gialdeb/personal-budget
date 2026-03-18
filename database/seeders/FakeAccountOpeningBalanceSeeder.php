<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountOpeningBalance;
use App\Models\User;
use Illuminate\Database\Seeder;

class FakeAccountOpeningBalanceSeeder extends Seeder
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
            ['account' => 'Conto Intesa Personale', 'date' => '2024-01-01', 'amount' => 2150.00, 'notes' => 'Saldo iniziale seed 2024'],
            ['account' => 'Carta Revolut', 'date' => '2024-01-01', 'amount' => 260.00, 'notes' => 'Saldo iniziale seed 2024'],
            ['account' => 'Cassa Casa 1', 'date' => '2024-01-01', 'amount' => 120.00, 'notes' => 'Saldo iniziale seed 2024'],
            ['account' => 'Conto Intesa Personale', 'date' => '2025-01-01', 'amount' => 3200.00, 'notes' => 'Saldo iniziale seed 2025'],
            ['account' => 'Carta Revolut', 'date' => '2025-01-01', 'amount' => 450.00, 'notes' => 'Saldo iniziale seed 2025'],
            ['account' => 'Cassa Casa 1', 'date' => '2025-01-01', 'amount' => 250.00, 'notes' => 'Saldo iniziale seed 2025'],
        ];

        foreach ($rows as $row) {
            $account = $accounts[$row['account']] ?? null;

            if (! $account) {
                continue;
            }

            AccountOpeningBalance::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'balance_date' => $row['date'],
                ],
                [
                    'amount' => $row['amount'],
                    'notes' => $row['notes'],
                    'created_by' => $user->id,
                ]
            );
        }
    }
}
